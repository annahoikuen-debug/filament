<?php

namespace App\Filament\Widgets;

use App\Models\DailyCharge;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class UnprocessedDailyChargesWidget extends BaseWidget
{
    protected function getHeading(): string
    {
        return '未処理の日次課金';
    }

    protected function getStats(): array
    {
        // Count daily charges for today that are not yet invoiced?
        // For now, we'll count today's charges as a placeholder.
        $today = now()->toDateString();
        $count = DailyCharge::query()
            ->when(Auth::user()?->facility_id, fn ($q, $facilityId) => $q->where('facility_id', $facilityId))
            ->where('date', $today)
            ->count();

        return [
            Stat::make('本日分の日次課金', $count)
                ->description('今日の日付の日次課金件数')
                ->color($count > 0 ? 'warning' : 'success'),
        ];
    }
}
