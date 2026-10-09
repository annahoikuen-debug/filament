<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailService
{
    /**
     * メール送信（キュー経由で非同期化）
     * 設定未検知時はログへフォールバック
     */
    public function send(Mailable $mailable, string $email): bool
    {
        if (!$this->isMailConfigured()) {
            Log::info('[MailService] メール設定が無いためログに記録します', [
                'to' => $email,
                'mailable' => get_class($mailable),
            ]);
            return false;
        }

        try {
            // キューに投入（onQueue('emails') で emails キューへ）
            // 同期ドライバーの場合は即時送信されるため挙動は変わらない
            Mail::to($email)->queue($mailable);

            Log::info('[MailService] メールをキューに投入しました', [
                'to' => $email,
                'mailable' => get_class($mailable),
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('[MailService] メールキューへの投入に失敗しました', [
                'to' => $email,
                'mailable' => get_class($mailable),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * メール送信設定が検知できるか確認
     * （log/null/array メーラー、または smtp かつ host 未設定の場合は未設定と判断）
     */
    public function isMailConfigured(): bool
    {
        $mailer = config('mail.default', 'smtp');

        if (in_array($mailer, ['log', 'null', 'array'], true)) {
            return false;
        }

        if ($mailer === 'smtp' && empty(config('mail.mailers.smtp.host'))) {
            return false;
        }

        return true;
    }
}
