<?php

namespace App\Notifications;

use App\Models\MonthlyInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 週次未入金ダイジェスト通知。
 *
 * 毎週月曜 08:00 に実行され、施設ごとの未入金請求書一覧を
 * 施設管理者・法人管理者へ通知する。
 */
class UnpaidInvoiceWeeklyDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $yearMonth,
        public int $unpaidCount,
        public int $totalAmount,
        public array $unpaidInvoices,
        public ?int $facilityId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = "{$this->yearMonth}分 未入金ダイジェスト（週次）";
        $mail = (new MailMessage)
            ->subject($subject)
            ->line("請求年月: **{$this->yearMonth}**")
            ->line("未入金件数: **{$this->unpaidCount}件**")
            ->line("未入金総額: **¥".number_format($this->totalAmount)."**");

        $facilityLabel = $this->facilityId ? "（施設ID: {$this->facilityId}）" : "（全施設）";
        $mail->line("対象: {$facilityLabel}");

        if ($this->unpaidInvoices) {
            $mail->line('未入金入居者一覧:');
            foreach (array_slice($this->unpaidInvoices, 0, 10) as $inv) {
                $mail->line("- {$inv['room_number']} {$inv['resident_name']}：¥".number_format($inv['total_with_tax']));
            }
            if (count($this->unpaidInvoices) > 10) {
                $mail->line("... 他 ".(count($this->unpaidInvoices) - 10)."件");
            }
        }

        $mail->action('未入金一覧を確認', url('/admin/monthly-invoices?tableFilters[status][value]=unbilled'));

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'unpaid_invoice_weekly_digest',
            'year_month' => $this->yearMonth,
            'unpaid_count' => $this->unpaidCount,
            'total_amount' => $this->totalAmount,
            'facility_id' => $this->facilityId,
            'message' => "{$this->yearMonth}分の未入金が{$this->unpaidCount}件（¥".number_format($this->totalAmount)."）あります。督促対象を確認してください。",
        ];
    }
}
