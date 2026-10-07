<?php

namespace App\Filament\Widgets;

use App\Models\Trial;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TrialOverview extends BaseWidget
{
    protected function getHeading(): string
    {
        return 'トライアル情報';
    }
    
    protected function getStats(): array
    {
        // 現在のユーザーのトライアルを取得（メールベースで検索）
        $trial = Trial::where('email', Auth::user()?->email)
                     ->where('status', 'active')
                     ->first();
                     
        if (!$trial) {
            return [];
        }
        
        return [
            Stat::make('トライアル期間', $trial->daysUntilExpiry() . '日残')
                ->description($trial->trial_ends_at?->format('Y/m/d'))
                ->color($trial->daysUntilExpiry() < 3 ? 'danger' : 'success'),
            Stat::make('登録入居者数', $trial->facility?->residents->count() ?? 0)
                ->description('施設総入居者数')
                ->color('info'),
            Stat::make('今月の請求書数', 
                $trial->facility?->monthlyInvoices
                      ->where('billing_year_month', now()->format('Y-m'))
                      ->count() ?? 0)
                ->description('作成済み請求書')
                ->color('warning'),
        ];
    }
}