<?php

namespace App\Services\Ops;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

/**
 * 運用アラート配信の中核クラス。
 *
 * Slack 互換 Webhook とメールへクリティカルアラートを送信し、
 * 同一キーの重複通知はコールドダウン時間で抑制する。
 */
class AlertDispatcher
{
    /**
     * クリティカルアラートを送信する。
     *
     * @param  array{title: string, body: string, level?: string, context?: array} $payload
     * @return bool 実際に送信した場合 true（抑制・無効時は false）
     */
    public static function critical(array $payload): bool
    {
        if (! config('ops.enabled', false)) {
            Log::info('Ops alert disabled: '.$payload['title']);

            return false;
        }

        $title = $payload['title'];
        $body = $payload['body'];
        $level = Arr::get($payload, 'level', 'critical');
        $context = Arr::get($payload, 'context', []);

        // 同一キーの重複通知を抑制
        $key = 'ops_alert_'.sha1($title.'|'.$body);
        $cooldown = (int) config('ops.cooldown_seconds', 3600);

        if (Cache::has($key)) {
            Log::debug('Ops alert suppressed by cooldown: '.$title);

            return false;
        }

        Cache::put($key, true, now()->addSeconds($cooldown));

        $message = "【{$level}】{$title}\n{$body}";
        if (! empty($context)) {
            $message .= "\n\nコンテキスト: ".json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        $sent = false;

        // Slack 互換 Webhook
        $webhookUrl = config('ops.webhook_url');
        if ($webhookUrl) {
            try {
                Http::post($webhookUrl, [
                    'text' => $message,
                    'username' => 'あんしん システムアラート',
                ]);
                Log::info('Ops alert sent via webhook: '.$title);
                $sent = true;
            } catch (\Throwable $e) {
                Log::error('Ops alert webhook failed: '.$e->getMessage());
            }
        }

        // メール
        $emails = config('ops.emails');
        if (! empty($emails)) {
            try {
                foreach ($emails as $email) {
                    Mail::raw($message, function ($mail) use ($email, $title) {
                        $mail->to($email)
                            ->subject("[あんしん] {$title}");
                    });
                }
                Log::info('Ops alert sent via email: '.$title);
                $sent = true;
            } catch (\Throwable $e) {
                Log::error('Ops alert email failed: '.$e->getMessage());
            }
        }

        return $sent;
    }

    /**
     * バックアップ失敗時のアラートを送信するショートカット。
     */
    public static function backupFailed(string $command, string $error, array $context = []): bool
    {
        return self::critical([
            'title' => 'バックアップ失敗',
            'body' => "コマンド `{$command}` が失敗しました。",
            'level' => 'critical',
            'context' => array_merge($context, ['error' => $error]),
        ]);
    }

    /**
     * スケジューラ未検知（heartbeat古い）時のアラート。
     */
    public static function schedulerDead(string $command, array $context = []): bool
    {
        return self::critical([
            'title' => 'スケジューラ停止の疑い',
            'body' => "コマンド `{$command}` の heartbeat が更新されていません。schedule:work プロセスが停止している可能性があります。",
            'level' => 'warning',
            'context' => $context,
        ]);
    }
}
