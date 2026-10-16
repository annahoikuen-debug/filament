<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotFaq;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FaqResponder
{
    public function __construct(
        private readonly ?BigramTokenizer $tokenizer = null,
    ) {}

    /**
     * Load keyword document frequency stats from cache/DB.
     * Returns empty array if table is empty (fallback mode).
     *
     * @return array<string, int> keyword => document_frequency
     */
    private function loadKeywordStats(): array
    {
        return Cache::remember('chatbot_keyword_stats', 3600, function () {
            return DB::table('chatbot_keyword_stats')
                ->pluck('document_frequency', 'keyword')
                ->all();
        });
    }

    /**
     * キーワード一致および Bi-gram 類似度スコアリングでFAQを検索する
     *
     * @return Collection<int, ChatbotFaq>
     */
    public function search(string $query, ?int $facilityId): Collection
    {
        $keywords = $this->extractKeywords($query);

        // 1. 既存のキーワード一致によるスコアリング
        $results = collect();
        if ($keywords !== []) {
            $results = ChatbotFaq::query()
                ->where('is_active', true)
                ->where(fn ($q) => $this->applyFacilityScope($q, $facilityId))
                ->get()
                ->map(fn (ChatbotFaq $faq) => [
                    'faq' => $faq,
                    'score' => $this->score($faq, $keywords),
                ])
                ->filter(fn (array $item) => $item['score'] > 0)
                ->sortByDesc(fn (array $item) => $item['score'])
                ->values()
                ->map(fn (array $item) => $item['faq']);
        }

        if ($results->isNotEmpty()) {
            return $results;
        }

        // 2. キーワード未一致時の Bi-gram Jaccard類似度フォールバック
        $tokenizer = $this->tokenizer ?? new BigramTokenizer;
        $threshold = (float) config('chatbot.faq.bigram_threshold', 0.35);

        return $this->bigramSearch($query, $facilityId, $tokenizer, $threshold);
    }

    /**
     * 「もしかして」候補用の低閾値検索（上位 $limit 件）
     *
     * @return Collection<int, ChatbotFaq>
     */
    public function searchLoose(string $query, ?int $facilityId, int $limit = 2): Collection
    {
        $keywords = $this->extractKeywords($query);

        // キーワード一致（スコア > 0）を優先
        if ($keywords !== []) {
            $results = ChatbotFaq::query()
                ->where('is_active', true)
                ->where(fn ($q) => $this->applyFacilityScope($q, $facilityId))
                ->get()
                ->map(fn (ChatbotFaq $faq) => [
                    'faq' => $faq,
                    'score' => $this->score($faq, $keywords),
                ])
                ->filter(fn (array $item) => $item['score'] > 0)
                ->sortByDesc(fn (array $item) => $item['score'])
                ->values();

            if ($results->isNotEmpty()) {
                return $results->take($limit)->map(fn (array $item) => $item['faq'])->values();
            }
        }

        // 低閾値 Bi-gram 検索
        $tokenizer = $this->tokenizer ?? new BigramTokenizer;
        $looseThreshold = (float) config('chatbot.faq.loose_threshold', 0.2);

        return $this->bigramSearch($query, $facilityId, $tokenizer, $looseThreshold)->take($limit)->values();
    }

    /**
     * @return Collection<int, ChatbotFaq>
     */
    private function bigramSearch(string $query, ?int $facilityId, BigramTokenizer $tokenizer, float $threshold): Collection
    {
        return ChatbotFaq::query()
            ->where('is_active', true)
            ->where(fn ($q) => $this->applyFacilityScope($q, $facilityId))
            ->get()
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

        // Load keyword stats for IDF weighting (cached, falls back to empty array)
        $keywordStats = $this->loadKeywordStats();
        $totalFaqCount = ChatbotFaq::query()->where('is_active', true)->count();

        foreach ($keywords as $keyword) {
            foreach ($faqKeywords as $faqKeyword) {
                if (mb_strpos($faqKeyword, $keyword) !== false || mb_strpos($keyword, $faqKeyword) !== false) {
                    // IDF weighting: if stats table has data, use IDF; else fallback to simple count
                    if ($keywordStats !== [] && isset($keywordStats[$keyword]) && $totalFaqCount > 0) {
                        $df = $keywordStats[$keyword];
                        // Smoothed IDF: log((N + 1) / (df + 1)) + 1
                        $idf = log((1 + $totalFaqCount) / (1 + $df)) + 1;
                        $score += (int) round($idf * 2);
                    } else {
                        // Fallback: simple count (original behavior)
                        $score++;
                    }
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

    /**
     * @param  Builder<ChatbotFaq>  $query
     * @return Builder<ChatbotFaq>
     */
    private function applyFacilityScope($query, ?int $facilityId)
    {
        if ($facilityId === null) {
            return $query->whereNull('facility_id');
        }

        return $query->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', $facilityId));
    }
}
