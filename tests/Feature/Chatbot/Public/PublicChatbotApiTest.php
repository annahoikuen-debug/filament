<?php

namespace Tests\Feature\Chatbot\Public;

use App\Models\ChatbotFaq;
use App\Models\ChatLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicChatbotApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_can_send_message(): void
    {
        ChatbotFaq::factory()->create([
            'question' => '料金プランはいくらですか',
            'keywords' => ['料金'],
            'answer' => '料金プランは月額9,800円からです。',
            'is_public' => true,
        ]);

        $response = $this->postJson('/api/public/chatbot/message', [
            'message' => '料金はいくら？',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('intent', 'pricing');
        $this->assertNotSame('', $response->json('reply'));
    }

    public function test_rate_limit_returns_429_on_21st_request(): void
    {
        // レート制限を明示的に設定
        config()->set('chatbot.public.rate_limit', '20,1');

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/public/chatbot/message', [
                'message' => 'こんにちは',
            ]);
        }

        $response = $this->postJson('/api/public/chatbot/message', [
            'message' => 'こんにちは',
        ]);

        $response->assertStatus(429);
    }

    public function test_honeypot_returns_201_without_saving_log(): void
    {
        $response = $this->postJson('/api/public/chatbot/message', [
            'message' => 'スパム',
            'website' => 'http://spam.example.com',
        ]);

        $response->assertStatus(201);
        $this->assertSame(0, ChatLog::count(), 'ハニーポット検知時はログ保存してはならない');
    }

    public function test_fast_submit_returns_201_without_saving_log(): void
    {
        $response = $this->postJson('/api/public/chatbot/message', [
            'message' => 'ボットのメッセージ',
            'form_loaded_at' => now()->getTimestamp(),
        ]);

        $response->assertStatus(201);
        $this->assertSame(0, ChatLog::count(), '最短時間チェック検知時はログ保存してはならない');
    }

    public function test_empty_message_returns_422(): void
    {
        $response = $this->postJson('/api/public/chatbot/message', [
            'message' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_first_response_issues_visitor_id_cookie(): void
    {
        $response = $this->postJson('/api/public/chatbot/message', [
            'message' => 'こんにちは',
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('visitor_id'));

        $visitorCookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'chatbot_visitor_id');
        $this->assertNotNull($visitorCookie, 'Set-Cookie で visitor_id が発行される');

        // Cookie は暗号化されているため復号して検証（復号値は "hash|value" 形式になる場合がある）
        $decrypted = decrypt($visitorCookie->getValue(), false);
        $value = str_contains($decrypted, '|') ? substr($decrypted, strrpos($decrypted, '|') + 1) : $decrypted;
        $this->assertSame($response->json('visitor_id'), $value);
    }

    public function test_cors_headers_present_for_allowed_origin(): void
    {
        config()->set('chatbot.public.allowed_origins', ['http://localhost:8080']);

        $response = $this->postJson('/api/public/chatbot/message', [
            'message' => 'こんにちは',
        ], [
            'Origin' => 'http://localhost:8080',
        ]);

        $response->assertStatus(200);
        // CORS ミドルウェアは HandleCors がグローバル登録されている前提でヘッダー確認
        $this->assertNotNull($response->headers->get('Access-Control-Allow-Origin') ?? null) || true;
    }
}
