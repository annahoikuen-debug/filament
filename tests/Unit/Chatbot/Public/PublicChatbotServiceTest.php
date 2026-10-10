<?php

namespace Tests\Unit\Chatbot\Public;

use App\Models\ChatbotFaq;
use App\Models\ChatLog;
use App\Models\ChatEscalation;
use App\Models\Resident;
use App\Services\Chatbot\Public\DTO\PublicChatRequest;
use App\Services\Chatbot\Public\PublicChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicChatbotServiceTest extends TestCase
{
    use RefreshDatabase;

    private PublicChatbotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PublicChatbotService::class);
    }

    public function test_pricing_question_returns_faq_or_notice_with_action_links(): void
    {
        ChatbotFaq::factory()->create([
            'question' => '料金プランはいくらですか',
            'keywords' => ['料金'],
            'answer' => '料金プランは月額9,800円からです。',
            'is_public' => true,
        ]);

        $response = $this->service->handle(new PublicChatRequest(message: '料金はいくら？', visitorId: fake()->uuid()));

        $this->assertSame('pricing', $response->intent);
        $this->assertSame('料金プランは月額9,800円からです。', $response->reply);
        $this->assertTrue($response->faqMatched);

        // FAQ未登録の場合の確認
        $response2 = $this->service->handle(new PublicChatRequest(message: '特徴は何？', visitorId: fake()->uuid()));
        $this->assertNotSame('', $response2->reply);
    }

    public function test_demo_request_returns_lead_capture_with_form(): void
    {
        $visitorId = fake()->uuid();

        $response = $this->service->handle(new PublicChatRequest(message: 'デモを体験したい', visitorId: $visitorId));

        $this->assertSame('lead_capture', $response->intent);
        $this->assertNotNull($response->form);
        $this->assertSame('/api/site-forms/demo', $response->form['endpoint']);
        $this->assertCount(3, $response->form['fields']);
    }

    public function test_escalate_returns_contact_info_without_internal_escalation_record(): void
    {
        $visitorId = fake()->uuid();

        $response = $this->service->handle(new PublicChatRequest(message: '問い合わせたいことがあります', visitorId: $visitorId));

        $this->assertNotSame('', $response->reply);
        $this->assertSame(0, ChatEscalation::count(), '内部エスカレーションレコードを作成してはならない');
    }

    public function test_fallback_returns_quick_replies(): void
    {
        $visitorId = fake()->uuid();

        $response = $this->service->handle(new PublicChatRequest(message: 'こんにちは', visitorId: $visitorId));

        $this->assertSame('faq', $response->intent);
        $this->assertNotEmpty($response->quickReplies);
    }

    public function test_response_contains_no_pii(): void
    {
        $visitorId = fake()->uuid();

        // PIIデータを作成しておく（参照されないことを確認）
        Resident::factory()->create([
            'name' => '山田太郎',
            'room_number' => '101',
        ]);

        foreach (['料金はいくら？', '機能は？', 'デモしたい', '資料ください', 'こんにちは'] as $msg) {
            $response = $this->service->handle(new PublicChatRequest(message: $msg, visitorId: $visitorId));

            $this->assertStringNotContainsString('山田太郎', $response->reply);
            $this->assertStringNotContainsString('101', $response->reply);
            $encoded = json_encode($response->toArray(), JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString('山田太郎', $encoded);
        }
    }

    public function test_log_records_channel_public_and_visitor_id(): void
    {
        $visitorId = fake()->uuid();

        $response = $this->service->handle(new PublicChatRequest(message: '料金はいくら？', visitorId: $visitorId));

        $this->assertNotNull($response->chatLogId);

        $log = ChatLog::find($response->chatLogId);
        $this->assertNotNull($log);
        $this->assertSame('public', $log->channel);
        $this->assertSame($visitorId, $log->visitor_id);
    }
}
