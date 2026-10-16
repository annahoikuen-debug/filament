<?php

namespace Tests\Feature\Chatbot;

use App\Models\ChatbotFaq;
use App\Models\ChatbotFaqSuggestion;
use App\Models\ChatLog;
use App\Models\Facility;
use App\Models\User;
use App\Services\Chatbot\FaqSuggestionClusterer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqSuggestionClusteringTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facility = Facility::factory()->create(['name' => 'テスト施設']);
    }

    private function createMissLog(string $message, ?int $daysAgo = 1): ChatLog
    {
        return ChatLog::create([
            'user_id' => User::factory()->create(['facility_id' => $this->facility->id])->id,
            'session_id' => 'test-session-'.uniqid(),
            'user_message' => $message,
            'intent' => 'faq',
            'bot_reply' => '回答が見つかりませんでした',
            'faq_matched' => false,
            'facility_id' => $this->facility->id,
            'created_at' => now()->subDays($daysAgo),
        ]);
    }

    /** @test */
    public function similar_messages_form_one_cluster_with_correct_count(): void
    {
        // Bi-gram Jaccard 0.5 以上になる類似文を使用
        $this->createMissLog('領収書の再発行はできますか');
        $this->createMissLog('領収書の再発行はできますか？');
        $this->createMissLog('領収書の再発行は出来ますか');

        $clusterer = app(FaqSuggestionClusterer::class);
        $upserted = $clusterer->generate(30);

        $this->assertGreaterThanOrEqual(1, $upserted);

        $suggestion = ChatbotFaqSuggestion::query()
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($suggestion);
        $this->assertEquals(3, $suggestion->occurrence_count);
    }

    /** @test */
    public function dissimilar_messages_form_separate_clusters(): void
    {
        $this->createMissLog('領収書の再発行はできますか');
        $this->createMissLog('駐車場はありますか');
        $this->createMissLog('面会時間を教えてください');

        $clusterer = app(FaqSuggestionClusterer::class);
        $clusterer->generate(30);

        // 3件とも別クラスタで出現回数1 → 閾値2未満のため登録なし
        $this->assertEquals(0, ChatbotFaqSuggestion::count());
    }

    /** @test */
    public function single_occurrence_cluster_is_not_registered(): void
    {
        $this->createMissLog('ユニークな質問 xyz その1');

        $clusterer = app(FaqSuggestionClusterer::class);
        $clusterer->generate(30);

        $this->assertEquals(0, ChatbotFaqSuggestion::count());
    }

    /** @test */
    public function approve_flow_creates_faq_and_marks_approved(): void
    {
        $this->createMissLog('領収書の再発行はできますか');
        $this->createMissLog('領収書の再発行はできますか？');

        $clusterer = app(FaqSuggestionClusterer::class);
        $clusterer->generate(30);

        $suggestion = ChatbotFaqSuggestion::query()->where('status', 'pending')->first();
        $this->assertNotNull($suggestion);

        // 承認フロー（Filamentアクション相当）
        $faq = ChatbotFaq::create([
            'question' => $suggestion->representative_message,
            'answer' => 'マイページから再発行できます。',
            'category' => '自動提案',
            'facility_id' => $suggestion->facility_id,
            'is_active' => true,
            'is_public' => false,
        ]);

        $suggestion->update([
            'status' => 'approved',
            'approved_faq_id' => $faq->id,
        ]);

        $this->assertEquals('approved', $suggestion->fresh()->status);
        $this->assertEquals($faq->id, $suggestion->fresh()->approved_faq_id);
        $this->assertDatabaseHas('chatbot_faqs', ['id' => $faq->id]);
    }

    /** @test */
    public function batch_is_idempotent_no_duplicate_upsert(): void
    {
        $this->createMissLog('領収書の再発行はできますか');
        $this->createMissLog('領収書の再発行はできますか？');

        $clusterer = app(FaqSuggestionClusterer::class);
        $clusterer->generate(30);
        $first = ChatbotFaqSuggestion::count();

        // 再実行（冪等性）
        $clusterer->generate(30);
        $second = ChatbotFaqSuggestion::count();

        $this->assertEquals($first, $second);
        $this->assertEquals(1, $second);
    }

    /** @test */
    public function dismissed_cluster_is_not_reproposed_but_count_updated(): void
    {
        $this->createMissLog('領収書の再発行はできますか');
        $this->createMissLog('領収書の再発行はできますか？');

        $clusterer = app(FaqSuggestionClusterer::class);
        $clusterer->generate(30);

        $suggestion = ChatbotFaqSuggestion::query()->first();
        $suggestion->update(['status' => 'dismissed']);

        // 新たに同クラスタの質問が増えて再実行
        $this->createMissLog('領収書の再発行はできますでしょうか');
        $clusterer->generate(30);

        $fresh = $suggestion->fresh();
        $this->assertEquals('dismissed', $fresh->status);
        $this->assertEquals(3, $fresh->occurrence_count);
    }

    /** @test */
    public function artisan_command_with_suggest_option_generates_suggestions(): void
    {
        $this->createMissLog('領収書の再発行はできますか');
        $this->createMissLog('領収書の再発行はできますか？');

        $this->artisan('chatbot:analyze-faq-misses', ['--days' => 30, '--suggest' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('chatbot_faq_suggestions', [
            'status' => 'pending',
        ]);
    }
}
