<?php

namespace App\Services\Invoice;

use App\Models\MonthlyInvoice;
use App\Models\Resident;
use Carbon\Carbon;

class BasisTextBuilder
{
    public function __construct(
        private ProrationCalculator $prorationCalculator = new ProrationCalculator()
    ) {}

    /**
     * 月次請求の日割り計算根拠テキストを生成
     */
    public function build(MonthlyInvoice $invoice): string
    {
        $resident = $invoice->resident;
        if (! $resident) {
            return '入居者情報なし';
        }

        [$year, $month] = explode('-', $invoice->billing_year_month);
        $year = (int) $year;
        $month = (int) $month;

        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        $livingDays = $this->prorationCalculator->calculateLivingDays($resident, $year, $month);

        if ($livingDays >= $daysInMonth) {
            return "満月在籍 ({$daysInMonth}日/{$daysInMonth}日) 満額計算\n家賃: ¥" . number_format($invoice->rent_subtotal) . " / 管理費: ¥" . number_format($invoice->management_fee_subtotal) . " / 自費: ¥" . number_format($invoice->service_subtotal);
        }

        if ($livingDays <= 0) {
            return "対象期間外 (在籍0日)";
        }

        return "日割り計算: 在籍 {$livingDays}日 / 当月 {$daysInMonth}日\n" .
            "家賃式: ¥" . number_format($resident->base_rent) . " × {$livingDays}/{$daysInMonth} = ¥" . number_format($invoice->rent_subtotal) . "\n" .
            "管理費式: ¥" . number_format($resident->base_management_fee) . " × {$livingDays}/{$daysInMonth} = ¥" . number_format($invoice->management_fee_subtotal) . "\n" .
            "自費小計: ¥" . number_format($invoice->service_subtotal);
    }
}
