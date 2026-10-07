<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Trial;
use App\Mail\TrialExpiredMail;
use App\Mail\TrialExpiryWarningMail;
use App\Services\MailService;
use Log;

class SendTrialExpiryNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $trial;

    public function __construct(Trial $trial)
    {
        $this->trial = $trial;
    }

    public function handle(MailService $mailService)
    {
        Log::info("Sending trial expiry notification for trial {$this->trial->id}");

        $daysLeft = $this->trial->daysUntilExpiry();

        // 7日前、3日前、1日前、当日に通知
        if (in_array($daysLeft, [7, 3, 1, 0], true)) {
            $mailService->send(
                new TrialExpiryWarningMail($this->trial, $daysLeft),
                $this->trial->email,
            );
        }

        // 期限切れの場合は自動でstatus更新
        if ($this->trial->isExpired() && $this->trial->status === 'active') {
            $this->trial->update(['status' => 'expired']);
            Log::warning("Trial {$this->trial->id} has expired");

            $mailService->send(
                new TrialExpiredMail($this->trial),
                $this->trial->email,
            );
        }
    }
}
