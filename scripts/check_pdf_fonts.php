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
$pdf = $pdfService->generateInvoicePdf($invoice);
$pdfPath = storage_path('app/test_bold.pdf');
file_put_contents($pdfPath, $pdf->output());

echo "PDF generated: " . filesize($pdfPath) . " bytes\n";

// Check if bold text is in the PDF stream
$content = file_get_contents($pdfPath);

// Check for font references in PDF
echo "\n=== PDF内のフォント参照 ===\n";
echo "ipaexg: " . (strpos($content, 'ipaexg') !== false ? 'YES' : 'NO') . "\n";
echo "IPAexGothic: " . (strpos($content, 'IPAexGothic') !== false ? 'YES' : 'NO') . "\n";
echo "Bold: " . (strpos($content, 'Bold') !== false ? 'YES' : 'NO') . "\n";

// Check for text operators that might indicate bold
echo "\n=== PDFテキストオペレータ ===\n";
echo "Tf (text font): " . (substr_count($content, ' Tf ') > 0 ? 'YES' : 'NO') . "\n";