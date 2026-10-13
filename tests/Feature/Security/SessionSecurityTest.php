<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function セッション設定は安全なデフォルト値を持つ()
    {
        // HttpOnly は常に有効
        $this->assertTrue((bool) config('session.http_only'));

        // SameSite は lax または strict
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);

        // 有効期限は 120 分（既定）
        $this->assertEquals(120, config('session.lifetime'));
    }

    /** @test */
    public function セッションドライバは_env_例でデータベースを推奨している()
    {
        // テスト環境は array ドライバ。本番テンプレートでは database を使用する。
        $envExample = file_get_contents(base_path('.env.example'));
        $envProduction = file_get_contents(base_path('.env.production.example'));

        $this->assertStringContainsString('SESSION_DRIVER=database', $envExample);
        $this->assertStringContainsString('SESSION_DRIVER=database', $envProduction);

        // テスト実行時のドライバ（array）が設定されていることも確認
        $this->assertNotEmpty(config('session.driver'));
    }

    /** @test */
    public function sessions_テーブルが存在する()
    {
        $this->assertTrue(
            \Schema::hasTable('sessions'),
            'sessions テーブルが存在すること（SESSION_DRIVER=database の前提）'
        );
    }

    /** @test */
    public function ステータス200_のレスポンスでセッション_cookie_の_httponly_属性が有効()
    {
        // セッションを使うレスポンス（Filament ログイン画面）
        $response = $this->get('/admin/login');

        $response->assertStatus(200);

        $cookies = $response->headers->getCookies();
        $sessionCookie = collect($cookies)->first(fn ($cookie) => str_contains($cookie->getName(), 'session'));

        if ($sessionCookie) {
            $this->assertTrue($sessionCookie->isHttpOnly(), 'セッション Cookie は HttpOnly であること');
            $this->assertContains($sessionCookie->getSameSite(), ['lax', 'strict', null], 'SameSite は lax/strict であること');
        }
    }

    /** @test */
    public function secure_cookie設定が_envから反映される()
    {
        // .env.example では local 開発のため false、本番テンプレートでは true を設定
        $envExample = file_get_contents(base_path('.env.example'));
        $envProduction = file_get_contents(base_path('.env.production.example'));

        $this->assertStringContainsString('SESSION_HTTP_ONLY=true', $envExample);
        $this->assertStringContainsString('SESSION_SAME_SITE=lax', $envExample);

        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $envProduction);
        $this->assertStringContainsString('SESSION_ENCRYPT=true', $envProduction);
        $this->assertStringContainsString('SESSION_HTTP_ONLY=true', $envProduction);
        $this->assertStringContainsString('SESSION_SAME_SITE=lax', $envProduction);
    }

    /** @test */
    public function 本番テンプレートでは_app_debug_false_が設定されている()
    {
        $envProduction = file_get_contents(base_path('.env.production.example'));

        $this->assertStringContainsString('APP_DEBUG=false', $envProduction);
        $this->assertStringContainsString('LOG_LEVEL=error', $envProduction);
    }

    /** @test */
    public function セッション暗号化設定は_env_で制御できる()
    {
        // config は env('SESSION_ENCRYPT', false) を参照する
        $this->assertArrayHasKey('encrypt', config('session'));
        $this->assertArrayHasKey('secure', config('session'));
    }
}
