<?php

namespace App\Console\Commands;

use App\Models\ChatbotFaq;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RebuildKeywordStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chatbot:rebuild-keyword-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild keyword document frequency stats for FAQ IDF scoring';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Rebuilding keyword document frequency stats...');

        // Get all active FAQs with their questions and keywords
        $faqs = ChatbotFaq::query()
            ->where('is_active', true)
            ->get(['question', 'keywords']);

        if ($faqs->isEmpty()) {
            $this->warn('No active FAQs found. Stats table will be empty.');
            return self::SUCCESS;
        }

        // Extract keywords from all FAQs using the same logic as FaqResponder
        $keywordDocCount = []; // keyword => number of FAQs containing it

        foreach ($faqs as $faq) {
            $keywords = $this->extractKeywordsFromFaq($faq->question, $faq->keywords ?? []);
            
            // Use array_unique to count each keyword once per FAQ (document frequency)
            $uniqueKeywords = array_unique($keywords);
            
            foreach ($uniqueKeywords as $keyword) {
                if (mb_strlen($keyword) < 2) {
                    continue;
                }
                $keywordDocCount[$keyword] = ($keywordDocCount[$keyword] ?? 0) + 1;
            }
        }

        // Truncate and rebuild the stats table in a transaction
        DB::transaction(function () use ($keywordDocCount) {
            DB::table('chatbot_keyword_stats')->truncate();

            if ($keywordDocCount !== []) {
                $rows = [];
                foreach ($keywordDocCount as $keyword => $df) {
                    $rows[] = [
                        'keyword' => $keyword,
                        'document_frequency' => $df,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('chatbot_keyword_stats')->insert($rows);
            }
        });

        // Clear the cache
        Cache::forget('chatbot_keyword_stats');

        $this->info(sprintf('Rebuilt keyword stats: %d unique keywords indexed.', count($keywordDocCount)));

        return self::SUCCESS;
    }

    /**
     * Extract keywords from FAQ question and keywords array.
     * Mirrors FaqResponder::extractKeywords() logic.
     *
     * @return array<int, string>
     */
    private function extractKeywordsFromFaq(string $question, array $faqKeywords): array
    {
        $allText = $question . ' ' . implode(' ', $faqKeywords);

        $allText = preg_replace('/[？?。.!！｡\s]+/u', ' ', $allText);
        $allText = preg_replace('/(の|は|が|を|で|に|です|ます|か|について|どう|なん|何|如何)/u', ' ', (string) $allText);

        $words = array_filter(
            array_map('trim', explode(' ', (string) $allText)),
            fn ($w) => mb_strlen($w) >= 2
        );

        return array_values(array_unique($words));
    }
}
