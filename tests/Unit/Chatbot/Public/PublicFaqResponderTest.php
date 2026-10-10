<?php

namespace Tests\Unit\Chatbot\Public;

use App\Models\ChatbotFaq;
use App\Services\Chatbot\Public\PublicFaqResponder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFaqResponderTest extends TestCase
{
    use RefreshDatabase;

    private PublicFaqResponder $responder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->responder = new PublicFaqResponder;
    }

    public function test_returns_public_faqs_by_keyword_match_in_score_order(): void
    {
        ChatbotFaq::factory()->create([
            'question' => '料金プランについて教えてください',
            'keywords' => ['料金'],
            'is_public' => true,
        ]);
        ChatbotFaq::factory()->create([
            'question' => 'お支払い方法について',
            'keywords' => ['支払い'],
            'is_public' => true,
        ]);

        $results = $this->responder->searchPublic('料金を教えてください');

        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertSame('料金プランについて教えてください', $results->first()->question);
    }

    public function test_internal_only_faqs_are_not_returned(): void
    {
        ChatbotFaq::factory()->create([
            'question' => '入居者の支払い状況',
            'keywords' => ['料金', '支払い'],
            'is_public' => false,
        ]);

        $results = $this->responder->searchPublic('料金はいくらですか');

        $this->assertCount(0, $results);
    }

    public function test_bigram_fallback_works_when_no_keyword_match(): void
    {
        // キーワード登録なし・質問文だけの公開FAQ
        ChatbotFaq::factory()->create([
            'question' => '月額料金の料金プランはいくらですか',
            'keywords' => [],
            'is_public' => true,
        ]);

        $results = $this->responder->searchPublic('料金プランはいくら？');

        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_returns_empty_collection_when_no_match(): void
    {
        ChatbotFaq::factory()->create([
            'question' => '全く関係のない質問です',
            'keywords' => ['無関係'],
            'is_public' => true,
        ]);

        $results = $this->responder->searchPublic('宇宙船の運賃はいくらですか');

        $this->assertCount(0, $results);
    }
}
