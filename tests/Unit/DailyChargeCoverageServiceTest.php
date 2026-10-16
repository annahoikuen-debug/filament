<?php

use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\Facility;
use App\Models\Resident;
use App\Services\DailyChargeCoverageService;

beforeEach(function () {
    $this->service = new DailyChargeCoverageService();
    $this->facility = Facility::factory()->create();

    $this->resident = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '101',
        'name' => 'マトリクス検証入居者',
        'status' => 'active',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
    ]);

    $this->chargeItem = ChargeItem::create([
        'name' => 'おやつ',
        'default_price' => 100,
        'is_active' => true,
    ]);
});

test('記録がある日付がtrue、記録がない日付がfalseとしてマトリクス化されること', function () {
    // 2026年10月5日のみ記録作成
    DailyCharge::create([
        'resident_id' => $this->resident->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-05',
        'unit_price' => 100,
        'quantity' => 1,
    ]);

    $result = $this->service->getMonthlyCoverage('2026-10', $this->facility->id);

    expect($result['daysInMonth'])->toBe(31)
        ->and(count($result['matrix']))->toBe(1);

    $row = $result['matrix'][0];
    expect($row['days'][5])->toBeTrue()
        ->and($row['days'][1])->toBeFalse()
        ->and($row['totalCount'])->toBe(1)
        ->and($row['missingDaysCount'])->toBe(30);
});
