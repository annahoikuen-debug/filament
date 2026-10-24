<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    /**
     * ミドルウェアを直接実行してレスポンスヘッダーを検証するヘルパー
     */
    private function runMiddleware(string $environment, bool $secure = false): Response
    {
        app()->detectEnvironment(fn () => $environment);

        $request = Request::create($secure ? 'https://example.com/admin' : 'http://example.com/admin');
        if ($secure) {
            $request->server->set('HTTPS', 'on');
        }

        $middleware = new SecurityHeaders;

        return $middleware->handle($request, fn () => new Response('OK'));
    }

    // ---- Unit: CSP ビルダー検証 ----

    /** @test */
    public function build_production_csp_は必要なディレクティブを含む()
    {
        $middleware = new SecurityHeaders;
        $method = new \ReflectionMethod($middleware, 'buildProductionCsp');
        $method->setAccessible(true);
        $csp = $method->invoke($middleware);

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('script-src', $csp);
        $this->assertStringContainsString('style-src', $csp);
        $this->assertStringContainsString('font-src', $csp);
        $this->assertStringContainsString('img-src', $csp);
        $this->assertStringContainsString("frame-src 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    /** @test */
    public function build_local_csp_は開発用緩和設定を含む()
    {
        $middleware = new SecurityHeaders;
        $method = new \ReflectionMethod($middleware, 'buildLocalCsp');
        $method->setAccessible(true);
        $csp = $method->invoke($middleware);

        $this->assertStringContainsString('http://localhost:*', $csp);
        $this->assertStringContainsString('ws://localhost:*', $csp);
        $this->assertStringContainsString("frame-src 'self'", $csp);
    }

    // ---- Feature: ヘッダー存在確認 ----

    /** @test */
    public function レスポンスに全セキュリティヘッダーが設定される()
    {
        $response = $this->runMiddleware('production');

        $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('geolocation=()', $response->headers->get('Permissions-Policy'));
        $this->assertEquals('same-origin', $response->headers->get('Cross-Origin-Opener-Policy'));
        $this->assertEquals('same-origin', $response->headers->get('Cross-Origin-Resource-Policy'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
    }

    /** @test */
    public function 本番環境では_strict_csp_が適用される()
    {
        $response = $this->runMiddleware('production');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-src 'none'", $csp);
        $this->assertStringNotContainsString('localhost', $csp);
    }

    /** @test */
    public function 開発環境では緩和された_csp_が適用される()
    {
        $response = $this->runMiddleware('local');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('http://localhost:*', $csp);
    }

    /** @test */
    public function 本番環境で_https_の場合_hsts_ヘッダーが設定される()
    {
        $response = $this->runMiddleware('production', secure: true);

        $hsts = $response->headers->get('Strict-Transport-Security');
        $this->assertNotNull($hsts);
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
    }

    /** @test */
    public function 本番環境で_http_の場合_hsts_ヘッダーは設定されない()
    {
        $response = $this->runMiddleware('production', secure: false);

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    /** @test */
    public function 開発環境では_hsts_ヘッダーは設定されない()
    {
        $response = $this->runMiddleware('local', secure: true);

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    /** @test */
    public function 除外パスではヘッダーが適用されない()
    {
        $request = Request::create('http://example.com/chatbot/widget.js');
        $middleware = new SecurityHeaders;

        $response = $middleware->handle($request, fn () => new Response('OK'));

        $this->assertNull($response->headers->get('X-Frame-Options'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    /** @test */
    public function ヘルスチェックパス_up_ではヘッダーが適用されない()
    {
        $request = Request::create('http://example.com/up');
        $middleware = new SecurityHeaders;

        $response = $middleware->handle($request, fn () => new Response('OK'));

        $this->assertNull($response->headers->get('X-Frame-Options'));
    }
}
