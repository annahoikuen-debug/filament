<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Trial;
use Illuminate\Support\Facades\Bus;

class CheckAndNotifyTrialExpiries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function handle()
    {
        // 通知対象日（7日前・3日前・1日前・当日）に通知
        $expiringSoon = Trial::where('status', 'active')
                            ->whereNotNull('trial_ends_at')
                            ->where('trial_ends_at', '<=', now()->addDays(7))
                            ->where('trial_ends_at', '>=', now())
                            ->get();
        
        foreach ($expiringSoon as $trial) {
            if (in_array($trial->daysUntilExpiry(), [7, 3, 1, 0], true)) {
                Bus::dispatch(new \App\Jobs\SendTrialExpiryNotification($trial));
            }
        }
        
        // 既に期限切れになったトライアルを処理
        $expired = Trial::where('status', 'active')
                       ->whereNotNull('trial_ends_at')
                       ->where('trial_ends_at', '<', now())
                       ->get();
                      
                       
        foreach ($expired as $trial) {
            $trial->update(['status' => 'expired']);
            Bus::dispatch(new \App\Jobs\SendTrialExpiryNotification($trial));
        }
    }
}