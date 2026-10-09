<?php

namespace App\Mail;

use App\Models\MonthlyInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public MonthlyInvoice $invoice,
        public string $pdfUrl,
        public ?string $message = null,
    ) {}

    public function envelope(): Envelope
    {
        $residentName = $this->invoice->resident->name ?? '入居者様';
        $yearMonth = $this->invoice->billing_year_month;
        return new Envelope(
            subject: "【請求書】{$yearMonth}月分 {$residentName}様",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'pdfUrl' => $this->pdfUrl,
                'message' => $this->message,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}