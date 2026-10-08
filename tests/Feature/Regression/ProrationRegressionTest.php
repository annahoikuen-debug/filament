<?php

use App\Enums\ResidentStatus;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;

test('既存の機能（満月在籍者）が変更されないこと', function () {
    // 1ヶ月まるまる在籍している場合のテスト
    $resident = Resident::create([
        'room_number' => '501',
        'name' => '満月在籍者',
        'base_rent' => 50000,
        'base_management_fee' => 25000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-09-01', // 以前から在籍
        'move_out_date' => null,
    ]);

    $service = app(InvoiceCalculationService::class);
    $stats = $service->generateForMonth('2026-10');

    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-10')
        ->first();

    expect($invoice->rent_subtotal)->toBe(50000);
    expect($invoice->management_fee_subtotal)->toBe(25000);

    // 総居住者数と作成数が正しいことを確認
    expect($stats['total_residents'])->toBe(1);
    expect($stats['created'])->toBe(1);
});

test('入居日・退去日が未設定の場合の動作が変更されないこと', function () {
    $resident = Resident::create([
        'room_number' => '502',
        'name' => '日付未設定者',
        'base_rent' => 45000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => null,
        'move_out_date' => null,
    ]);

    $service = app(InvoiceCalculationService::class);
    $stats = $service->generateForMonth('2026-10');

    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-10')
        ->first();

    // 日付未設定の場合は満額請求される（既存動作の維持）
    expect($invoice->rent_subtotal)->toBe(45000);
    expect($invoice->management_fee_subtotal)->toBe(20000);
});
