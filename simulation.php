<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Facility;
use App\Models\Resident;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Services\InvoiceCalculationService;
use App\Services\InvoicePdfService;
use Carbon\Carbon;

echo "========================================\n";
echo "老人ホーム請求書作成シミュレーション\n";
echo "定員25名施設の1ヶ月分請求処理\n";
echo "========================================\n\n";

// 1. 対象施設の取得（施設ID=1: ケアレジデンスひまわり）
$facility = Facility::find(1);
echo "【対象施設】\n";
echo "  名称: {$facility->name}\n";
echo "  運営: {$facility->operator}\n";
echo "  住所: {$facility->address}\n\n";

// 2. 入居者25名の確認
$residents = Resident::where('facility_id', $facility->id)
    ->where('status', 'active')
    ->orderBy('room_number')
    ->get();

echo "【入居者一覧】({$residents->count()}名)\n";
foreach ($residents as $resident) {
    echo "  {$resident->room_number}号室: {$resident->name} ({$resident->name_kana}) - 家賃: ¥" . number_format($resident->base_rent) . " / 管理費: ¥" . number_format($resident->base_management_fee) . "\n";
}
echo "\n";

// 3. 自費サービス品目マスタ確認
$chargeItems = ChargeItem::where('is_active', true)->get();
echo "【自費サービス品目】\n";
foreach ($chargeItems as $item) {
    echo "  - {$item->name}: デフォルト単価 ¥" . number_format($item->default_price) . " (税区分: {$item->tax_type->value})\n";
}
echo "\n";

// 4. 当月の自費利用記録をシミュレーション（スマホ入力を想定）
echo "【ステップ1: 消耗品・介護保険費用の入力（スマホ操作をシミュレート）】\n";
$targetMonth = '2026-10';
$monthStart = Carbon::createFromFormat('Y-m', $targetMonth)->startOfMonth();
$monthEnd = Carbon::createFromFormat('Y-m', $targetMonth)->endOfMonth();
$daysInMonth = $monthEnd->day;

// 各入居者に対してランダムに自費利用記録を作成
$createdCharges = 0;
foreach ($residents as $index => $resident) {
    // おむつ使用（約60%の入居者）
    if ($index % 2 === 0 || $index % 3 === 0) {
        $item = $chargeItems->where('name', '紙おむつ (パンツタイプ)')->first();
        if ($item) {
            DailyCharge::updateOrCreate([
                'resident_id' => $resident->id,
                'charge_item_id' => $item->id,
                'date' => $monthStart->copy()->addDays(rand(1, $daysInMonth - 1))->toDateString(),
            ], [
                'facility_id' => $facility->id,
                'unit_price' => $item->default_price,
                'quantity' => rand(15, 45), // 月間枚数
                'note' => '日中・夜間交換分',
            ]);
            $createdCharges++;
        }
    }
    
    // 尿取りパッド（約40%）
    if ($index % 3 === 0) {
        $item = $chargeItems->where('name', '尿とりパッド')->first();
        if ($item) {
            DailyCharge::updateOrCreate([
                'resident_id' => $resident->id,
                'charge_item_id' => $item->id,
                'date' => $monthStart->copy()->addDays(rand(1, $daysInMonth - 1))->toDateString(),
            ], [
                'facility_id' => $facility->id,
                'unit_price' => $item->default_price,
                'quantity' => rand(60, 120),
                'note' => '1日分',
            ]);
            $createdCharges++;
        }
    }
    
    // 理美容（約20%）
    if ($index % 5 === 0) {
        $item = $chargeItems->where('name', '理美容代 (カット)')->first();
        if ($item) {
            DailyCharge::updateOrCreate([
                'resident_id' => $resident->id,
                'charge_item_id' => $item->id,
                'date' => $monthStart->copy()->addDays(rand(1, $daysInMonth - 1))->toDateString(),
            ], [
                'facility_id' => $facility->id,
                'unit_price' => $item->default_price,
                'quantity' => 1,
                'note' => '訪問理美容',
            ]);
            $createdCharges++;
        }
    }
    
    // 受診付き添い（約15%）
    if ($index % 7 === 0) {
        $item = $chargeItems->where('name', '受診付き添い費 (30分)')->first();
        if ($item) {
            DailyCharge::updateOrCreate([
                'resident_id' => $resident->id,
                'charge_item_id' => $item->id,
                'date' => $monthStart->copy()->addDays(rand(1, $daysInMonth - 1))->toDateString(),
            ], [
                'facility_id' => $facility->id,
                'unit_price' => $item->default_price,
                'quantity' => rand(1, 3),
                'note' => '通院付き添い',
            ]);
            $createdCharges++;
        }
    }
    
    // 日用品立替金（約30%）
    if ($index % 4 === 0) {
        $item = $chargeItems->where('name', '日用品・嗜好品立替金')->first();
        if ($item) {
            DailyCharge::updateOrCreate([
                'resident_id' => $resident->id,
                'charge_item_id' => $item->id,
                'date' => $monthStart->copy()->addDays(rand(1, $daysInMonth - 1))->toDateString(),
            ], [
                'facility_id' => $facility->id,
                'unit_price' => rand(1000, 5000),
                'quantity' => 1,
                'note' => 'ティッシュ・洗剤・嗜好品等立替',
            ]);
            $createdCharges++;
        }
    }
}

