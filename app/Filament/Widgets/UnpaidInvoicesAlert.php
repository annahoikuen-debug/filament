<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\MonthlyInvoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class UnpaidInvoicesAlert extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getHeading(): string
    {
        return '未入金アラート';
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $query = MonthlyInvoice::query()
            ->where('status', '!=', InvoiceStatus::Paid)
            ->where('status', '!=', InvoiceStatus::Cancelled);

        // 施設管理者の場合は自施設のみ
        if ($user && $user->role === 'facility_admin' && $user->facility_id) {
            $query->where('facility_id', $user->facility_id);
        }

        $unpaidCount = $query->count();
        $unpaidTotal = $query->sum('total_amount');
        $overdueCount = (clone $query)
            ->where('billing_year_month', '<', now()->subMonth()->format('Y-m'))
            ->count();
        $overdueTotal = (clone $query)
            ->where('billing_year_month', '<', now()->subMonth()->format('Y-m'))
            ->sum('total_amount');

        return [
            Stat::make('未入金件数', $unpaidCount)
                ->description('入金待ちの請求書')
                ->descriptionIcon('heroicon-m-clock')
                ->color($unpaidCount > 0 ? 'warning' : 'success')
                ->chart([7, 3, 5, 2, 8, 4, $unpaidCount]),

            Stat::make('未入金総額', '¥' . number_format($unpaidTotal))
                ->description('回収見込み額')
                ->descriptionIcon('heroicon-m-currency-yen')
                ->color('warning')
                ->chart([100000, 150000, 80000, 200000, 120000, 90000, $unpaidTotal]),

            Stat::make('期限超過件数', $overdueCount)
                ->description('前月以前の未入金')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueCount > 0 ? 'danger' : 'success')
                ->chart([2, 1, 0, 3, 1, 0, $overdueCount]),

            Stat::make('期限超過総額', '¥' . number_format($overdueTotal))
                ->description('至急回収が必要')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($overdueTotal > 0 ? 'danger' : 'success')
                ->chart([50000, 30000, 0, 80000, 20000, 0, $overdueTotal]),
        ];
    }
}