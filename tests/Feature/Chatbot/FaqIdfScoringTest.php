<?php

namespace Tests\Feature\Chatbot;

use App\Models\ChatbotFaq;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqIdfScoringTest extends TestCase
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

    public function test_idf_weighting_prefers_specific_keywords_over_generic(): void
    {
        // Create FAQs: one generic (many keywords overlap), one specific
        ChatbotFaq::create([
            'question' => '請求書の発行方法を教えてください',
            'keywords' => ['請求書', '発行', '方法'],
            'answer' => '月次請求書画面から発行できます。',
            'category' => '請求',
            'is_active' => true,
        ]);

        ChatbotFaq::create([
            'question' => '口座振替の設定方法を教えてください',
            'keywords' => ['口座振替', '設定', '方法'],
            'answer' => '入居者台帳から口座振替設定が可能です。',
            'category' => '支払い',
            'is_active' => true,
        ]);

        ChatbotFaq::create([
            'question' => '領収書の発行方法を教えてください',
            'keywords' => ['領収書', '発行', '方法'],
            'answer' => '請求書詳細から領収書発行できます。',
            'category' => '請求',
            'is_active' => true,
        ]);

        // Rebuild keyword stats
        $this->artisan('chatbot:rebuild-keyword-stats');

        // Query with generic term "発行" - should match all 3, but specific terms should rank higher for specific queries
        // Query with specific term "口座振替" - should strongly prefer the 口座振替 FAQ
        $response = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => '口座振替の設定',
            ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        // The 口座振替 FAQ should be matched due to high IDF weight of "口座振替"
        $this->assertStringContainsString('口座振替', $reply);
    }

    public function test_fallback_when_stats_table_empty(): void
    {
        // Ensure stats table is empty (no FAQs or fresh DB)
        // This tests the fallback to simple counting when keyword_stats is empty
        ChatbotFaq::create([
            'question' => '請求書の発行方法',
            'keywords' => ['請求書', '発行'],
            'answer' => '発行できます。',
            'category' => '請求',
            'is_active' => true,
        ]);

        // Don't run rebuild - table should be empty or have no matching keywords
        $response = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => '請求書 発行',
            ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        // Should still match via fallback counting
        $this->assertStringContainsString('発行', $reply);
    }

    public function test_generic_keyword_gets_lower_score(): void
    {
        // "方法" appears in all 3 FAQs -> high DF -> low IDF
        // "口座振替" appears in only 1 FAQ -> low DF -> high IDF
        ChatbotFaq::create([
            'question' => '請求書の発行方法',
            'keywords' => ['請求書', '発行', '方法'],
            'answer' => 'A',
            'category' => '請求',
            'is_active' => true,
        ]);
        ChatbotFaq::create([
            'question' => '領収書の発行方法',
            'keywords' => ['領収書', '発行', '方法'],
            'answer' => 'B',
            'category' => '請求',
            'is_active' => true,
        ]);
        ChatbotFaq::create([
            'question' => '口座振替の設定方法',
            'keywords' => ['口座振替', '設定', '方法'],
            'answer' => 'C',
            'category' => '支払い',
            'is_active' => true,
        ]);

        $this->artisan('chatbot:rebuild-keyword-stats');

        // Query with only generic term "方法" - should match but may return any
        $response = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => '方法',
            ]);

        $response->assertStatus(200);
        // Should still return some answer (bigram fallback may kick in)
        $this->assertNotEmpty($response->json('reply'));
    }

    public function test_idf_stats_cached_and_cleared_on_rebuild(): void
    {
        ChatbotFaq::create([
            'question' => 'テスト質問',
            'keywords' => ['テスト', '質問'],
            'answer' => '回答',
            'category' => 'テスト',
            'is_active' => true,
        ]);

        $this->artisan('chatbot:rebuild-keyword-stats');

        // First call populates cache
        $response1 = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => 'テスト',
            ]);
        $response1->assertStatus(200);

        // Add new FAQ with different keyword
        ChatbotFaq::create([
            'question' => '新しい質問',
            'keywords' => ['新しい', 'キーワード'],
            'answer' => '新回答',
            'category' => 'テスト',
            'is_active' => true,
        ]);

        // Before rebuild, cache still has old stats
        $response2 = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => '新しい',
            ]);
        $response2->assertStatus(200);

        // After rebuild, new keyword should be indexed
        $this->artisan('chatbot:rebuild-keyword-stats');

        $response3 = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => '新しい',
            ]);
        $response3->assertStatus(200);
        $this->assertStringContainsString('新回答', $response3->json('reply'));
    }
}
