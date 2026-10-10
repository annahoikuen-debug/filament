<?php

namespace App\Services\Chatbot\Public;

use App\Models\ChatbotFaq;
use App\Services\Chatbot\BigramTokenizer;
use Illuminate\Support\Collection;

/**
 * 公開FAQ検索サービス（is_public=true のFAQのみ対象・施設スコープなし）
 *
 * 既存 FaqResponder のスコアリングロジック（キーワード一致→Bi-gram Jaccard
 * フォールバック）を公開スコープ向けに再実装したもの。既存 FaqResponder は
 * 一行も変更しない。
 */
class PublicFaqResponder
{
    public function __construct(
        private readonly ?BigramTokenizer $tokenizer = null,
    ) {}

    /**
     * 公開FAQを検索する
     *
     * @return Collection<int, ChatbotFaq>
     */
    public function searchPublic(string $query): Collection
    {
        $faqs = ChatbotFaq::query()
            ->where('is_public', true)
            ->where('is_active', true)
            ->get();

        if ($faqs->isEmpty()) {
            return collect();
        }

        $keywords = $this->extractKeywords($query);

        // 1. キーワード一致によるスコアリング
        if ($keywords !== []) {
            $results = $faqs
                ->map(fn (ChatbotFaq $faq) => [
                    'faq' => $faq,
                    'score' => $this->score($faq, $keywords),
                ])
                ->filter(fn (array $item) => $item['score'] > 0)
                ->sortByDesc(fn (array $item) => $item['score'])
                ->values()
                ->map(fn (array $item) => $item['faq']);

            if ($results->isNotEmpty()) {
                return $results;
            }
        }

        // 2. キーワード未一致時の Bi-gram Jaccard類似度フォールバック
        $tokenizer = $this->tokenizer ?? new BigramTokenizer;
        $threshold = (float) config('chatbot.faq.bigram_threshold', 0.35);

        return $faqs
            ->map(fn (ChatbotFaq $faq) => [
                'faq' => $faq,
                'score' => $tokenizer->similarity($query, $faq->question),
            ])
            ->filter(fn (array $item) => $item['score'] >= $threshold)
            ->sortByDesc(fn (array $item) => $item['score'])
            ->values()
            ->map(fn (array $item) => $item['faq']);
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function score(ChatbotFaq $faq, array $keywords): int
    {
        $faqKeywords = $faq->keywords ?? [];
        $score = 0;

        foreach ($keywords as $keyword) {
            foreach ($faqKeywords as $faqKeyword) {
                if (mb_strpos($faqKeyword, $keyword) !== false || mb_strpos($keyword, $faqKeyword) !== false) {
                    $score++;
                    break;
                }
            }
        }

        if (mb_strpos($faq->question, $keywords[0]) !== false) {
            $score += 2;
        }

        return $score;
    }

    /**
     * @return array<int, string>
     */
    private function extractKeywords(string $query): array
    {
        $query = preg_replace('/[？?。.!！｡\s]+/u', ' ', $query);
        $query = preg_replace('/(の|は|が|を|で|に|です|ます|か|について|どう|なん|何|如何)/u', ' ', (string) $query);

        $words = array_filter(array_map('trim', explode(' ', (string) $query)), fn ($w) => mb_strlen($w) >= 2);

        return array_values(array_unique($words));
    }
}
