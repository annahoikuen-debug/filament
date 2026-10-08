<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\MonthlyInvoice;
use App\Models\Resident;

test('税関連のアトリビュートが正しく計算されること', function () {
    $resident = Resident::create([
        'room_number' => '601',
        'name' => '税計算テスト',
        'base_rent' => 50000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
    ]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 50000, // 非課税
        'management_fee_subtotal' => 30000, // 課税
        'service_subtotal' => 20000, // 課税
        'tax_rate' => 10,
        'status' => InvoiceStatus::Unbilled,
    ]);

    // アトリビュートの確算
    expect($invoice->non_taxable_amount)->toBe(50000);
    expect($invoice->taxable_amount)->toBe(50000); // 30000 + 20000
    expect((int) $invoice->tax_rate)->toBe(10);
    expect($invoice->tax_amount)->toBe(5000); // 50000 * 0.1
    expect($invoice->total_with_tax)->toBe(105000); // 50000 + 50000 + 5000
});

test('マークアズペイド後も税情報が保持されること', function () {
    $resident = Resident::create([
        'room_number' => '602',
        'name' => '入金後税テスト',
        'base_rent' => 40000,
        'base_management_fee' => 25000,
        'move_in_date' => '2026-01-01',
    ]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 40000,
        'management_fee_subtotal' => 25000,
        'service_subtotal' => 15000,
        'tax_rate' => 10,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $invoice->markAsPaid(PaymentMethod::BankTransfer);

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid);
    expect($invoice->non_taxable_amount)->toBe(40000);
    expect($invoice->taxable_amount)->toBe(40000); // 25000 + 15000
    expect((int) $invoice->tax_rate)->toBe(10);
    expect($invoice->tax_amount)->toBe(4000); // 40000 * 0.1
});
