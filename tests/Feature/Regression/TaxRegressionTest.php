<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;

test('税関連カラム追加後も既存の合計金額計算が変わらないこと', function () {
    $resident = Resident::create([
        'room_number' => '801',
        'name' => '税リグレッションテスト',
        'base_rent' => 50000,
        'base_management_fee' => 25000,
        'move_in_date' => '2026-01-01',
    ]);

    $service = app(InvoiceCalculationService::class);
    $stats = $service->generateForMonth('2026-10');

    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-10')
        ->first();

    // 合計金額の計算は変更されないことを確認
    expect($invoice->total_amount)->toBe(75000); // 50000 + 25000

    // 新しい税関連フィールドも正しく設定されていることを確認
    expect($invoice->taxable_amount)->toBe(25000);
    expect($invoice->tax_amount)->toBe(2500);
    expect((int) $invoice->tax_rate)->toBe(10);
});

test('マークアズペイド機能が税情報を壊さないこと', function () {
    $resident = Resident::create([
        'room_number' => '802',
        'name' => '入金後税リグレッション',
        'base_rent' => 40000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 40000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $invoice->markAsPaid(PaymentMethod::BankTransfer);

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid);
    expect($invoice->total_amount)->toBe(60000); // 変更なし
    expect($invoice->taxable_amount)->toBe(20000);
    expect($invoice->tax_amount)->toBe(2000);
});
