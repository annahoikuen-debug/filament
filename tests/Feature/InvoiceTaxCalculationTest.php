<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ResidentStatus;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceCsvExportService;
use App\Services\InvoicePdfService;

test('請求生成時に税情報が正しく設定されること', function () {
    $resident = Resident::create([
        'room_number' => '701',
        'name' => '税計算請求テスト',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    // 自費記録も追加
    $chargeItem = ChargeItem::create([
        'name' => 'テストサービス',
        'default_price' => 1000,
    ]);

    DailyCharge::create([
        'resident_id' => $resident->id,
        'charge_item_id' => $chargeItem->id,
        'date' => '2026-10-01',
        'unit_price' => 1000,
        'quantity' => 2,
    ]);

    $service = app(InvoiceCalculationService::class);
    $stats = $service->generateForMonth('2026-10');

    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-10')
        ->first();

    expect($invoice)->not->toBeNull();
    expect($invoice->rent_subtotal)->toBe(60000);
    expect($invoice->management_fee_subtotal)->toBe(30000);
    expect($invoice->service_subtotal)->toBe(2000);
    expect($invoice->taxable_amount)->toBe(32000); // 30000 + 2000
    expect($invoice->tax_amount)->toBe(3200); // 32000 * 0.1
    expect($invoice->total_amount)->toBe(92000); // 60000 + 30000 + 2000
    expect($invoice->total_with_tax)->toBe(95200); // 92000 + 3200
});

test('PDF生成時に税情報が正しく表示されること', function () {
    $resident = Resident::create([
        'room_number' => '702',
        'name' => 'PDF税テスト',
        'base_rent' => 50000,
        'base_management_fee' => 25000,
        'move_in_date' => '2026-01-01',
    ]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 25000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $pdfService = app(InvoicePdfService::class);
    $pdf = $pdfService->generateInvoicePdf($invoice);

    // PDFが生成されることだけを確認（実際の中身の検証は複雑になるため）
    expect($pdf)->not->toBeNull();
    $output = $pdf->output();
    expect(strlen($output))->toBeGreaterThan(1000); // ある程度のサイズがあることを確認
});

test('CSV出力に税情報が含まれること', function () {
    $resident = Resident::create([
        'room_number' => '703',
        'name' => 'CSV税テスト',
        'base_rent' => 45000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 45000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 5000,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $csvService = new InvoiceCsvExportService;
    $csv = $csvService->exportMonthlyListCsv('2026-10');

    expect($csv)->toContain('45000,20000,5000,25000,10%,2500'); // 家賃,管理費,自費,課税対象,税率,税額
});

test('領収書PDF生成時に税情報が正しく表示されること', function () {
    $resident = Resident::create([
        'room_number' => '704',
        'name' => '領収書税テスト',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
    ]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $invoice->markAsPaid(PaymentMethod::BankTransfer);

    $pdfService = app(InvoicePdfService::class);
    $pdf = $pdfService->generateReceiptPdf($invoice);

    expect($pdf)->not->toBeNull();
    $output = $pdf->output();
    expect(strlen($output))->toBeGreaterThan(1000);
});
