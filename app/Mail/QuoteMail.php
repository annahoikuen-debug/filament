<?php

namespace App\Mail;

use App\Models\Trial;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Trial $trial,
        public readonly array $quote, // plan, plan_name, monthly_price, note
    ) {}

    public function build()
    {
        return $this->subject('【あんしん】見積書のご提出')
            ->view('emails.quote')
            ->onQueue('emails');
    }
}
