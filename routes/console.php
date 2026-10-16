<?php

use App\Console\Commands\GenerateMonthlyInvoices;
use App\Models\ChatLog;
use App\Services\InvoicePdfService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

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
    ->description('チャットボット会話ログの保持期間超過分を削除（内部90日・公開30日）')
    ->environments(['production', 'staging']);

// FAQキーワードDF統計の再構築（日次・深夜帯）
Schedule::command('chatbot:rebuild-keyword-stats')
    ->dailyAt('02:30')
    ->description('FAQキーワード文書頻度統計の再構築（IDFスコアリング用）')
    ->environments(['production', 'staging']);

// FAQ未回答質問分析（日次）+ クラスタリングによるFAQ候補自動生成
Schedule::command('chatbot:analyze-faq-misses', ['--days' => 30, '--suggest' => true])
    ->dailyAt('03:00')
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
    ->description('毎月1日 02:00 に前月分の請求データを自動生成')
    ->environments(['production', 'staging'])
    ->onSuccess(function () {
        Log::info('月次請求自動生成: 正常完了');
    })
    ->onFailure(function () {
        Log::error('月次請求自動生成: 失敗');
    });
