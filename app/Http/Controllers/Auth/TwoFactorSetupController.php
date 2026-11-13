<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthService;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TwoFactorSetupController extends Controller
{
    public function __construct(
        private TwoFactorAuthService $twoFactorService,
    ) {}

    /**
     * 2FA セットアップ画面を表示（秘密鍵生成・QR コード表示）
     */
    public function show(Request $request): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        // すでに 2FA 設定済みの場合は完了済みページへ
        if ($user->two_factor_confirmed_at) {
            return redirect()->route('2fa.verify')
                ->with('status', '二要素認証はすでに設定済みです。');
        }

        // 秘密鍵が未生成の場合は新規生成
        if (empty($user->two_factor_secret)) {
            $secret = $this->twoFactorService->generateSecretKey();
            $user->update(['two_factor_secret' => $secret]);
        } else {
            $secret = $user->two_factor_secret;
        }

        // QR コード用の OTPAuth URL 生成
        $qrUrl = 'otpauth://totp/あんしん:'.$user->email.'?secret='.$secret.'&issuer=%E3%82%A2%E3%83%8D%E3%82%B7%E3%83%B3';

        return view('auth.2fa-setup', [
            'secret' => $secret,
            'qrUrl' => $qrUrl,
            'user' => $user,
        ]);
    }

    /**
     * 2FA セットアップを確定（TOTP コード検証後、two_factor_confirmed_at を設定）
     */
    public function confirm(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        if (empty($user->two_factor_secret)) {
            return back()->withErrors(['code' => '2FA 秘密鍵が設定されていません。もう一度セットアップをやり直してください。']);
        }

        if (! $this->twoFactorService->verifyCode($user->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => '認証コードが正しくありません。再度入力してください。']);
        }

        // リカバリーコードを生成（設定時に一度だけ表示）
        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();
        $user->update([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ]);

        Log::info('2FA setup completed', ['user_id' => $user->id]);

        return redirect()->route('2fa.verify')
            ->with('recovery_codes', $recoveryCodes)
            ->with('status', '二要素認証の設定が完了しました。リカバリーコードを保存してください。');
    }
}
