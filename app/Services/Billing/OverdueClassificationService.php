<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Models\MonthlyInvoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OverdueClassificationService
{
    public const STAGE_NORMAL = 'normal';       // 期限内または期日当日
    public const STAGE_NOTICE = 'notice';       // 注意（1〜30日経過）
    public const STAGE_REMINDER = 'reminder';   // 督促（31〜60日経過）
    public const STAGE_WARNING = 'warning';     // 警告・法務対応（61日以上経過）

    /**
     * 未払い請求書の滞納ステージと経過日数を判定
     *
     * @return array{stage: string, days_overdue: int, label: string}
     */
    public function classify(MonthlyInvoice $invoice, ?Carbon $asOfDate = null): array
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            return [
                'stage' => self::STAGE_NORMAL,
                'days_overdue' => 0,
                'label' => '入金済み',
            ];
        }

        $now = $asOfDate ?: now();
        $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date) : Carbon::parse($invoice->billing_year_month . '-27');

        if ($now->lessThanOrEqualTo($dueDate)) {
            return [
                'stage' => self::STAGE_NORMAL,
                'days_overdue' => 0,
                'label' => '期日内',
            ];
        }

        $daysOverdue = (int) $dueDate->diffInDays($now);

        if ($daysOverdue <= 30) {
            $stage = self::STAGE_NOTICE;
            $label = "注意 ({$daysOverdue}日経過)";
        } elseif ($daysOverdue <= 60) {
            $stage = self::STAGE_REMINDER;
            $label = "要督促 ({$daysOverdue}日経過)";
        } else {
            $stage = self::STAGE_WARNING;
            $label = "法務警告 ({$daysOverdue}日経過)";
        }

        return [
            'stage' => $stage,
            'days_overdue' => $daysOverdue,
            'label' => $label,
        ];
    }
}
