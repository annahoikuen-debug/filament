<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MonthlyInvoice;
use App\Services\InvoicePdfService;

$invoice = MonthlyInvoice::with('resident')->first();
$pdfService = app(InvoicePdfService::class);

try {
    $pdf = $pdfService->generateInvoicePdf($invoice);
    // output canvas messages or logs if any
    $dompdf = $pdf->getDomPDF();
    $canvas = $dompdf->getCanvas();
    echo "Dompdf rendered successfully.\n";
    $dompdf->set_option('show_warnings', true);
    $dompdf->set_option('debugPng', true);
    
    // Check registered fonts
    $fontMetrics = $dompdf->getFontMetrics();
    $families = $fontMetrics->getFontFamilies();
    echo "Registered font families:\n";
    foreach ($families as $family => $styles) {
        echo "  - {$family}: " . implode(', ', array_keys($styles)) . "\n";
    }
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
