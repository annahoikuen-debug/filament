<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotFaqSuggestion;
use App\Models\ChatLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * FAQ 未回答質問の貪欲（greedy）クラスタリングを行い、
 * 頻出クラスタを FAQ 候補として suggestions テーブルに upsert する。
 * LLM・外部API不使用。オフピーク夜間バッチ向け。
 */
class FaqSuggestionClusterer
{
    /** クラスタ帰属判定の類似度しきい値 */
    private const SIMILARITY_THRESHOLD = 0.5;

    /** suggestions に登録する最小出現回数 */
    private const MIN_OCCURRENCE = 2;

    public function __construct(
        private readonly BigramTokenizer $tokenizer = new BigramTokenizer,
    ) {}

    /**
     * 過去 N 日の未ヒット質問をクラスタリングし、候補を upsert する
     *
     * @param  int  $days  集計対象日数
     * @param  int  $minOccurrence  登録最小出現回数
     * @return int 新規 or 更新された候補件数
     */
    public function generate(int $days = 30, int $minOccurrence = self::MIN_OCCURRENCE): int
    {
        $since = now()->subDays($days);

        $logs = ChatLog::query()
            ->where('intent', 'faq')
            ->where('faq_matched', false)
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_message')
            ->where('user_message', '!=', '')
            ->orderBy('id')
            ->get(['id', 'user_message', 'facility_id', 'created_at']);

        if ($logs->isEmpty()) {
            return 0;
        }

        $clusters = $this->cluster($logs);
        $upserted = 0;

        foreach ($clusters as $cluster) {
            if ($cluster['count'] < $minOccurrence) {
                continue;
            }

            $alreadyApproved = ChatbotFaqSuggestion::query()
                ->where('cluster_key', $cluster['key'])
                ->whereIn('status', ['approved', 'dismissed'])
                ->exists();

            if ($alreadyApproved) {
                // 承認済み・却下済みクラスタの再提案を抑止（件数情報のみ更新）
                ChatbotFaqSuggestion::query()
                    ->where('cluster_key', $cluster['key'])
                    ->update([
                        'occurrence_count' => $cluster['count'],
                        'last_occurred_at' => $cluster['last_occurred_at'],
                    ]);

                continue;
            }

            ChatbotFaqSuggestion::updateOrCreate(
                ['cluster_key' => $cluster['key']],
                [
                    'representative_message' => $cluster['representative'],
                    'occurrence_count' => $cluster['count'],
                    'last_occurred_at' => $cluster['last_occurred_at'],
                    'facility_id' => $cluster['facility_id'],
                    'status' => 'pending',
                ]
            );

            $upserted++;
        }

        return $upserted;
    }

    /**
     * 貪欲クラスタリング: 代表ベクトルとの類似度が閾値以上のログを同一クラスタに帰属させる
     *
     * @param  Collection<int, ChatLog>  $logs
     * @return array<int, array{key: string, representative: string, count: int, last_occurred_at: Carbon, facility_id: ?int}>
     */
    public function cluster(Collection $logs): array
    {
        $clusters = [];

        foreach ($logs as $log) {
            $message = trim((string) $log->user_message);
            if ($message === '') {
                continue;
            }

            $matched = false;

            foreach ($clusters as $index => $cluster) {
                $sim = $this->tokenizer->similarity($cluster['representative'], $message);
                if ($sim >= self::SIMILARITY_THRESHOLD) {
                    $clusters[$index]['count']++;
                    $clusters[$index]['last_occurred_at'] = max(
                        $cluster['last_occurred_at'],
                        $log->created_at
                    );

                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                $clusters[] = [
                    'key' => ChatbotFaqSuggestion::makeClusterKey($message),
                    'representative' => $message,
                    'count' => 1,
                    'last_occurred_at' => $log->created_at,
                    'facility_id' => $log->facility_id,
                ];
            }
        }

        return $clusters;
    }
}
