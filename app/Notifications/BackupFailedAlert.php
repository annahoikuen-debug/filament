<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * バックアップ失敗通知。
 *
 * DatabaseBackup コマンド失敗時に、運用責任者へ通知する。
 */
class BackupFailedAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $command,
        public string $error,
        public array $context = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('バックアップ失敗アラート')
            ->error()
            ->line("コマンド: **{$this->command}**")
            ->line("エラー: {$this->error}")
            ->line('バックアップが失敗しました。至急ログを確認し、復旧作業を実行してください。')
            ->action('ログを確認', url('/admin/activity-logs'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'backup_failed',
            'command' => $this->command,
            'error' => $this->error,
            'message' => "バックアップ失敗: {$this->command} - {$this->error}",
        ];
    }
}
