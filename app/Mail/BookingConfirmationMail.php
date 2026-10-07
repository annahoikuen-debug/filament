<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Booking $booking)
    {
    }

    public function build()
    {
        return $this->subject('【あんしん】デモ面談の予約を受け付けました')
            ->view('emails.booking_confirmation')
            ->onQueue('emails');
    }
}
