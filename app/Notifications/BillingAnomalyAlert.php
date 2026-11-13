<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 請求異常検知即時通知。
 *
 * BillingAnomalyDetector で Error/WARNING 検知時に即時送信する。
 */
class BillingAnomalyAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $yearMonth,
        public array $anomalyReport,
        public ?int $facilityId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $errors = $this->anomalyReport['error_count'] ?? 0;
        $warnings = $this->anomalyReport['warning_count'] ?? 0;

        $subject = "{$this->yearMonth}分 請求異常検知アラート".($errors > 0 ? "（Error {$errors}件）" : "");

        $mail = (new MailMessage)
            ->subject($subject)
            ->line("請求年月: **{$this->yearMonth}**")
            ->line("Error: **{$errors}件** / Warning: **{$warnings}件**");

        $facilityLabel = $this->facilityId ? "（施設ID: {$this->facilityId}）" : "（全施設）";
        $mail->line("対象: {$facilityLabel}");

        if ($errors > 0) {
            $mail->line("⚠ Error が検出されました。月次締めを進める前に解消してください。")->error();
        }

        $mail->line('主な検知内容:');
        foreach (array_slice(array_merge(
            $this->anomalyReport['errors'] ?? [],
            $this->anomalyReport['warnings'] ?? []
        ), 0, 10) as $item) {
            $mail->line("- [{$item['rule_label']}] {$item['message']}");
        }
        if (count(($this->anomalyReport['errors'] ?? []) + ($this->anomalyReport['warnings'] ?? [])) > 10) {
            $mail->line("... 他にも検知結果があります。");
        }

        $mail->action('異常詳細を確認', url('/admin/monthly-closing-wizard'));

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'billing_anomaly_alert',
            'year_month' => $this->yearMonth,
            'error_count' => $this->anomalyReport['error_count'] ?? 0,
            'warning_count' => $this->anomalyReport['warning_count'] ?? 0,
            'facility_id' => $this->facilityId,
            'message' => "{$this->yearMonth}分で請求異常が検出されました（Error:{$errors} / Warning:{$warnings}）",
        ];
    }
}
