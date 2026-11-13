<?php

use App\Jobs\CheckAndNotifyTrialExpiries;
use App\Jobs\SendTrialNurtureEmails;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Sentry\Laravel\ServiceProvider as SentryServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/health',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\EnforceTwoFactor::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // 毎日午前1時にSQLiteデータベースの整合性チェックを実行
        $schedule->command('db:health-check')
                 ->dailyAt('01:00')
                 ->name('db:health-check')
                 ->withoutOverlapping();

        // 毎日午前9時にトライアル期限チェックジョブを実行
        $schedule->job(new CheckAndNotifyTrialExpiries())
                 ->dailyAt('09:00')
                 ->name('trial:expiry-check')
                 ->withoutOverlapping();

        // 毎日午前10時にナーチャリングメールシーケンスを実行
        $schedule->job(new SendTrialNurtureEmails())
                 ->dailyAt('10:00')
                 ->name('trial:nurture-emails')
                 ->withoutOverlapping();

        // 毎月1日午前2時に5年以上古い請求書PDFキャッシュを削除
        $schedule->command('invoices:cleanup-pdfs --years=5')
                 ->monthlyOn(1, '02:00')
                 ->name('invoices:cleanup-pdfs')
                 ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Sentry は config/sentry.php の DSN 設定に基づき自動で例外をキャプチャするため、
        // ここでは追加のカスタムレポートは行わない（DSN未設定時は自動無効化）。
    })
    ->withProviders([
        SentryServiceProvider::class,
    ])
    ->create();