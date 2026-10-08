<?php

use App\Enums\ResidentStatus;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;

test('月中入居者の請求が日割り計算されること', function () {
    $resident = Resident::create([
        'room_number' => '401',
        'name' => '月中入居者',
        'base_rent' => 62000,
        'base_management_fee' => 28000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-10-15', // 10月15日入居
        'move_out_date' => null,
    ]);

    $service = app(InvoiceCalculationService::class);
    $stats = $service->generateForMonth('2026-10');

    // 10月31日間中17日在籍 (15日〜31日)
    // 期待値: 家賃 62000 * 17/31 = 34000, 管理費 28000 * 17/31 = 15355
    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-10')
        ->first();

    expect($invoice)->not->toBeNull();
    // 端数処理により多少異なる可能性があるため、範囲でチェック
    expect($invoice->rent_subtotal)->toBeGreaterThanOrEqual(33900);
    expect($invoice->rent_subtotal)->toBeLessThanOrEqual(34100);
    expect($invoice->management_fee_subtotal)->toBeGreaterThanOrEqual(15300);
    expect($invoice->management_fee_subtotal)->toBeLessThanOrEqual(15410);
});

test('月中退去者の請求が日割り計算されること', function () {
    $resident = Resident::create([
        'room_number' => '402',
        'name' => '月中退去者',
        'base_rent' => 55000,
        'base_management_fee' => 25000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-04-01',
        'move_out_date' => '2026-10-10', // 10月10日退去
    ]);

    // 在籍期間内の自費記録も作成
    $chargeItem = ChargeItem::create([
        'name' => 'テストサービス',
        'default_price' => 1000,
    ]);

    DailyCharge::create([
        'resident_id' => $resident->id,
        'charge_item_id' => $chargeItem->id,
        'date' => '2026-10-05', // 在籍期間内
        'unit_price' => 1000,
        'quantity' => 2,
    ]);

    $service = app(InvoiceCalculationService::class);
    $stats = $service->generateForMonth('2026-10');

    // 10月1日〜10日で10日在籍
    // 期待値: 家賃 55000 * 10/31 = 17742, 管理費 25000 * 10/31 = 8065
    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-10')
        ->first();

    expect($invoice)->not->toBeNull();
    expect($invoice->rent_subtotal)->toBeGreaterThanOrEqual(17700);
    expect($invoice->rent_subtotal)->toBeLessThanOrEqual(17800);
    expect($invoice->management_fee_subtotal)->toBeGreaterThanOrEqual(8000);
    expect($invoice->management_fee_subtotal)->toBeLessThanOrEqual(8100);
    // 自費: 1000 * 2 = 2000 (全額、在籍期間内のため)
    expect($invoice->service_subtotal)->toBe(2000);
});

test('月の最初日に入居した場合は満額請求されること', function () {
    $resident = Resident::create([
        'room_number' => '403',
        'name' => '月初入居者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-10-01',
        'move_out_date' => null,
    ]);

    $service = app(InvoiceCalculationService::class);
    $stats = $service->generateForMonth('2026-10');

    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-10')
        ->first();

    expect($invoice->rent_subtotal)->toBe(60000);
    expect($invoice->management_fee_subtotal)->toBe(30000);
});

test('月の最後に退去した場合は満額請求されること', function () {
    $resident = Resident::create([
        'room_number' => '404',
        'name' => '月末退去者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
        'move_out_date' => '2026-10-31',
    ]);

    $service = app(InvoiceCalculationService::class);
    $stats = $service->generateForMonth('2026-10');

    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-10')
        ->first();

    expect($invoice->rent_subtotal)->toBe(60000);
    expect($invoice->management_fee_subtotal)->toBe(30000);
});
