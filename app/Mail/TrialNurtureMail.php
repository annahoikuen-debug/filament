<?php

namespace App\Mail;

use App\Models\Trial;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrialNurtureMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Trial $trial,
        public readonly string $stage, // checkin_3d, case_7d, convert_10d
    ) {
    }

    public function build()
    {
        $subjects = [
            'checkin_3d' => '【あんしん】トライアルは順調ですか？',
            'case_7d' => '【あんしん】導入施設の声をお届けします',
            'convert_10d' => '【あんしん】本契約への移行をご検討ください',
        ];

        return $this->subject($subjects[$this->stage] ?? '【あんしん】お知らせ')
            ->view('emails.trial_nurture')
            ->onQueue('emails');
    }
}
