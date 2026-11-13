<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\TwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TwoFactorVerifyController extends Controller
{
    public function __construct(
        private TwoFactorAuthService $twoFactorService,
    ) {}

    /**
     * 2FA 検証画面を表示（TOTP コード入力）
     */
    public function show(Request $request): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        // 2FA 未設定の場合はセットアップへ
        if (! $user->two_factor_confirmed_at) {
            return redirect()->route('2fa.setup');
        }

        // すでにこのセッションで検証済みの場合は元のページへ
        if ($request->session()->get('2fa_verified', false)) {
            return redirect()->intended('/admin');
        }

        return view('auth.2fa-verify', [
            'user' => $user,
        ]);
    }

    /**
     * TOTP コードまたはリカバリーコードで検証
     */
    public function verify(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $code = trim($request->code);
        $verified = false;

        // TOTP コード検証
        if (! empty($user->two_factor_secret) && $this->twoFactorService->verifyCode($user->two_factor_secret, $code)) {
            $verified = true;
        }
        // リカバリーコード検証（使用済みコードは消費される）
        elseif ($this->twoFactorService->verifyAndConsumeRecoveryCode($user, $code)) {
            $verified = true;
            Log::info('2FA recovery code consumed', ['user_id' => $user->id]);
        }

        if (! $verified) {
            return back()->withErrors(['code' => '認証コードが正しくありません。'])->withInput();
        }

        $request->session()->put('2fa_verified', true);
        $request->session()->put('2fa_verified_at', now());

        Log::info('2FA verification succeeded', ['user_id' => $user->id]);

        return redirect()->intended('/admin')
            ->with('status', '二要素認証の検証が完了しました。');
    }

    /**
     * 2FA 無効化（ユーザー自身が 2FA を解除する場合）
     */
    public function disable(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        if (! $this->twoFactorService->verifyCode($user->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => '認証コードが正しくありません。']);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $request->session()->forget('2fa_verified');

        return redirect()->route('2fa.setup')
            ->with('status', '二要素認証を無効化しました。');
    }
}
