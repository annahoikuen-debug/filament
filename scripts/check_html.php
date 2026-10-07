<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MonthlyInvoice;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\View;

$invoice = MonthlyInvoice::with('resident')->first();
if (!$invoice) {
    echo "No invoice found\n";
    exit(1);
}

$invoice->load([
    'resident.dailyCharges' => function ($query) use ($invoice) {
        $query->forYearMonth($invoice->billing_year_month)
            ->with('chargeItem')
            ->orderBy('date');
    },
]);

// Use reflection to call private method
$pdfService = app(InvoicePdfService::class);
$reflection = new ReflectionClass($pdfService);
$method = $reflection->getMethod('getTemplateConfig');
$method->setAccessible(true);
$templateConfig = $method->invoke($pdfService, 'invoice');

$html = View::make('invoices.pdf', [
    'invoice' => $invoice,
    'resident' => $invoice->resident,
    'dailyCharges' => $invoice->resident->dailyCharges,
    'facility' => config('facility'),
    'tax_info' => [
        'non_taxable' => $invoice->non_taxable_amount,
        'taxable' => $invoice->taxable_amount,
        'tax_rate' => $invoice->tax_rate,
        'tax_amount' => $invoice->tax_amount,
        'total_with_tax' => $invoice->total_with_tax,
    ],
    'template' => $templateConfig,
])->render();

file_put_contents(storage_path('app/final_debug_invoice.html'), $html);

echo "HTML generated: " . strlen($html) . " bytes\n";
echo "Has CSS variables (var(): " . (strpos($html, 'var(') !== false ? 'YES' : 'NO') . "\n";
echo "Has :root: " . (strpos($html, ':root') !== false ? 'YES' : 'NO') . "\n";
echo "Has Tailwind classes (class=\"flex): " . (strpos($html, 'class="flex') !== false ? 'YES' : 'NO') . "\n";
echo "Has inline styles: " . (strpos($html, 'style=') !== false ? 'YES' : 'NO') . "\n";

// Check for Japanese text
echo "Has Japanese text (請求書): " . (strpos($html, '請求書') !== false ? 'YES' : 'NO') . "\n";
echo "Has Japanese text (ご請求金額): " . (strpos($html, 'ご請求金額') !== false ? 'YES' : 'NO') . "\n";

// Show first 500 chars
echo "\n--- First 500 chars ---\n";
echo substr($html, 0, 500) . "\n";