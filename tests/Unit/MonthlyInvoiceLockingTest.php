<?php

use App\Enums\InvoiceStatus;
use App\Models\MonthlyInvoice;
use App\Models\Resident;

test('バージョンカラムが追加され、新規作成時に0で初期化されること', function () {
    $resident = Resident::create([
        'room_number' => '1101',
        'name' => 'ロックテスト入居者',
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

    $invoice->refresh();

    expect($invoice->version)->toBe(0);
});

test('更新時にバージョンがインクリメントされること', function () {
    $resident = Resident::create([
        'room_number' => '1102',
        'name' => 'ロック更新テスト',
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

    $initialVersion = $invoice->version;
    expect($initialVersion)->toBe(0);

    // 更新を実行
    $invoice->update(['service_subtotal' => 5000]);

    $invoice->refresh();
    expect($invoice->version)->toBe($initialVersion + 1);
    expect($invoice->version)->toBe(1);
});

test('複数回更新時にバージョンが正しくインクリメントされること', function () {
    $resident = Resident::create([
        'room_number' => '1103',
        'name' => '複数ロックテスト',
        'base_rent' => 30000,
        'base_management_fee' => 15000,
        'move_in_date' => '2026-01-01',
    ]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 30000,
        'management_fee_subtotal' => 15000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    expect($invoice->version)->toBe(0);

    // 3回更新
    for ($i = 0; $i < 3; $i++) {
        $invoice->update([
            'service_subtotal' => 1000 * ($i + 1),
        ]);
        $invoice->refresh();
    }

    expect($invoice->version)->toBe(3);
});
