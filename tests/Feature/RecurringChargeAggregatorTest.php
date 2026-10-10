<?php

use App\Enums\TaxType;
use App\Models\ChargeItem;
use App\Models\Facility;
use App\Models\RecurringCharge;
use App\Models\Resident;
use App\Services\Invoice\RecurringChargeAggregator;
use Illuminate\Support\Carbon;

function recurringSetup(): array
{
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);

    return [$facility, $resident];
}

it('aggregates monthly recurring charges', function () {
    [, $resident] = recurringSetup();

    $item = ChargeItem::factory()->create([
        'tax_type' => TaxType::Standard,
        'default_price' => 50000,
    ]);

    RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $resident->facility_id,
        'charge_item_id' => $item->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'unit_price' => 50000,
        'start_date' => '2026-01-01',
        'end_date' => null,
        'is_active' => true,
    ]);

    $aggregator = new RecurringChargeAggregator;
    $result = $aggregator->aggregate([$resident->id], '2026-03', Carbon::parse('2026-03-27'));

    expect($result[$resident->id]['standard'])->toBe(50000)
        ->and($result[$resident->id]['reduced'])->toBe(0)
        ->and($result[$resident->id]['non_taxable'])->toBe(0);
});

it('aggregates daily recurring charges with mid-month range', function () {
    [, $resident] = recurringSetup();

    $item = ChargeItem::factory()->create([
        'tax_type' => TaxType::Reduced,
        'default_price' => 2000,
    ]);

    RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $resident->facility_id,
        'charge_item_id' => $item->id,
        'frequency' => 'daily',
        'quantity' => 1,
        'unit_price' => 2000,
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-12',
        'is_active' => true,
    ]);

    $aggregator = new RecurringChargeAggregator;
    $result = $aggregator->aggregate([$resident->id], '2026-03', Carbon::parse('2026-03-27'));

    // 3日間 × 2000円
    expect($result[$resident->id]['reduced'])->toBe(6000);
});

it('returns zero structure for empty resident ids', function () {
    $aggregator = new RecurringChargeAggregator;

    $result = $aggregator->aggregate([], '2026-03', Carbon::parse('2026-03-27'));

    expect($result)->toBeArray()->toBeEmpty();
});

it('initializes zero for residents without charges', function () {
    [, $resident] = recurringSetup();

    $aggregator = new RecurringChargeAggregator;
    $result = $aggregator->aggregate([$resident->id], '2026-03', Carbon::parse('2026-03-27'));

    expect($result[$resident->id])->toBe([
        'standard' => 0,
        'reduced' => 0,
        'non_taxable' => 0,
    ]);
});

it('skips charges outside the target month', function () {
    [, $resident] = recurringSetup();

    $item = ChargeItem::factory()->create(['default_price' => 50000]);

    RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $resident->facility_id,
        'charge_item_id' => $item->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'unit_price' => 50000,
        'start_date' => '2026-05-01',
        'end_date' => null,
        'is_active' => true,
    ]);

    $aggregator = new RecurringChargeAggregator;
    $result = $aggregator->aggregate([$resident->id], '2026-03', Carbon::parse('2026-03-27'));

    expect($result[$resident->id]['standard'])->toBe(0);
});

it('skips inactive charges', function () {
    [, $resident] = recurringSetup();

    $item = ChargeItem::factory()->create(['default_price' => 50000]);

    RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $resident->facility_id,
        'charge_item_id' => $item->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'unit_price' => 50000,
        'start_date' => '2026-01-01',
        'end_date' => null,
        'is_active' => false,
    ]);

    $aggregator = new RecurringChargeAggregator;
    $result = $aggregator->aggregate([$resident->id], '2026-03', Carbon::parse('2026-03-27'));

    expect($result[$resident->id]['standard'])->toBe(0);
});

it('skips charges with zero price', function () {
    [, $resident] = recurringSetup();

    $item = ChargeItem::factory()->create(['default_price' => 0]);

    RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $resident->facility_id,
        'charge_item_id' => $item->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'unit_price' => 0,
        'start_date' => '2026-01-01',
        'end_date' => null,
        'is_active' => true,
    ]);

    $aggregator = new RecurringChargeAggregator;
    $result = $aggregator->aggregate([$resident->id], '2026-03', Carbon::parse('2026-03-27'));

    expect($result[$resident->id]['standard'])->toBe(0);
});

it('handles charge item relation correctly', function () {
    [, $resident] = recurringSetup();

    $item = ChargeItem::factory()->create(['default_price' => 50000]);

    $recurring = RecurringCharge::create([
        'resident_id' => $resident->id,
        'facility_id' => $resident->facility_id,
        'charge_item_id' => $item->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'unit_price' => 50000,
        'start_date' => '2026-01-01',
        'end_date' => null,
        'is_active' => true,
    ]);

    expect($recurring->chargeItem)->toBeInstanceOf(ChargeItem::class);

    $aggregator = new RecurringChargeAggregator;
    $result = $aggregator->aggregate([$resident->id], '2026-03', Carbon::parse('2026-03-27'));

    expect($result[$resident->id]['standard'])->toBe(50000);
});

it('aggregates multiple residents with mixed tax types', function () {
    [$facility, $resident1] = recurringSetup();
    $resident2 = Resident::factory()->create(['facility_id' => $facility->id]);

    $standardItem = ChargeItem::factory()->create([
        'tax_type' => TaxType::Standard,
        'default_price' => 10000,
    ]);
    $nonTaxableItem = ChargeItem::factory()->create([
        'tax_type' => TaxType::NonTaxable,
        'default_price' => 3000,
    ]);

    RecurringCharge::create([
        'resident_id' => $resident1->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $standardItem->id,
        'frequency' => 'monthly',
        'quantity' => 2,
        'unit_price' => 10000,
        'start_date' => '2026-01-01',
        'end_date' => null,
        'is_active' => true,
    ]);

    RecurringCharge::create([
        'resident_id' => $resident2->id,
        'facility_id' => $facility->id,
        'charge_item_id' => $nonTaxableItem->id,
        'frequency' => 'monthly',
        'quantity' => 1,
        'unit_price' => 3000,
        'start_date' => '2026-01-01',
        'end_date' => null,
        'is_active' => true,
    ]);

    $aggregator = new RecurringChargeAggregator;
    $result = $aggregator->aggregate([$resident1->id, $resident2->id], '2026-03', Carbon::parse('2026-03-27'));

    expect($result[$resident1->id]['standard'])->toBe(20000)
        ->and($result[$resident2->id]['non_taxable'])->toBe(3000);
});
