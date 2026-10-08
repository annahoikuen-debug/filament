<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MonthlyInvoice;
use App\Services\Pdf\InvoicePdfGenerator;
use Dompdf\Dompdf;
use Dompdf\Options;

$invoice = MonthlyInvoice::with('resident')->first();
$generator = app(InvoicePdfGenerator::class);
$html = $generator->previewInvoice($invoice);

$options = new Options();
$options->set('font_dir', storage_path('fonts'));
$options->set('font_cache', storage_path('fonts'));
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'ipaexg');

$dompdf = new Dompdf($options);
$registry = app(\App\Services\Pdf\Contracts\FontRegistryInterface::class);
$registry->register($dompdf);

$dompdf->loadHtml($html);
$dompdf->render();

$fontMetrics = $dompdf->getFontMetrics();
echo "Font families:\n";
print_r(array_keys($fontMetrics->getFontFamilies()));

file_put_contents(storage_path('app/debug_output.pdf'), $dompdf->output());
echo "Rendered debug PDF successfully.\n";