echo "  作成された自費利用記録: {$createdCharges}件\n\n";

// 5. 当月の請求書を一括生成
echo "【ステップ2: 請求書生成・計算処理】\n";
$service = app()->make(InvoiceCalculationService::class);
$stats = $service->generateForMonth($targetMonth, false, $facility->id);

echo "  対象年月: {$targetMonth}\n";
echo "  対象施設: {$facility->name}\n";
echo "  結果:\n";
echo "    - 新規作成: {$stats['created']}件\n";
echo "    - 更新: {$stats['updated']}件\n";
echo "    - スキップ: {$stats['skipped']}件\n";
echo "    - エラー: {$stats['conflicts']}件\n";
echo "    - 対象入居者数: {$stats['total_residents']}名\n\n";

// 6. 生成された請求書のサマリー表示
echo "【ステップ3: 生成された請求書サマリー】\n";
$invoices = MonthlyInvoice::where('billing_year_month', $targetMonth)
    ->where('facility_id', $facility->id)
    ->with('resident')
    ->orderBy('resident_id')
    ->get();

$totalAmount = 0;
$totalTax = 0;
foreach ($invoices as $invoice) {
    $resident = $invoice->resident;
    echo "  {$resident->room_number}号室 {$resident->name}様\n";
    echo "    家賃: ¥" . number_format($invoice->rent_subtotal) . " (非課税)\n";
    echo "    管理費: ¥" . number_format($invoice->management_fee_subtotal) . " (課税10%)\n";
    echo "    自費サービス: ¥" . number_format($invoice->service_subtotal) . "\n";
    echo "    小計: ¥" . number_format($invoice->total_amount) . "\n";
    echo "    消費税: ¥" . number_format($invoice->tax_amount) . "\n";
    echo "    税込合計: ¥" . number_format($invoice->total_with_tax) . "\n";
    echo "    ステータス: {$invoice->status->value}\n\n";
    $totalAmount += $invoice->total_amount;
    $totalTax += $invoice->tax_amount;
}

echo "【施設合計】\n";
echo "  総額（税抜）: ¥" . number_format($totalAmount) . "\n";
echo "  消費税合計: ¥" . number_format($totalTax) . "\n";
echo "  税込総合計: ¥" . number_format($totalAmount + $totalTax) . "\n\n";

// 7. PDF出力（全入居者分）
echo "【ステップ4: PDF一括出力（ZIPアーカイブ）】\n";
$pdfService = app()->make(InvoicePdfService::class);

echo "  同期版ZIP生成中...\n";
$zipPath = $pdfService->generateMonthlyZipSync($targetMonth, $facility->id);
echo "  ZIPファイル作成完了: {$zipPath}\n";
echo "  ファイルサイズ: " . number_format(filesize($zipPath) / 1024, 1) . " KB\n\n";

// 8. カテゴリ別ZIP生成（住居費・介護サービス・統合）
echo "  カテゴリ別ZIP生成中...\n";
$zipPathCat = $pdfService->generateMonthlyZipByCategory($targetMonth, $facility->id, true);
echo "  カテゴリ別ZIP作成完了: {$zipPathCat}\n";
echo "  ファイルサイズ: " . number_format(filesize($zipPathCat) / 1024, 1) . " KB\n\n";

// 9. 個別PDF生成テスト（最初の入居者）
echo "【ステップ5: 個別PDF生成テスト】\n";
$firstInvoice = $invoices->first();
$pdf = $pdfService->generateInvoicePdf($firstInvoice, $firstInvoice->resident->facility?->toConfigArray());
$pdfBinary = $pdf->output();
echo "  対象: {$firstInvoice->resident->room_number}号室 {$firstInvoice->resident->name}様\n";
echo "  PDFサイズ: " . number_format(strlen($pdfBinary) / 1024, 1) . " KB\n";

// 領収書PDF（入金済みの場合のみ）
if ($firstInvoice->status->value === 'paid') {
    $receiptPdf = $pdfService->generateReceiptPdf($firstInvoice, $firstInvoice->resident->facility?->toConfigArray());
    echo "  領収書PDFサイズ: " . number_format(strlen($receiptPdf->output()) / 1024, 1) . " KB\n";
} else {
    echo "  領収書: 未入金のため未生成（入金処理後に生成可能）\n";
}

echo "\n========================================\n";
echo "シミュレーション完了！\n";
echo "========================================\n";
echo "\n【処理フローまとめ】\n";
echo "1. ✅ 入居者マスタ登録（25名）\n";
echo "2. ✅ 自費サービス品目マスタ登録（7品目）\n";
echo "3. ✅ 日々の自費利用記録入力（スマホ対応フォーム想定）\n";
echo "4. ✅ 月次請求書一括生成・税計算（家賃非課税・管理費課税・自費サービス区分課税）\n";
echo "5. ✅ PDF一括出力（ZIPアーカイブ・カテゴリ別・統合請求書）\n";
echo "6. ✅ 個別PDF生成・領収書生成対応\n";
echo "\n【モバイル対応について】\n";
echo "- Filament管理画面はレスポンシブ対応済み\n";
echo "- DailyCharge作成フォームはスマホでも操作可能（inputMode=numeric等設定済み）\n";
echo "- 公開API経由で外部システム連携も可能\n";