<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 毎日午前9時にトライアル期限チェックジョブを実行
        $schedule->job(new \App\Jobs\CheckAndNotifyTrialExpiries())
                 ->dailyAt('09:00')
                 ->withoutOverlapping();

        // 毎日午前10時にナーチャリングメールシーケンスを実行
        $schedule->job(new \App\Jobs\SendTrialNurtureEmails())
                 ->dailyAt('10:00')
                 ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}