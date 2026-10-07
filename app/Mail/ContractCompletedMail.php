<?php

namespace App\Mail;

use App\Models\Trial;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContractCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Trial $trial,
        public readonly string $plan,
        public readonly int $monthlyPrice,
    ) {
    }

    public function build()
    {
        return $this->subject('【あんしん】本契約が完了しました')
            ->view('emails.contract_completed')
            ->onQueue('emails');
    }
}
