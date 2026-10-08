<?php

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;

beforeEach(function () {
    $this->service = app()->make(InvoiceCalculationService::class);
});

test('入居中Resident25名分の月次請求書が一括で正しく生成されること', function () {
    // 25名の入居者を準備
    for ($i = 1; $i <= 25; $i++) {
        Resident::create([
            'room_number' => (string) (100 + $i),
            'name' => "入居者 {$i}",
            'base_rent' => 60000,
            'base_management_fee' => 30000,
            'status' => ResidentStatus::Active,
            'move_in_date' => '2026-01-01',
        ]);
    }

    $result = $this->service->generateForMonth('2026-10');

    expect($result['created'])->toBe(25)
        ->and($result['total_residents'])->toBe(25);

    expect(MonthlyInvoice::count())->toBe(25);

    $firstInvoice = MonthlyInvoice::where('billing_year_month', '2026-10')->first();
    expect($firstInvoice->rent_subtotal)->toBe(60000)
        ->and($firstInvoice->management_fee_subtotal)->toBe(30000)
        ->and($firstInvoice->service_subtotal)->toBe(0)
        ->and($firstInvoice->total_amount)->toBe(90000)
        ->and($firstInvoice->status)->toBe(InvoiceStatus::Unbilled);
});

test('日々の自費記録(daily_charges)が正確に合算集計されること', function () {
    $resident = Resident::create([
        'room_number' => '101',
        'name' => '山田 太郎',
        'base_rent' => 65000,
        'base_management_fee' => 30000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    $item1 = ChargeItem::create(['name' => '紙おむつ', 'default_price' => 200]);
    $item2 = ChargeItem::create(['name' => '理美容', 'default_price' => 2000]);

    // 当月の自費記録: 200円 × 3個 = 600円
    DailyCharge::create([
        'resident_id' => $resident->id,
        'charge_item_id' => $item1->id,
        'date' => '2026-10-05',
        'unit_price' => 200,
        'quantity' => 3,
    ]);

    // 当月の自費記録: 2000円 × 1個 = 2000円
    DailyCharge::create([
        'resident_id' => $resident->id,
        'charge_item_id' => $item2->id,
        'date' => '2026-10-15',
        'unit_price' => 2000,
        'quantity' => 1,
    ]);

    // 前月の自費記録（集計対象外となるべき）
    DailyCharge::create([
        'resident_id' => $resident->id,
        'charge_item_id' => $item1->id,
        'date' => '2026-09-30',
        'unit_price' => 200,
        'quantity' => 5,
    ]);

    $this->service->generateForMonth('2026-10');

    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-10')
        ->first();

    // 自費小計: 600 + 2000 = 2600円
    expect($invoice->service_subtotal)->toBe(2600)
        ->and($invoice->rent_subtotal)->toBe(65000)
        ->and($invoice->management_fee_subtotal)->toBe(30000)
        // 合計: 65000 + 30000 + 2600 = 97600円
        ->and($invoice->total_amount)->toBe(97600);
});

test('請求済・入金済の請求書は通常スキップされ上書きされないこと', function () {
    $resident = Resident::create([
        'room_number' => '101',
        'name' => '佐藤 一郎',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    // 既に「請求済」の確定レコードを作成しておく
    MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => InvoiceStatus::Billed,
    ]);

    // 家賃設定を変更
    $resident->update(['base_rent' => 80000]);

    // 通常の再計算
    $result = $this->service->generateForMonth('2026-10', forceUpdate: false);

    expect($result['skipped'])->toBe(1);

    // 金額が保護されて上書きされていないこと
    $invoice = MonthlyInvoice::where('resident_id', $resident->id)->first();
    expect($invoice->rent_subtotal)->toBe(50000);

    // 強制更新フラグをONにした場合
    $this->service->generateForMonth('2026-10', forceUpdate: true);
    $invoice->refresh();
    expect($invoice->rent_subtotal)->toBe(80000);
});
