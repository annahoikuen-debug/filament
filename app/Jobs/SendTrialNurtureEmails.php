<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Trial;
use App\Mail\TrialNurtureMail;
use App\Services\MailService;
use Log;

class SendTrialNurtureEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * ナーチャリングシーケンス（開始日からの経過日数 → ステージ）
     */
    private const SEQUENCE = [
        3 => 'checkin_3d',
        7 => 'case_7d',
        10 => 'convert_10d',
    ];

    public function handle(MailService $mailService)
    {
        $trials = Trial::where('status', 'active')
            ->whereNotNull('trial_started_at')
            ->get();

        foreach ($trials as $trial) {
            $elapsedDays = $trial->trial_started_at->startOfDay()
                ->diffInDays(now()->startOfDay());

            if (!isset(self::SEQUENCE[$elapsedDays])) {
                continue;
            }

            $stage = self::SEQUENCE[$elapsedDays];
            $sentStages = $trial->trial_config['emails_sent'] ?? [];

            // 既送信のステージはスキップ（重複防止）
            if (in_array($stage, $sentStages, true)) {
                continue;
            }

            $mailService->send(
                new TrialNurtureMail($trial, $stage),
                $trial->email,
            );

            $trial->update([
                'trial_config' => array_merge(
                    $trial->trial_config ?? [],
                    ['emails_sent' => array_merge($sentStages, [$stage])]
                ),
            ]);

            Log::info("Nurture email sent: trial {$trial->id}, stage {$stage}");
        }
    }
}
