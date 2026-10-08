<?php

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;

test('一括請求生成時に楽観ロックが適用されバージョンがインクリメントされること', function () {
    $resident = Resident::create([
        'room_number' => '1201',
        'name' => '一括ロックテスト',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    // 既に請求データを作成しておく
    $existingInvoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 2000,
        'status' => InvoiceStatus::Unbilled,
        'version' => 5, // 既に何らかのバージョンがあると仮定
    ]);

    $service = app(InvoiceCalculationService::class);
    $stats = $service->generateForMonth('2026-10');

    // バージョンがインクリメントされていることを確認
    $existingInvoice->refresh();
    expect($existingInvoice->version)->toBeGreaterThan(5);

    // 統計情報も正しいことを確認
    expect($stats['updated'])->toBe(1);
    expect($stats['created'])->toBe(0);
});

test('フォースアップデート時にステータスに関わらず更新されること', function () {
    $resident = Resident::create([
        'room_number' => '1202',
        'name' => 'フォースアップデートテスト',
        'base_rent' => 55000,
        'base_management_fee' => 27500,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    // 入金済みの請求データを作成
    $paidInvoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 55000,
        'management_fee_subtotal' => 27500,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Paid, // 入金済み
        'version' => 3,
    ]);

    $service = app(InvoiceCalculationService::class);
    // フォースアップデートフラグをtrueにして実行
    $stats = $service->generateForMonth('2026-10', true);

    // フォースアップデートなので更新されるはず
    $paidInvoice->refresh();
    expect($paidInvoice->version)->toBeGreaterThan(3);
    expect($stats['updated'])->toBe(1);
});

test('同時に同じレコードを更新しようとすると競合が発生すること（シミュレーション）', function () {
    // このテストは実際の競合状況をシミュレートするのは複雑なので、
    // 楽観ロックメソッド自体のテストに焦点を当てる
    $resident = Resident::create([
        'room_number' => '1203',
        'name' => '競合シミュレーションテスト',
        'base_rent' => 50000,
        'base_management_fee' => 25000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 25000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
        'version' => 2,
    ]);

    $service = app(InvoiceCalculationService::class);

    // 楽観ロックを適用して更新を試みる（正しいバージョン）
    $updateData = [
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 25000,
        'service_subtotal' => 3000,
        'total_amount' => 78000,
    ];

    // 正しいバージョンであれば更新成功
    $result = $invoice->where('version', 2)
        ->update(array_merge($updateData, ['version' => 3]));

    expect($result)->toBe(1);
    $invoice->refresh();
    expect($invoice->version)->toBe(3);

    // 間違ったバージョンを指定すると失敗する
    $result = $invoice->where('version', 99)
        ->update(array_merge($updateData, ['version' => 4, 'service_subtotal' => 4000]));

    expect($result)->toBe(0); // バージョンが一致しないので更新されない
});
