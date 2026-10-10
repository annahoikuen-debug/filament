<?php

namespace App\Filament\Widgets;

use App\Models\ChatLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ChatbotFaqMissStatsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected function getHeading(): string
    {
        return 'チャットボット未回答分析（直近30日）';
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        // 内部チャネルのみ対象（公開サイトチャネルは別集計）
        $query = ChatLog::query()
            ->channel('internal')
            ->where('intent', 'faq')
            ->where('created_at', '>=', now()->subDays(30));

        if ($user && $user->role === 'facility_admin' && $user->facility_id) {
            $query->where('facility_id', $user->facility_id);
        }

        $totalFaqQuestions = $query->count();
        $missedCount = (clone $query)->where('faq_matched', false)->count();
        $matchedCount = (clone $query)->where('faq_matched', true)->count();
        $matchRate = $totalFaqQuestions > 0
            ? round(($matchedCount / $totalFaqQuestions) * 100, 1)
            : 100;

        $feedbackHelpfulCount = (clone $query)->where('feedback', 'helpful')->count();
        $feedbackTotal = (clone $query)->whereNotNull('feedback')->count();
        $feedbackRate = $feedbackTotal > 0
            ? round(($feedbackHelpfulCount / $feedbackTotal) * 100, 1) . '%'
            : '未集計';

        return [
            Stat::make('FAQ質問総数', $totalFaqQuestions)
                ->description('直近30日間の問い合わせ')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('primary'),

            Stat::make('未回答件数', $missedCount)
                ->description('FAQ拡充の改善候補')
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color($missedCount > 0 ? 'warning' : 'success'),

            Stat::make('FAQ解決率', $matchRate . '%')
                ->description('自動回答できた割合')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($matchRate >= 80 ? 'success' : 'warning'),

            Stat::make('職員評価（有益率）', $feedbackRate)
                ->description("👍 {$feedbackHelpfulCount}件 / 総評価 {$feedbackTotal}件")
                ->descriptionIcon('heroicon-m-hand-thumb-up')
                ->color('info'),
        ];
    }
}
