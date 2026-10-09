<?php

use App\Jobs\CheckAndNotifyTrialExpiries;
use App\Jobs\SendTrialNurtureEmails;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withSchedule(function (Schedule $schedule) {
        // 毎日午前9時にトライアル期限チェックジョブを実行
        $schedule->job(new CheckAndNotifyTrialExpiries())
                 ->dailyAt('09:00')
                 ->withoutOverlapping();

        // 毎日午前10時にナーチャリングメールシーケンスを実行
        $schedule->job(new SendTrialNurtureEmails())
                 ->dailyAt('10:00')
                 ->withoutOverlapping();

        // 毎月1日午前2時に5年以上古い請求書PDFキャッシュを削除
        $schedule->command('invoices:cleanup-pdfs --years=5')
                 ->monthlyOn(1, '02:00')
                 ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();