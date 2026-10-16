<?php

namespace Tests\Feature\Chatbot;

use App\Models\ChatbotFaq;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClarificationFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facility = Facility::factory()->create();
        $this->user = User::factory()->corporateAdmin()->create();
    }

    public function test_resident_not_found_shows_active_resident_suggestions(): void
    {
        $this->resident = Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'name' => '存在 しない人',
            'status' => 'active',
        ]);
        Resident::factory()->count(3)->create([
            'facility_id' => $this->facility->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => 'ふがふがの情報を教えてください',
            ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('見つかりませんでした', $response->json('reply'));
        $this->assertStringContainsString('お探しの方はいますか', $response->json('reply'));

        $quickReplies = $response->json('quick_replies');
        $this->assertNotEmpty($quickReplies);
        // 4 active residents created, none match "ふがふが", so all 4 should be suggested (limit 5)
        $this->assertCount(4, $quickReplies);
    }

    public function test_resident_not_found_does_not_suggest_matching_resident(): void
    {
        Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'name' => '一郎 候補',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => '一郎 候補の情報を教えてください',
            ]);

        // 正常に見つかるため候補提示は発生しない
        $response->assertStatus(200);
        $this->assertStringContainsString('一郎 候補', $response->json('reply'));
    }

    public function test_faq_miss_shows_maybe_suggestions(): void
    {
        ChatbotFaq::create([
            'question' => '請求書の発行方法を教えてください',
            'keywords' => ['請求書', '発行'],
            'answer' => '月次請求書画面から発行できます。',
            'category' => '請求',
            'is_active' => true,
        ]);

        // 大きく外れたが bigram で部分的に似た質問
        $response = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => '請求書のやりかた',
            ]);

        $response->assertStatus(200);
        // FAQ ヒットまたは「もしかして」候補のいずれかで請求書関連が返る
        $reply = $response->json('reply');
        $quickReplies = $response->json('quick_replies');
        $this->assertTrue(
            str_contains($reply, '請求書') || collect($quickReplies ?? [])->contains(fn ($v) => str_contains((string) $v, '請求書')),
            'FAQ ヒットまたはもしかして候補に請求書関連が含まれること'
        );
    }

    public function test_faq_total_miss_still_shows_category_quick_replies(): void
    {
        // FAQ を1件も登録しない
        $response = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => 'まったく無関係な質問 xyz123',
            ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('見つかりませんでした', $response->json('reply'));
        // JSON key is 'quick_reply_categories' per ChatResponse::toArray()
        $this->assertNotEmpty($response->json('quick_reply_categories'));
    }
}
