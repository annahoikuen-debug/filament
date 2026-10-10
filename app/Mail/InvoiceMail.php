<?php

namespace App\Mail;

use App\Models\MonthlyInvoice;
use App\Models\ServiceInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public MonthlyInvoice $invoice,
        public string $pdfUrl,
        public ?string $message = null,
        public bool $includeCareServices = false,
    ) {}

    public function envelope(): Envelope
    {
        $residentName = $this->invoice->resident->name ?? '入居者様';
        $yearMonth = $this->invoice->billing_year_month;
        $suffix = $this->includeCareServices ? '（介護サービス含む）' : '';

        return new Envelope(
            subject: "【請求書】{$yearMonth}月分 {$residentName}様{$suffix}",
        );
    }

    public function content(): Content
    {
        $careServices = $this->includeCareServices
            ? ServiceInvoice::where('resident_id', $this->invoice->resident_id)
                ->where('billing_year_month', $this->invoice->billing_year_month)
                ->where('status', '!=', 'draft')
                ->whereNotNull('pdf_path')
                ->get()
            : collect();

        return new Content(
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'pdfUrl' => $this->pdfUrl,
                'message' => $this->message,
                'includeCareServices' => $this->includeCareServices,
                'careServices' => $careServices,
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        // 1. 住居費請求書PDF（URLから取得して添付）
        if ($this->pdfUrl) {
            try {
                // 署名付きURLからPDFを取得
                $response = Http::timeout(30)->get($this->pdfUrl);
                if ($response->successful()) {
                    $attachments[] = Attachment::fromData(
                        fn () => $response->body(),
                        "請求書_{$this->invoice->billing_year_month}_{$this->invoice->resident->room_number}号室_{$this->invoice->resident->name}.pdf"
                    )->withMime('application/pdf');
                }
            } catch (\Throwable $e) {
                Log::warning('住居費PDF添付失敗', ['url' => $this->pdfUrl, 'error' => $e->getMessage()]);
            }
        }

        // 2. 介護サービスPDF（includeCareServicesがtrueの場合）
        if ($this->includeCareServices) {
            $careInvoices = ServiceInvoice::where('resident_id', $this->invoice->resident_id)
                ->where('billing_year_month', $this->invoice->billing_year_month)
                ->where('status', '!=', 'draft')
                ->whereNotNull('pdf_path')
                ->get();

            foreach ($careInvoices as $careInvoice) {
                if ($careInvoice->hasPdf()) {
                    try {
                        $pdfContent = Storage::disk('local')->get($careInvoice->pdf_path);
                        $label = $careInvoice->service_type->getLabel();
                        $attachments[] = Attachment::fromData(
                            fn () => $pdfContent,
                            "{$label}_請求書_{$careInvoice->billing_year_month}_{$this->invoice->resident->room_number}号室_{$this->invoice->resident->name}.pdf"
                        )->withMime('application/pdf');
                    } catch (\Throwable $e) {
                        Log::warning('介護サービスPDF添付失敗', ['invoice_id' => $careInvoice->id, 'error' => $e->getMessage()]);
                    }
                }
            }
        }

        return $attachments;
    }
}
