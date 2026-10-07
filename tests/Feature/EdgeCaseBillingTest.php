<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ResidentStatus;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;

beforeEach(function () {
    $this->service = new InvoiceCalculationService;
    $this->chargeItem = ChargeItem::create([
        'name' => '自費サービス',
        'default_price' => 1000,
    ]);
});

test('月途中入居（10月15日入居）の10月請求が正しく生成されること', function () {
    $resident = Resident::create([
        'room_number' => '1201',
        'name' => '月途中入居テスト',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-10-15',
    ]);

    $stats = $this->service->generateForMonth('2026-10');

    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-10')
        ->first();

    // 10月15日〜31日で17日間在籍 → 日割り計算
    // 家賃: 60000 * 17/31 = 32903, 管理費: 30000 * 17/31 = 16452
    expect($invoice)->not->toBeNull()
        ->and($invoice->rent_subtotal)->toBe(32903)
        ->and($invoice->management_fee_subtotal)->toBe(16452)
        ->and($invoice->total_amount)->toBe(49355) // 32903 + 16452
        ->and($stats['created'])->toBe(1);
});

test('月途中退去（10月15日退去）の10月請求が正しく生成されること', function () {
    $resident = Resident::create([
        'room_number' => '1202',
        'name' => '月途中退去テスト',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2025-04-01',
        'move_out_date' => '2026-10-15',
    ]);

    $stats = $this->service->generateForMonth('2026-10');

    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-10')
        ->first();

    expect($invoice)->not->toBeNull()
        ->and($stats['created'])->toBe(1);
});

test('入居日と退去日が同じ日でも請求が生成されること', function () {
    $resident = Resident::create([
        'room_number' => '1203',
        'name' => '同日入退居テスト',
        'base_rent' => 40000,
        'base_management_fee' => 10000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-10-20',
        'move_out_date' => '2026-10-20',
    ]);

    $stats = $this->service->generateForMonth('2026-10');

    expect($stats['created'])->toBe(1);
});

test('forceUpdate=trueで請求済データも上書き再計算されること', function () {
    $resident = Resident::create([
        'room_number' => '1204',
        'name' => '強制更新テスト',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    // 最初に生成
    $this->service->generateForMonth('2026-10');

    // 請求済に変更
    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-10')
        ->first();
    $invoice->update(['status' => InvoiceStatus::Billed]);

    // 自費記録を追加
    DailyCharge::create([
        'resident_id' => $resident->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-10',
        'unit_price' => 2000,
        'quantity' => 3,
    ]);

    // forceUpdate=false → スキップ
    $stats = $this->service->generateForMonth('2026-10', false);
    expect($stats['skipped'])->toBe(1)
        ->and($stats['updated'])->toBe(0);

    // forceUpdate=true → 更新
    $stats = $this->service->generateForMonth('2026-10', true);
    expect($stats['updated'])->toBe(1);

    $invoice->refresh();
    expect($invoice->service_subtotal)->toBe(6000)
        ->and($invoice->total_amount)->toBe(76000);
});

test('請求ステータスがUnbilled→Billed→Paidの順に遷移すること', function () {
    $resident = Resident::create([
        'room_number' => '1205',
        'name' => 'ステータス遷移テスト',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => InvoiceStatus::Unbilled,
    ]);

    expect($invoice->status)->toBe(InvoiceStatus::Unbilled);

    $invoice->update(['status' => InvoiceStatus::Billed]);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Billed);

    $invoice->fresh()->markAsPaid(PaymentMethod::BankTransfer, '2026-11-05');
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('入金済み請求書の再入金消込はstatusがPaidのまま維持されること', function () {
    $resident = Resident::create([
        'room_number' => '1206',
        'name' => '再入金テスト',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => InvoiceStatus::Unbilled,
    ]);

    // 1回目の入金消込
    $invoice->markAsPaid(PaymentMethod::BankTransfer, '2026-11-05');
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->paid_at->format('Y-m-d'))->toBe('2026-11-05');

    // 2回目の入金消込（日付更新されるがstatusはPaid維持）
    $invoice->fresh()->markAsPaid(PaymentMethod::Cash, '2026-11-10');
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->paid_at->format('Y-m-d'))->toBe('2026-11-10');
});

test('月途中入居者の自費記録が入居日前の場合はDomainExceptionとなること', function () {
    $resident = Resident::create([
        'room_number' => '1207',
        'name' => '自費期間外テスト',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-10-15',
    ]);

    expect(fn () => DailyCharge::create([
        'resident_id' => $resident->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-10',
        'unit_price' => 1000,
        'quantity' => 1,
    ]))->toThrow(DomainException::class);
});
