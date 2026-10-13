<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * ヘッダー適用除外パス（静的配信・ヘルスチェック等）
     */
    private array $exceptPaths = [
        'chatbot/widget.js',
        'up',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (in_array($request->path(), $this->exceptPaths, true)) {
            return $response;
        }

        $isProduction = app()->environment('production');

        // 共通ヘッダー
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // CSP: 環境別設定
        $response->headers->set('Content-Security-Policy', $isProduction
            ? $this->buildProductionCsp()
            : $this->buildLocalCsp());

        // HSTS（本番のみ・HTTPS 前提）
        if ($isProduction && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }

    private function buildProductionCsp(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // Filament/Livewire 用
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        return implode('; ', $directives);
    }

    private function buildLocalCsp(): string
    {
        // 開発用: より緩やか（Vite HMR 等対応）
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:* ws://localhost:*",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https: http:",
            "connect-src 'self' http://localhost:* ws://localhost:*",
            "frame-src 'self'",
        ];

        return implode('; ', $directives);
    }
}
