<?php

use App\Enums\ResidentStatus;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;

beforeEach(function () {
    $this->service = app(InvoiceCalculationService::class);
    $this->chargeItem = ChargeItem::create([
        'name' => '自費サービス',
        'default_price' => 1000,
    ]);
});

test('月途中入居（15日入居）の住民が対象月の請求生成に含まれること', function () {
    Resident::create([
        'room_number' => '201',
        'name' => '月中入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-10-15',
    ]);

    $stats = $this->service->generateForMonth('2026-10');

    expect($stats['total_residents'])->toBe(1)
        ->and($stats['created'])->toBe(1);

    $invoice = MonthlyInvoice::where('billing_year_month', '2026-10')->first();
    expect($invoice)->not->toBeNull()
        // 10月15日〜31日で17日間在籍 → 日割り計算
        // 家賃: 50000 * 17/31 = 27419, 管理費: 20000 * 17/31 = 10968
        ->and($invoice->total_amount)->toBe(38387);
});

test('月途中退去（15日退去）の住民が対象月の請求生成に含まれること', function () {
    Resident::create([
        'room_number' => '202',
        'name' => '月中退去者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2025-01-01',
        'move_out_date' => '2026-10-15',
    ]);

    $stats = $this->service->generateForMonth('2026-10');

    expect($stats['total_residents'])->toBe(1)
        ->and($stats['created'])->toBe(1);
});

test('対象月より前に退去した住民は請求生成に含まれないこと', function () {
    Resident::create([
        'room_number' => '203',
        'name' => '前月退去者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2025-01-01',
        'move_out_date' => '2026-09-30',
    ]);

    $stats = $this->service->generateForMonth('2026-10');

    expect($stats['total_residents'])->toBe(0)
        ->and($stats['created'])->toBe(0);
});

test('対象月より後に入居した住民は請求生成に含まれないこと', function () {
    Resident::create([
        'room_number' => '204',
        'name' => '来月入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-11-01',
    ]);

    $stats = $this->service->generateForMonth('2026-10');

    expect($stats['total_residents'])->toBe(0)
        ->and($stats['created'])->toBe(0);
});

test('未来日付（まだ入居していない月）でも月内入居者は正しく処理されること', function () {
    Resident::create([
        'room_number' => '205',
        'name' => '月末日入居者',
        'base_rent' => 40000,
        'base_management_fee' => 10000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-10-31',
    ]);

    $stats = $this->service->generateForMonth('2026-10');

    expect($stats['created'])->toBe(1);
});

test('月途中退去者の自費記録は退去日までのみ集計されること', function () {
    $resident = Resident::create([
        'room_number' => '206',
        'name' => '退去者自費テスト',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2025-01-01',
        'move_out_date' => '2026-10-15',
    ]);

    // 在籍期間内の記録のみ作成
    DailyCharge::create([
        'resident_id' => $resident->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-01',
        'unit_price' => 1000,
        'quantity' => 1,
    ]);

    $stats = $this->service->generateForMonth('2026-10');

    $invoice = MonthlyInvoice::where('billing_year_month', '2026-10')->first();
    expect($invoice->service_subtotal)->toBe(1000)
        // 10月1日〜15日で15日間在籍 → 日割り計算
        // 家賃: 50000 * 15/31 = 24194, 管理費: 20000 * 15/31 = 9677
        // 自費: 1000 (在籍期間内)
        ->and($invoice->total_amount)->toBe(34871);
});
