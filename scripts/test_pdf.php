<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MonthlyInvoice;
use App\Services\InvoicePdfService;

$invoice = MonthlyInvoice::with('resident')->first();
if (!$invoice) {
    echo "No invoice found\n";
    exit(1);
}

$pdfService = app(InvoicePdfService::class);

// 1. 請求書PDF
$invoicePdf = $pdfService->generateInvoicePdf($invoice);
$invoicePath = storage_path('app/sample_invoice.pdf');
file_put_contents($invoicePath, $invoicePdf->output());
echo "Invoice PDF generated: " . filesize($invoicePath) . " bytes\n";

// 2. 領収書PDF
$receiptPdf = $pdfService->generatePdfFromInvoice($invoice, 'receipt');
$receiptPath = storage_path('app/sample_receipt.pdf');
file_put_contents($receiptPath, $receiptPdf->output());
echo "Receipt PDF generated: " . filesize($receiptPath) . " bytes\n";

// 3. ZIP一括出力
$yearMonth = $invoice->billing_year_month;
$zipPath = $pdfService->generateMonthlyZip($yearMonth);
echo "ZIP generated: " . $zipPath . " (" . filesize($zipPath) . " bytes)\n";
