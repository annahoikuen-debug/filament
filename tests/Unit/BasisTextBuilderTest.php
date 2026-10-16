<?php

use App\Enums\InvoiceStatus;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\Invoice\BasisTextBuilder;

beforeEach(function () {
    $this->builder = new BasisTextBuilder();
});

test('満月在籍の場合は満額計算表記を返すこと', function () {
    $resident = Resident::create([
        'room_number' => '101',
        'name' => '通常入居者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
    ]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 2000,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $text = $this->builder->build($invoice);

    expect($text)->toContain('満月在籍')
        ->and($text)->toContain('31日/31日')
        ->and($text)->toContain('¥60,000');
});

test('月中入居の場合は在籍日数と日割り計算式を返すこと', function () {
    $resident = Resident::create([
        'room_number' => '102',
        'name' => '途中入居者',
        'base_rent' => 62000,
        'base_management_fee' => 31000,
        'move_in_date' => '2026-10-16', // 10/16〜10/31 = 16日間
    ]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 32000,
        'management_fee_subtotal' => 16000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $text = $this->builder->build($invoice);

    expect($text)->toContain('日割り計算: 在籍 16日 / 当月 31日')
        ->and($text)->toContain('16/31');
});
