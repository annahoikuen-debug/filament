<?php

use App\Console\Commands\GenerateMonthlyInvoices;
use App\Models\ChatLog;
use App\Models\Facility;
use App\Services\InvoicePdfService;
use App\Services\Ops\AlertDispatcher;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;
use App\Enums\InvoiceStatus;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pdf:install-fonts {--force : 既存フォントを上書きする}', function () {
    $pdfService = app(InvoicePdfService::class);

    $this->info('Noto Sans JP フォントのインストールを開始します...');

    $results = $pdfService->installNotoSansJpFonts();

    $this->newLine();
    $this->table(['ウェイト', '結果'], array_map(fn ($weight, $result) => [$weight, $result], array_keys($results), $results));

    $this->newLine();

    $installed = collect($results)->filter(fn ($r) => $r === 'installed')->count();
    $alreadyExists = collect($results)->filter(fn ($r) => $r === 'already_exists')->count();
    $failed = collect($results)->filter(fn ($r) => str_starts_with($r, 'error') || $r === 'download_failed')->count();

    if ($installed > 0) {
        $this->info("{$installed} 個のフォントを新規インストールしました。");
    }
    if ($alreadyExists > 0) {
        $this->info("{$alreadyExists} 個のフォントは既に存在します。");
    }
    if ($failed > 0) {
        $this->error("{$failed} 個のフォントのインストールに失敗しました。");

        return 1;
    }

    $this->info('フォントキャッシュをクリアしました。');
    $this->info('インストール完了！');

    return 0;
})->purpose('Noto Sans JP フォントをダウンロードしてPDF生成用にインストールします');

// チャットボット会話ログの保持期間クリーンアップ（チャネル別保持期間）
Schedule::call(function () {
    $channels = [
        'internal' => (int) config('chatbot.retention_days', 90),
        'public' => (int) config('chatbot.public.retention_days', 30),
    ];
    foreach ($channels as $channel => $retentionDays) {
        $deleted = ChatLog::channel($channel)
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->delete();
        if ($deleted > 0) {
            Log::info("チャットログクリーンアップ: [{$channel}] {$deleted} 件を削除");
        }
    }
})
    ->dailyAt('04:00')
    ->name('chatlog.cleanup')
    ->withoutOverlapping()
    ->description('チャットボット会話ログの保持期間超過分を削除（内部90日・公開30日）')
    ->environments(['production', 'staging']);

// FAQキーワードDF統計の再構築（日次・深夜帯）
Schedule::command('chatbot:rebuild-keyword-stats')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->description('FAQキーワード文書頻度統計の再構築（IDFスコアリング用）')
    ->environments(['production', 'staging']);

// FAQ未回答質問分析（日次）+ クラスタリングによるFAQ候補自動生成
Schedule::command('chatbot:analyze-faq-misses', ['--days' => 30, '--suggest' => true])
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->description('未回答チャットボット質問の集計分析とFAQ候補のクラスタリング生成')
    ->environments(['production', 'staging'])
    ->onSuccess(function () {
        Log::info('FAQ未回答分析＋候補生成: 正常完了');
    })
    ->onFailure(function () {
        Log::error('FAQ未回答分析＋候補生成: 失敗');
    });

// データベース暗号化バックアップ（日次深夜 03:00）と自動検証（03:30）
Schedule::command('backup:database')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->description('毎日 03:00 にデータベース暗号化バックアップを実行')
    ->environments(['production', 'staging'])
    ->onSuccess(function () {
        Log::info('日次バックアップ: 正常完了');
    })
    ->onFailure(function () {
        Log::error('日次バックアップ: 失敗');
    });

Schedule::command('backup:verify-restore')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->description('毎日 03:30 に直近バックアップの復旧・整合性を自動検証')
    ->environments(['production', 'staging'])
    ->onSuccess(function () {
        Log::info('バックアップ復旧自動検証: 正常完了');
    })
    ->onFailure(function () {
        Log::error('バックアップ復旧自動検証: 異常検知');
    });

// スケジューラ設定
Schedule::command(GenerateMonthlyInvoices::class)
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping()
    ->description('毎月1日 02:00 に前月分の請求データを自動生成')
    ->environments(['production', 'staging'])
    ->onSuccess(function () {
        Log::info('月次請求自動生成: 正常完了');
    })
    ->onFailure(function () {
        Log::error('月次請求自動生成: 失敗');
    });

