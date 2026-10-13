<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Facility;
use App\Models\Resident;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Services\InvoiceCalculationService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceCsvExportService;
use Carbon\Carbon;

echo "=== シミュレーション開始 ===\n";

$calcService = app(InvoiceCalculationService::class);
$pdfService = app(InvoicePdfService::class);
$csvService = app(InvoiceCsvExportService::class);

$facilities = Facility::all();
echo "登録施設数: " . $facilities->count() . "\n";
echo "登録入居者数: " . Resident::count() . "\n";
echo "登録品目数: " . ChargeItem::count() . "\n";

$targetMonths = [
    1 => Carbon::now()->format('Y-m'),               // シミュレーション1: 当月
    2 => Carbon::now()->addMonth()->format('Y-m'),      // シミュレーション2: 翌月
    3 => Carbon::now()->subMonth()->format('Y-m'),      // シミュレーション3: 前月（過去月再計算・日割り・入金消込）
];

foreach ($targetMonths as $round => $targetMonth) {
    echo "\n------------------------------------------------------------\n";
    echo "【シミュレーション {$round}回目】 対象月: {$targetMonth}\n";
    echo "------------------------------------------------------------\n";
    
    // 1. 日常自費記録の登録（シミュレーション用に追加）
    $resident = Resident::first();
    $chargeItem = ChargeItem::first();
    $today = Carbon::today()->toDateString();
    
    $daily = DailyCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $resident->facility_id,
        'charge_item_id' => $chargeItem->id,
        'date' => Carbon::createFromFormat('Y-m', $targetMonth)->startOfMonth()->addDays(5)->toDateString(),
        'unit_price' => $chargeItem->default_price,
        'quantity' => 3,
        'note' => "シミュレーション{$round} 自費テスト",
    ]);
    echo "1. 日常自費記録の登録完了: {$daily->date} / 入居者: {$resident->name} / 金額: " . ($daily->unit_price * $daily->quantity) . "円\n";

    // 2. 月次請求データの一括生成
    $startTime = microtime(true);
    $stats = $calcService->generateForMonth($targetMonth, true); // forceUpdate=true
    $calcDuration = round((microtime(true) - $startTime) * 1000, 2);
    echo "2. 月次請求一括生成完了: 新規 {$stats['created']}件, 更新 {$stats['updated']}件, スキップ {$stats['skipped']}件 (処理時間: {$calcDuration}ms)\n";

    // 3. 金額確認とステータス検証
    $invoices = MonthlyInvoice::forYearMonth($targetMonth)->with('resident')->get();
    echo "3. 請求データ総数: {$invoices->count()}件\n";
    $sampleInvoice = $invoices->first();
    echo "   [サンプル請求書 #{$sampleInvoice->id}] 入居者: {$sampleInvoice->resident->name} ({$sampleInvoice->resident->room_number}号室)\n";
    echo "   家賃: ¥{$sampleInvoice->rent_subtotal}, 管理費: ¥{$sampleInvoice->management_fee_subtotal}, 自費: ¥{$sampleInvoice->service_subtotal}\n";
    echo "   合計金額: ¥{$sampleInvoice->total_amount} (内税: ¥{$sampleInvoice->tax_amount})\n";

    // 4. 個別請求書PDF出力
    $startTime = microtime(true);
    $invoicePdf = $pdfService->generateInvoicePdf($sampleInvoice);
    $pdfBinary = $invoicePdf->output();
    $pdfDuration = round((microtime(true) - $startTime) * 1000, 2);
    $pdfSize = strlen($pdfBinary);
    echo "4. 個別請求書PDF生成: {$pdfSize} bytes (所要時間: {$pdfDuration}ms)\n";

    // 5. 一括ZIP出力
    $startTime = microtime(true);
    $zipPath = $pdfService->generateMonthlyZip($targetMonth);
    $zipDuration = round((microtime(true) - $startTime) * 1000, 2);
    $zipSize = file_exists($zipPath) ? filesize($zipPath) : 0;
    echo "5. 全員分請求書一括ZIP生成: {$zipSize} bytes (所要時間: {$zipDuration}ms) -> {$zipPath}\n";

    // 6. 請求済ステータスへ更新 & 入金消込 & 領収書出力
    $sampleInvoice->update(['status' => InvoiceStatus::Billed]);
    echo "6. ステータス変更: '未請求' -> '請求済'\n";
    
    $paidDate = Carbon::now()->toDateString();
    $sampleInvoice->markAsPaid(PaymentMethod::DirectDebit, $paidDate);
    echo "7. 入金消込完了: 支払方法=" . PaymentMethod::DirectDebit->getLabel() . ", 入金日={$paidDate}, 領収書番号={$sampleInvoice->receipt_number}\n";

    // 領収書PDF生成
    $startTime = microtime(true);
    $receiptPdf = $pdfService->generateReceiptPdf($sampleInvoice);
    $receiptBinary = $receiptPdf->output();
    $receiptDuration = round((microtime(true) - $startTime) * 1000, 2);
    $receiptSize = strlen($receiptBinary);
    echo "8. 領収書PDF生成: {$receiptSize} bytes (所要時間: {$receiptDuration}ms)\n";

    // 9. 会計CSV出力
    $csv = $csvService->exportAccountingJournalCsv($targetMonth, softwareType: 'freee');
    $csvRows = count(explode("\n", trim($csv)));
    echo "9. 会計仕訳CSV(freee)出力: {$csvRows}行\n";
}

echo "\n=== 3回のシミュレーションがすべて正常完了しました ===\n";
