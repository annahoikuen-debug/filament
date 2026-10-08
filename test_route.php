<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Simulate the preview route
$service = app(\App\Services\InvoicePdfService::class);
$invoice = \App\Models\MonthlyInvoice::find(13000);

$html = $service->previewHtml($invoice, 'invoice');

echo "Content-Type: " . $html->headers->get('Content-Type') . "\n";
echo "Status: " . $html->getStatusCode() . "\n";
echo "Body length: " . strlen($html->getContent()) . "\n";

// Check for key strings
$body = $html->getContent();
$checks = [
    '口座振替' => '口座振替',
    '支払期限' => '支払期限',
    '振込先' => '振込先',
    '口座名義' => '口座名義',
    '御 請 求 書' => '御 請 求 書',
    'ご請求先' => 'ご請求先',
    '発行者情報' => '発行者情報',
];

echo "\n";
foreach ($checks as $label => $text) {
    $found = str_contains($body, $text);
    echo ($found ? '✓' : '✗') . " $label ($text)\n";
}