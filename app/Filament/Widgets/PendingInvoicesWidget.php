<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\MonthlyInvoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class PendingInvoicesWidget extends BaseWidget
{
    protected function getHeading(): string
    {
        return '未発行の請求書';
    }

    protected function getStats(): array
    {
        // Count invoices that are not paid (i.e., Unbilled or Billed)
        $count = MonthlyInvoice::query()
            ->when(Auth::user()?->facility_id, fn ($q, $facilityId) => $q->where('facility_id', $facilityId))
            ->whereNotIn('status', [InvoiceStatus::Paid])
            ->count();

        return [
            Stat::make('未発行・請求済み件数', $count)
                ->description('支払い待ちまたは請求済みの請求書件数')
                ->color($count > 0 ? 'warning' : 'success'),
        ];
    }
}
