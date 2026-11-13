<?php

namespace App\Notifications;

use App\Models\MonthlyInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 月次請求生成完了通知。
 *
 * GenerateMonthlyInvoices コマンド実行後、対象施設の管理者へ
 * 新規作成・更新・スキップ・エラー件数を通知する。
 */
class MonthlyInvoicesGenerated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $yearMonth,
        public int $created,
        public int $updated,
        public int $skipped,
        public int $errors,
        public ?int $facilityId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = "{$this->yearMonth}分 請求データ生成完了";
        $mail = (new MailMessage)
            ->subject($subject)
            ->line("請求年月: **{$this->yearMonth}**")
            ->line("新規作成: {$this->created}件")
            ->line("更新: {$this->updated}件")
            ->line("スキップ: {$this->skipped}件");

        if ($this->errors > 0) {
            $mail->line("⚠ エラー: {$this->errors}件（ログを確認してください）")->error();
        } else {
            $mail->line("エラーなし。正常に完了しました。");
        }

        $facilityLabel = $this->facilityId ? "（施設ID: {$this->facilityId}）" : "（全施設）";
        $mail->line("対象: {$facilityLabel}");

        return $mail->action(
            '請求データを確認',
            url('/admin/monthly-invoices?tableFilters[billing_year_month][value]='.$this->yearMonth)
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'monthly_invoices_generated',
            'year_month' => $this->yearMonth,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'facility_id' => $this->facilityId,
            'message' => "{$this->yearMonth}分の請求データ生成が完了しました（新規:{$this->created} / 更新:{$this->updated} / スキップ:{$this->skipped}" . ($this->errors > 0 ? " / エラー:{$this->errors}" : '') . "）",
        ];
    }
}
