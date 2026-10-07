<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MonthlyInvoice;
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

$pdfService = app(\App\Services\InvoicePdfService::class);
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

file_put_contents(storage_path('app/current_invoice.html'), $html);

echo "HTML generated: " . strlen($html) . " bytes\n";

// 日本語文字の確認
echo "\n=== HTML内の日本語チェック ===\n";
$checkStrings = ['請求書', 'ご請求金額', '佐藤', '一郎', '号室', '円', '合計', '基本家賃', '管理費', '自費サービス', '消費税', '但し', '領収', '発行者', '振込先', '口座名義', '支払期限', '登録番号', 'ケアレジデンス', 'ひまわり', '株式会社', 'ケア'];
foreach ($checkStrings as $str) {
    $count = substr_count($html, $str);
    if ($count > 0) {
        echo "  {$str}: {$count} 回\n";
    }
}

// HTMLを保存
echo "\nHTML保存先: storage/app/current_invoice.html\n";