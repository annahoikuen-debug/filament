<?php

namespace App\Jobs;

use App\Models\MonthlyInvoice;
use App\Services\InvoicePdfService;
use App\Services\FacilityConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class GenerateInvoicePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $yearMonth,
        public int $invoiceId,
        public ?int $facilityId = null,
        public string $jobId = '',
    ) {}

    public function handle(InvoicePdfService $pdfService, FacilityConfigService $configService): void
    {
        $invoice = MonthlyInvoice::with('resident')->find($this->invoiceId);

        if (! $invoice) {
            Log::warning("Invoice {$this->invoiceId} not found for PDF generation");

            return;
        }

        $cachePath = $this->getCachePath($invoice);

        if (File::exists($cachePath)) {
            Log::info("PDF already cached for invoice {$this->invoiceId}: {$cachePath}");

            return;
        }

        try {
            $facilityConfig = $this->facilityId ? $configService->getConfig($this->facilityId) : null;
            $pdf = $pdfService->generateInvoicePdf($invoice, $facilityConfig);
            $pdfContent = $pdf->output();

            File::ensureDirectoryExists(dirname($cachePath));
            File::put($cachePath, $pdfContent);

            Log::info("Generated and cached PDF for invoice {$this->invoiceId}");
        } catch (\Throwable $e) {
            Log::error("Failed to generate PDF for invoice {$this->invoiceId}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function getCachePath(MonthlyInvoice $invoice): string
    {
        return storage_path("app/invoices/{$this->yearMonth}/invoice_{$invoice->id}.pdf");
    }
}