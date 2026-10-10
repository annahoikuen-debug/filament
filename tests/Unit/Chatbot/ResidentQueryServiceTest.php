<?php

use App\Enums\InvoiceStatus;
use App\Models\DailyCharge;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Services\Chatbot\ResidentQueryService;

beforeEach(function () {
    $this->facility = Facility::factory()->create();
    $this->otherFacility = Facility::factory()->create();
    $this->admin = User::factory()->facilityAdmin()->create(['facility_id' => $this->facility->id]);
    $this->corporateAdmin = User::factory()->corporateAdmin()->create();
    $this->service = new ResidentQueryService;

    $this->resident = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '101',
        'name' => '佐藤花子',
        'name_kana' => 'サトウハナコ',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2025-04-01',
    ]);

    Resident::create([
        'facility_id' => $this->otherFacility->id,
        'room_number' => '201',
        'name' => '鈴木一郎',
        'base_rent' => 40000,
        'base_management_fee' => 15000,
        'move_in_date' => '2025-04-01',
    ]);
});

test('findResident が名前・カナ・部屋番号で検索できること', function () {
    expect($this->service->findResident('佐藤', $this->admin)?->id)->toBe($this->resident->id)
        ->and($this->service->findResident('サトウハナ', $this->admin)?->id)->toBe($this->resident->id)
        ->and($this->service->findResident('101', $this->admin)?->id)->toBe($this->resident->id)
        ->and($this->service->findResident('存在しない人名', $this->admin))->toBeNull();
});

test('facility_admin は他施設の入居者を検索できないこと', function () {
    expect($this->service->findResident('鈴木', $this->admin))->toBeNull()
        ->and($this->service->findResident('鈴木', $this->corporateAdmin)?->name)->toBe('鈴木一郎');
});

test('searchResidents が部分一致の入居者一覧を返すこと', function () {
    $results = $this->service->searchResidents('佐藤', $this->admin);

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('佐藤花子');

    // corporate admin は全施設（「一郎」に一致するのは鈴木一郎のみ）
    expect($this->service->searchResidents('一郎', $this->corporateAdmin))->toHaveCount(1)
        ->and($this->service->searchResidents('一郎', $this->corporateAdmin)->first()->name)->toBe('鈴木一郎');
});

test('getLatestInvoice が最新の請求書を返すこと', function () {
    expect($this->service->getLatestInvoice($this->resident))->toBeNull();

    MonthlyInvoice::create([
        'billing_year_month' => '2026-01',
        'resident_id' => $this->resident->id,
        'facility_id' => $this->facility->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => InvoiceStatus::Unbilled,
    ]);
    MonthlyInvoice::create([
        'billing_year_month' => '2026-02',
        'resident_id' => $this->resident->id,
        'facility_id' => $this->facility->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 71000,
        'status' => InvoiceStatus::Billed,
        'paid_at' => '2026-03-05',
    ]);

    $latest = $this->service->getLatestInvoice($this->resident);
    expect($latest?->billing_year_month)->toBe('2026-02');
});

test('getPaymentStatus が請求なしの場合に請求データなしを返すこと', function () {
    $status = $this->service->getPaymentStatus($this->resident);

    expect($status['has_invoice'])->toBeFalse()
        ->and($status['status'])->toBeNull()
        ->and($status['status_label'])->toBe('請求データなし')
        ->and($status['billing_year_month'])->toBeNull();
});

test('getPaymentStatus が請求ありの場合に状態を返すこと', function () {
    MonthlyInvoice::create([
        'billing_year_month' => '2026-02',
        'resident_id' => $this->resident->id,
        'facility_id' => $this->facility->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 71000,
        'status' => InvoiceStatus::Billed,
        'paid_at' => '2026-03-05',
    ]);

    $status = $this->service->getPaymentStatus($this->resident);

    expect($status['has_invoice'])->toBeTrue()
        ->and($status['status'])->toBe('billed')
        ->and($status['billing_year_month'])->toBe('2026-02')
        ->and($status['total_amount'])->toBe(70000) // オブザーバーが構成要素合計で再計算
        ->and($status['paid_at'])->toBe('2026/03/05');
});

test('getMonthlyDailyChargeTotal が月内の自費利用料合計を返すこと', function () {
    $item = \App\Models\ChargeItem::factory()->create(['tax_type' => \App\Enums\TaxType::NonTaxable, 'default_price' => 1000]);

    DailyCharge::create([
        'resident_id' => $this->resident->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $item->id,
        'date' => '2026-03-10',
        'unit_price' => 1000,
        'quantity' => 2,
    ]);
    DailyCharge::create([
        'resident_id' => $this->resident->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $item->id,
        'date' => '2026-03-15',
        'unit_price' => 1500,
        'quantity' => 1,
    ]);
    // 範囲外
    DailyCharge::create([
        'resident_id' => $this->resident->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $item->id,
        'date' => '2026-02-10',
        'unit_price' => 9999,
        'quantity' => 9,
    ]);

    expect($this->service->getMonthlyDailyChargeTotal($this->resident, '2026-03'))->toBe(3500);
});

test('residentNames が施設スコープ内の氏名一覧を返すこと', function () {
    $names = $this->service->residentNames($this->admin);

    expect($names)->toContain('佐藤花子')
        ->toContain('サトウハナコ')
        ->not->toContain('鈴木一郎');

    expect($this->service->residentNames($this->corporateAdmin))->toContain('鈴木一郎');
});

test('getProrationBasis が月全体在住の場合はnullを返すこと', function () {
    expect($this->service->getProrationBasis($this->resident, '2026-03'))->toBeNull();
});

test('getProrationBasis が月中途入居の場合に根拠データを返すこと', function () {
    $resident = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '102',
        'name' => '中途入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-03-11',
    ]);

    $basis = $this->service->getProrationBasis($resident, '2026-03');

    expect($basis)->toBeArray()
        ->and($basis['days_in_month'])->toBe(31)
        ->and($basis['start_day'])->toBe(11)
        ->and($basis['end_day'])->toBe(31)
        ->and($basis['active_days'])->toBe(21)
        ->and($basis['prorated_rent'])->toBe((int) round(50000 * 21 / 31))
        ->and($basis['prorated_management_fee'])->toBe((int) round(20000 * 21 / 31));
});

test('getProrationBasis が月中途退去の場合に根拠データを返すこと', function () {
    $resident = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '103',
        'name' => '中途退去者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2025-04-01',
        'move_out_date' => '2026-03-15',
    ]);

    $basis = $this->service->getProrationBasis($resident, '2026-03');

    expect($basis)->toBeArray()
        ->and($basis['start_day'])->toBe(1)
        ->and($basis['end_day'])->toBe(15)
        ->and($basis['active_days'])->toBe(15);
});
