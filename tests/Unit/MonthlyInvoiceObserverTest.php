<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\MonthlyInvoice;
use App\Models\Resident;

test('MonthlyInvoice保存時に内訳の合算値が常にtotal_amountに自動同期されること', function () {
    $resident = Resident::create([
        'room_number' => '102',
        'name' => 'テスト入居者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
    ]);

    // total_amount にあえてデタラメな値(999999)を入れて保存しても補正されるか
    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 5000,
        'total_amount' => 999999, // 不正な値
        'status' => InvoiceStatus::Unbilled,
    ]);

    // 60000 + 30000 + 5000 = 95000 に自動計算されること
    expect($invoice->total_amount)->toBe(95000);

    // update 時に内訳を変更した場合も再同期されること
    $invoice->update([
        'service_subtotal' => 12000,
    ]);

    // 60000 + 30000 + 12000 = 102000
    expect($invoice->fresh()->total_amount)->toBe(102000);
});

test('markAsPaidメソッドで入金消込と領収書情報が正しく記録されること', function () {
    $resident = Resident::create([
        'room_number' => '103',
        'name' => '入金テスト者',
        'move_in_date' => '2026-01-01',
    ]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $invoice->markAsPaid(PaymentMethod::BankTransfer, '2026-11-05');

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->paid_at->format('Y-m-d'))->toBe('2026-11-05')
        ->and($invoice->payment_method)->toBe(PaymentMethod::BankTransfer)
        ->and($invoice->receipt_number)->toBe(sprintf('REC-202610-%04d', $resident->id))
        ->and($invoice->receipt_issued_at)->not->toBeNull();
});
