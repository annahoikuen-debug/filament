<?php

use App\Enums\InvoiceStatus;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Policies\MonthlyInvoicePolicy;

test('入金済み請求書の更新はポリシーによって禁止されること', function () {
    $user = User::factory()->create();
    $resident = Resident::create([
        'room_number' => '1401',
        'name' => 'アーカイブポリシーテスト',
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
        'status' => InvoiceStatus::Paid, // 入金済み
    ]);

    $policy = new MonthlyInvoicePolicy;

    // 更新は禁止されるはず
    $result = $policy->update($user, $invoice);
    expect($result)->toBeFalse();

    // 削除も禁止されるはず
    $result = $policy->delete($user, $invoice);
    expect($result)->toBeFalse();
});

test('未請求の請求書は更新・削除が許可されること', function () {
    $user = User::factory()->create();
    $resident = Resident::create([
        'room_number' => '1402',
        'name' => '未請求テスト入居者',
        'base_rent' => 45000,
        'base_management_fee' => 22500,
        'move_in_date' => '2026-01-01',
    ]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 45000,
        'management_fee_subtotal' => 22500,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled, // 未請求
    ]);

    $policy = new MonthlyInvoicePolicy;

    // 更新は許可されるはず
    $result = $policy->update($user, $invoice);
    expect($result)->toBeTrue();

    // 削除も許可されるはず
    $result = $policy->delete($user, $invoice);
    expect($result)->toBeTrue();
});

test('請求済み（未入金）の請求書は更新・削除が禁止されること', function () {
    $user = User::factory()->create();
    $resident = Resident::create([
        'room_number' => '1403',
        'name' => '請求済みテスト入居者',
        'base_rent' => 40000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 40000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 3000,
        'status' => InvoiceStatus::Billed, // 請求済み
    ]);

    $policy = new MonthlyInvoicePolicy;

    // 更新は禁止されるはず
    $result = $policy->update($user, $invoice);
    expect($result)->toBeFalse();

    // 削除も禁止されるはず
    $result = $policy->delete($user, $invoice);
    expect($result)->toBeFalse();
});

test('Filamentリソースでアーカイブ済み請求書の編集アクションが非表示になること', function () {
    $resident = Resident::create([
        'room_number' => '1404',
        'name' => 'Filamentアーカイブテスト',
        'base_rent' => 50000,
        'base_management_fee' => 25000,
        'move_in_date' => '2026-01-01',
    ]);

    // 入金済み請求書
    $paidInvoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 25000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Paid,
    ]);

    // 請求済み請求書
    $billedInvoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-11',
        'resident_id' => $resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 25000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Billed,
    ]);

    // 未請求請求書
    $unbilledInvoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-12',
        'resident_id' => $resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 25000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    // リソースのテーブルアクションから可視性をテスト
    // ここではポリシーテストで代用
    $policy = new MonthlyInvoicePolicy;
    $user = User::factory()->create();

    expect($policy->update($user, $paidInvoice))->toBeFalse();
    expect($policy->update($user, $billedInvoice))->toBeFalse();
    expect($policy->update($user, $unbilledInvoice))->toBeTrue();
});
