<?php

namespace App\Mail;

use App\Models\Trial;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrialProvisioningCompleteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Trial $trial,
        public readonly string $tempPassword,
    ) {
    }

    public function build()
    {
        return $this->subject('【あんしん】無料トライアルの準備が完了しました')
            ->view('emails.trial_provisioning_complete')
            ->onQueue('emails');
    }
}
