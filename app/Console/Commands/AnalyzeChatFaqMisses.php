<?php

namespace App\Console\Commands;

use App\Models\ChatLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnalyzeChatFaqMisses extends Command
{
    protected $signature = 'chatbot:analyze-faq-misses {--days=30 : 集計対象の日数}';

    protected $description = 'FAQに一致しなかったチャットボット質問の集計・頻度ランキングを出力します';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $since = now()->subDays($days);

        $this->info("=== FAQ未回答質問分析（直近 {$days} 日間） ===");

        $query = ChatLog::query()
            ->where('intent', 'faq')
            ->where('faq_matched', false)
            ->where('created_at', '>=', $since);

        $totalMisses = $query->count();
        $this->line("未回答質問の総件数: {$totalMisses}件");

        if ($totalMisses === 0) {
            $this->info('未回答の質問はありませんでした。');
            return Command::SUCCESS;
        }

        $ranking = ChatLog::query()
            ->where('intent', 'faq')
            ->where('faq_matched', false)
            ->where('created_at', '>=', $since)
            ->select('user_message', DB::raw('count(*) as count'))
            ->groupBy('user_message')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        $rows = $ranking->map(fn ($item, $index) => [
            'rank' => $index + 1,
            'count' => $item->count,
            'percentage' => round(($item->count / $totalMisses) * 100, 1) . '%',
            'message' => $item->user_message,
        ])->toArray();

        $this->table(['順位', '件数', '割合', '質問内容'], $rows);

        // Bi-gram類似度による未回答質問の自動クラスタリング
        $this->clusterAndDisplay($ranking, $totalMisses);

        return Command::SUCCESS;
    }

    /**
     * 未回答質問をBi-gram類似度でグループ化（クラスタリング）して表示する
     */
    private function clusterAndDisplay(\Illuminate\Support\Collection $items, int $totalMisses): void
    {
        if ($items->count() < 2) {
            return;
        }

        $this->newLine();
        $this->info("=== 類似未回答質問の自動グループ（クラスタリング） ===");

        $tokenizer = new \App\Services\Chatbot\BigramTokenizer;
        $clusters = [];
        $visited = [];

        foreach ($items as $i => $itemA) {
            if (isset($visited[$i])) {
                continue;
            }

            $cluster = [
                'representative' => $itemA->user_message,
                'count' => (int) $itemA->count,
                'variations' => [],
            ];
            $visited[$i] = true;

            foreach ($items as $j => $itemB) {
                if ($i === $j || isset($visited[$j])) {
                    continue;
                }

                $sim = $tokenizer->similarity($itemA->user_message, $itemB->user_message);
                if ($sim >= 0.35) {
                    $cluster['count'] += (int) $itemB->count;
                    $cluster['variations'][] = $itemB->user_message;
                    $visited[$j] = true;
                }
            }

            if (! empty($cluster['variations'])) {
                $clusters[] = $cluster;
            }
        }

        if (empty($clusters)) {
            $this->line('目立った類似バリエーション質問はありませんでした。');
            return;
        }

        $clusterRows = [];
        foreach ($clusters as $idx => $c) {
            $clusterRows[] = [
                'group' => 'グループ ' . ($idx + 1),
                'count' => $c['count'] . '件 (' . round(($c['count'] / $totalMisses) * 100, 1) . '%)',
                'representative' => $c['representative'],
                'variations' => implode(', ', $c['variations']),
            ];
        }

        $this->table(['グループ', '合計件数', '代表質問', '類似質問バリエーション'], $clusterRows);
    }
}
