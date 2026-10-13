<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$invoice = \App\Models\MonthlyInvoice::find(1);
$service = app(\App\Services\InvoicePdfService::class);
$pdf = $service->generateInvoicePdf($invoice);
echo 'PDF generated: ' . strlen($pdf->output()) . ' bytes' . PHP_EOL;

// Save to file
file_put_contents('storage/app/test_invoice.pdf', $pdf->output());
echo 'PDF saved to storage/app/test_invoice.pdf' . PHP_EOL;