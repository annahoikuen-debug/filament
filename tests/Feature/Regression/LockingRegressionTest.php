<?php

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;

test('楽観ロック実装後も既存の請求生成機能が正常に動作すること', function () {
    $resident = Resident::create([
        'room_number' => '1301',
        'name' => 'バージョンリグレッションテスト',
        'base_rent' => 50000,
        'base_management_fee' => 25000,
        'move_in_date' => '2026-01-01',
    ]);

    $service = app(InvoiceCalculationService::class);
    $stats = $service->generateForMonth('2026-10');

    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-10')
        ->first();

    expect($invoice)->not->toBeNull();
    expect($invoice->rent_subtotal)->toBe(50000);
    expect($invoice->management_fee_subtotal)->toBe(25000);
    expect($invoice->service_subtotal)->toBe(0);
    expect($invoice->total_amount)->toBe(75000);

    // versionカラムが正しく設定されていることを確認
    expect($invoice->version)->toBe(0);

    // 複数回実行しても問題がないことを確認
    $stats2 = $service->generateForMonth('2026-10', true); // forceUpdate

    $invoice->refresh();
    expect($invoice->version)->toBeGreaterThan(0);
    expect($stats2['updated'])->toBe(1);
});

test('既存データの更新時にも楽観ロックが適用されずに正常に動作すること', function () {
    $resident = Resident::create([
        'room_number' => '1302',
        'name' => '更新リグレッションテスト',
        'base_rent' => 40000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    // 自費記録を作成して service_subtotal = 1000 になるようにする
    $chargeItem = ChargeItem::create([
        'name' => 'テストサービス',
        'default_price' => 1000,
    ]);

    DailyCharge::create([
        'resident_id' => $resident->id,
        'charge_item_id' => $chargeItem->id,
        'date' => '2026-10-01',
        'unit_price' => 1000,
        'quantity' => 1,
    ]);

    // 既存請求データを作成
    MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 40000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 1000,
        'status' => InvoiceStatus::Unbilled,
        'version' => 1,
    ]);

    $service = app(InvoiceCalculationService::class);
    $stats = $service->generateForMonth('2026-10');

    expect($stats['updated'])->toBe(1);
    expect($stats['created'])->toBe(0);

    $invoice = MonthlyInvoice::where('billing_year_month', '2026-10')
        ->first();

    expect($invoice->rent_subtotal)->toBe(40000);
    expect($invoice->management_fee_subtotal)->toBe(20000);
    // サービス小計が変更されていないので同じ値のはず
    expect($invoice->service_subtotal)->toBe(1000);
    // バージョンがインクリメントされているはず
    expect($invoice->version)->toBe(2);
});
