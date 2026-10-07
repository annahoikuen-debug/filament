<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check if PDF contains Japanese text
$pdfPath = storage_path('app/sample_invoice.pdf');
if (!file_exists($pdfPath)) {
    echo "PDF not found: $pdfPath\n";
    exit(1);
}

$pdfContent = file_get_contents($pdfPath);
echo "PDF file size: " . strlen($pdfContent) . " bytes\n";

// Check for PDF header
echo "Has PDF header: " . (strpos($pdfContent, '%PDF') !== false ? 'YES' : 'NO') . "\n";

// Check for Japanese text in PDF (UTF-8 encoded)
echo "Has Japanese text (請求書): " . (strpos($pdfContent, '請求書') !== false ? 'YES' : 'NO') . "\n";
echo "Has Japanese text (ご請求金額): " . (strpos($pdfContent, 'ご請求金額') !== false ? 'YES' : 'NO') . "\n";

// Try to extract text using DomPDF's text extraction
try {
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadFile($pdfPath);
    $text = $pdf->output();
    echo "\nPDF loaded successfully\n";
} catch (\Exception $e) {
    echo "\nError loading PDF: " . $e->getMessage() . "\n";
}

// Check the HTML file
$htmlPath = storage_path('app/final_debug_invoice.html');
if (file_exists($htmlPath)) {
    $html = file_get_contents($htmlPath);
    echo "\nHTML file size: " . strlen($html) . " bytes\n";
    echo "HTML has Japanese text (請求書): " . (strpos($html, '請求書') !== false ? 'YES' : 'NO') . "\n";
    echo "HTML has Japanese text (ご請求金額): " . (strpos($html, 'ご請求金額') !== false ? 'YES' : 'NO') . "\n";
    echo "HTML has Japanese text (佐藤): " . (strpos($html, '佐藤') !== false ? 'YES' : 'NO') . "\n";
}