// 請求異常検知（日次 05:00）+ 異常時の即時通知
Schedule::call(function () {
    $yearMonth = now()->format('Y-m');
    $detector = app(\App\Services\BillingAnomalyDetector::class);
    $facilities = \App\Models\Facility::where('is_active', true)->pluck('id');

    foreach ($facilities as $facilityId) {
        $result = $detector->detect($yearMonth, $facilityId);
        if (($result['error_count'] ?? 0) > 0 || ($result['warning_count'] ?? 0) > 0) {
            if (config('ops.notifications.anomaly_alert_enabled', true)) {
                $recipients = app(\App\Services\Ops\NotificationTargetResolver::class)
                    ->resolveForFacility($facilityId);

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new \App\Notifications\BillingAnomalyAlert(
                        yearMonth: $yearMonth,
                        anomalyReport: $result,
                        facilityId: $facilityId,
                    ));
                }
            }
        }
    }
})
    ->dailyAt('05:00')
    ->name('ops:anomaly-detection')
    ->withoutOverlapping()
    ->description('毎日 05:00 に請求異常を検知し、異常時は管理者へ通知')
    ->environments(['production', 'staging']);

// 週次未入金ダイジェスト（毎週月曜 08:00）
Schedule::call(function () {
    $yearMonth = now()->subMonth()->format('Y-m');
    $facilities = \App\Models\Facility::where('is_active', true)->pluck('id');

    foreach ($facilities as $facilityId) {
        $unpaid = \App\Models\MonthlyInvoice::forYearMonth($yearMonth)
            ->forFacility($facilityId)
            ->where('status', \App\Enums\InvoiceStatus::Unbilled)
            ->with(['resident'])
            ->get();

        if ($unpaid->isNotEmpty()) {
            $totalAmount = $unpaid->sum('total_with_tax');
            $unpaidList = $unpaid->map(function ($inv) {
                return [
                    'room_number' => $inv->resident->room_number ?? '-',
                    'resident_name' => $inv->resident->name ?? '-',
                    'total_with_tax' => (int) $inv->total_with_tax,
                ];
            })->toArray();

            if (config('ops.notifications.unpaid_digest_enabled', true)) {
                $recipients = app(\App\Services\Ops\NotificationTargetResolver::class)
                    ->resolveForFacility($facilityId);

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new \App\Notifications\UnpaidInvoiceWeeklyDigest(
                        yearMonth: $yearMonth,
                        unpaidCount: $unpaid->count(),
                        totalAmount: $totalAmount,
                        unpaidInvoices: $unpaidList,
                        facilityId: $facilityId,
                    ));
                }
            }
        }
    }
})
    ->weeklyOn(1, '08:00')
    ->name('ops:unpaid-invoice-digest')
    ->withoutOverlapping()
    ->description('毎週月曜 08:00 に未入金ダイジェストを管理者へ通知')
    ->environments(['production', 'staging']);

// スケジューラ生存確認ハートビート（毎分）
// /health エンドポイントがこのキャッシュの新しさで schedule:work の生存を判定する
Schedule::call(function () {
    Cache::put('ops:heartbeat:schedule_work', now(), 90);
})
    ->everyMinute()
    ->name('ops:heartbeat:schedule_work')
    ->withoutOverlapping()
    ->description('スケジューラ生存確認ハートビート（schedule:work 生存判定）')
    ->environments(['production', 'staging']);

// キューワーカー生存・失敗監視（5分毎）
Schedule::call(function () {
    $failed = DB::table('failed_jobs')
        ->where('failed_at', '>=', now()->subMinutes(10))
        ->count();

    if ($failed > 0) {
        Log::warning("キュー失敗検知: 過去10分間 {$failed} 件");
        AlertDispatcher::critical([
            'title' => 'キュー失敗検知',
            'body' => "過去10分間に {$failed} 件のジョブが失敗しました。",
            'level' => 'warning',
            'context' => ['failed_jobs_count' => $failed],
        ]);
    }
})
    ->everyFiveMinutes()
    ->name('ops:queue:failure-monitor')
    ->withoutOverlapping()
    ->description('キューワーカー失敗監視')
    ->environments(['production', 'staging']);

// 電子帳簿保存法：帳票改ざん検証（毎日 06:00）
Schedule::command('invoice-documents:verify')
    ->dailyAt('06:00')
    ->name('ops:invoice-document-verify')
    ->withoutOverlapping()
    ->description('毎日 06:00 に帳票ファイルのハッシュ改ざん検証を実行')
    ->environments(['production', 'staging'])
    ->onFailure(function () {
        Log::error('帳票改ざん検証: 失敗');
        AlertDispatcher::critical([
            'title' => '帳票改ざん検証失敗',
            'body' => 'invoice-documents:verify コマンドが失敗しました。',
            'level' => 'critical',
        ]);
    });
