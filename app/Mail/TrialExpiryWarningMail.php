<?php

namespace App\Mail;

use App\Models\Trial;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrialExpiryWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Trial $trial,
        public readonly int $daysLeft,
    ) {}

    public function build()
    {
        return $this->subject("【あんしん】トライアル終了まであと {$this->daysLeft} 日です")
            ->view('emails.trial_expiry_warning')
            ->onQueue('emails');
    }
}
