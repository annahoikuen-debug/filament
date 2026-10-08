<?php

use App\Models\RecurringCharge;
use App\Models\Resident;
use App\Models\Facility;
use App\Models\ChargeItem;
use Carbon\Carbon;

test('fillableフィールドのみが一括代入で設定されること', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);
    $chargeItem = ChargeItem::factory()->create();

    $recurringCharge = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    expect($recurringCharge->exists)->toBeTrue()
        ->and($recurringCharge->frequency)->toBe('monthly')
        ->and($recurringCharge->quantity)->toBe(1);
});

test('guardedがidのみで他のフィールドは一括代入可能であること', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);
    $chargeItem = ChargeItem::factory()->create();

    $recurringCharge = RecurringCharge::create([
        'id' => 99999,
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'start_date' => '2026-01-01',
        'is_active' => true,
    ]);

    expect($recurringCharge->id)->not->toBe(99999);
});

test('キャストが正しく動作すること', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);
    $chargeItem = ChargeItem::factory()->create();

    $recurringCharge = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'monthly',
        'quantity' => '2',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => 'true',
    ]);

    expect($recurringCharge->quantity)->toBe(2)
        ->and($recurringCharge->start_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($recurringCharge->end_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($recurringCharge->is_active)->toBeTrue();
});

test('residentリレーションが正しく動作すること', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);
    $chargeItem = ChargeItem::factory()->create();
    $recurringCharge = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    expect($recurringCharge->resident->id)->toBe($resident->id);
});

test('facilityリレーションが正しく動作すること', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);
    $chargeItem = ChargeItem::factory()->create();
    $recurringCharge = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    expect($recurringCharge->facility->id)->toBe($facility->id);
});

test('chargeItemリレーションが正しく動作すること', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);
    $chargeItem = ChargeItem::factory()->create();
    $recurringCharge = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    expect($recurringCharge->chargeItem->id)->toBe($chargeItem->id);
});

test('scopeActiveメソッドが有効な定期課金のみ取得すること', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);
    $chargeItem = ChargeItem::factory()->create();

    $active = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $inactive = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => false,
    ]);

    $future = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'start_date' => '2030-01-01',
        'is_active' => true,
    ]);

    $past = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'start_date' => '2020-01-01',
        'end_date' => '2020-12-31',
        'is_active' => true,
    ]);

    $activeCharges = RecurringCharge::active()->get();
    expect($activeCharges->count())->toBe(1)
        ->and($activeCharges->first()->id)->toBe($active->id);
});

test('scopeForYearMonthメソッドが指定月の有効な定期課金を取得すること', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);
    $chargeItem = ChargeItem::factory()->create();

    $inMonth = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
        'is_active' => true,
    ]);

    $beforeMonth = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'is_active' => true,
    ]);

    $afterMonth = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
        'is_active' => true,
    ]);

    $charges = RecurringCharge::forYearMonth('2026-10')->get();
    expect($charges->count())->toBe(1)
        ->and($charges->first()->id)->toBe($inMonth->id);
});

test('getApplicableDaysメソッドで月額の場合は月の日数を返すこと', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);
    $chargeItem = ChargeItem::factory()->create();

    $recurringCharge = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
        'is_active' => true,
    ]);

    $days = $recurringCharge->getApplicableDays('2026-10');
    expect($days)->toBe(31);
});

test('getApplicableDaysメソッドで日額の場合は期間日数を返すこと', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);
    $chargeItem = ChargeItem::factory()->create();

    $recurringCharge = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'daily',
        'quantity' => 1,
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-20',
        'is_active' => true,
    ]);

    $days = $recurringCharge->getApplicableDays('2026-10');
    expect($days)->toBe(11);
});

test('getApplicableDaysメソッドで入居日・退去日を考慮すること', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);
    $chargeItem = ChargeItem::factory()->create();

    $recurringCharge = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'daily',
        'quantity' => 1,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
        'is_active' => true,
    ]);

    $moveInDate = Carbon::create(2026, 10, 15);
    $moveOutDate = Carbon::create(2026, 10, 25);
    $days = $recurringCharge->getApplicableDays('2026-10', $moveInDate, $moveOutDate);
    expect($days)->toBe(11);
});

test('getApplicableDaysメソッドで開始日が終了日より後なら0を返すこと', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);
    $chargeItem = ChargeItem::factory()->create();

    $recurringCharge = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $chargeItem->id,
        'frequency' => 'daily',
        'quantity' => 1,
        'start_date' => '2026-10-20',
        'end_date' => '2026-10-10',
        'is_active' => true,
    ]);

    $days = $recurringCharge->getApplicableDays('2026-10');
    expect($days)->toBe(0);
});