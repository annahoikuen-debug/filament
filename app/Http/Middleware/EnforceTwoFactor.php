<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 二要素認証 (2FA) 強制ミドルウェア。
 *
 * 2FA 設定済み・検証済みでないユーザーを 2FA 設定/検証ページへリダイレクトする。
 * 2FA 関連ルート自体は除外して無限リダイレクトを防ぐ。
 */
class EnforceTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // 未認証ユーザーはスキップ (Authenticate ミドルウェアで処理)
        if (! $user) {
            return $next($request);
        }

        // 2FA 強制モードが off の場合は何もしない
        if (config('two_factor.mode', 'off') === 'off') {
            return $next($request);
        }

        // 強制対象外のユーザー (admins モードで非管理者など)
        if (! $this->shouldEnforce($user)) {
            return $next($request);
        }

        // 2FA 関連ルートは除外
        if ($request->routeIs('2fa.*')) {
            return $next($request);
        }

        // 2FA 未設定の場合はセットアップページへ
        if (! $user->two_factor_confirmed_at) {
            return redirect()->route('2fa.setup')
                ->with('status', 'セキュリティ強化のため、二要素認証 (2FA) の設定を完了してください。');
        }

        // セッションで現在のリクエストが 2FA 検証済みか確認
        if (! $request->session()->get('2fa_verified', false)) {
            return redirect()->route('2fa.verify')
                ->with('status', '二要素認証コードを入力してください。');
        }

        return $next($request);
    }

    /**
     * 2FA 強制の対象ユーザーか判定する。
     */
    private function shouldEnforce($user): bool
    {
        $mode = config('two_factor.mode', 'off');

        return match ($mode) {
            'admins' => (bool) $user->is_admin,
            'all' => true,
            default => false,
        };
    }
}
