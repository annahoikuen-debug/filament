<?php

namespace App\Mail;

use App\Models\Trial;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrialExpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Trial $trial) {}

    public function build()
    {
        return $this->subject('【あんしん】無料トライアルの期限が切れました')
            ->view('emails.trial_expired')
            ->onQueue('emails');
    }
}
