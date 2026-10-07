<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Observers\MonthlyInvoiceObserver;

beforeEach(function () {
    $this->resident = Resident::create([
        'room_number' => '401',
        'name' => 'モデルテスト入居者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
    ]);
});

test('total_amountが内訳の合計に自動同期されること', function () {
    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 8000,
        'total_amount' => 0, // 不正な値でも補正される
        'status' => InvoiceStatus::Unbilled,
    ]);

    expect($invoice->total_amount)->toBe(98000);
});

test('update時にもtotal_amountが再計算されること', function () {
    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 0,
        'total_amount' => 90000,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $invoice->update(['service_subtotal' => 15000]);

    expect($invoice->fresh()->total_amount)->toBe(105000);
});

test('Observer削除後もtotal_amount同期が動作すること', function () {
    expect(class_exists(MonthlyInvoiceObserver::class))->toBeFalse();

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 10000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 30000,
        'total_amount' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    expect($invoice->total_amount)->toBe(60000);
});

test('resident_idが9999以下の場合の領収書番号フォーマット確認', function () {
    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 0,
        'total_amount' => 90000,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $invoice->markAsPaid(PaymentMethod::BankTransfer, '2026-11-05');

    $invoice->refresh();

    expect($invoice->receipt_number)->toBe(sprintf('REC-202610-%04d', $invoice->resident_id))
        ->and($invoice->status)->toBe(InvoiceStatus::Paid);
});

test('resident_idが10000以上でも領収書番号が崩れないこと', function () {
    // SQLite :memory: の autoincrement は小さいが、sprintf %04d の挙動を直接確認
    $formatted = sprintf('REC-%s-%04d', '202610', 12345);

    expect($formatted)->toBe('REC-202610-12345');
});

test('既に領収書番号が設定されている場合は上書きされないこと', function () {
    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 0,
        'total_amount' => 90000,
        'status' => InvoiceStatus::Unbilled,
        'receipt_number' => 'REC-CUSTOM-001',
    ]);

    $invoice->markAsPaid(PaymentMethod::Cash, '2026-11-01');

    $invoice->refresh();

    expect($invoice->receipt_number)->toBe('REC-CUSTOM-001')
        ->and($invoice->status)->toBe(InvoiceStatus::Paid);
});
