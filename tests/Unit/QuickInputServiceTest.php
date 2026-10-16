<?php

use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use App\Services\QuickInputService;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = new QuickInputService();
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->user);

    $this->facility = Facility::factory()->create();
    $this->resident1 = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '101',
        'name' => 'テスト入居者1',
        'status' => 'active',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);
    $this->resident2 = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '102',
        'name' => 'テスト入居者2',
        'status' => 'active',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    $this->chargeItem = ChargeItem::create([
        'name' => '紙おむつM',
        'default_price' => 150,
        'is_active' => true,
    ]);
});

test('直近レコードなしの場合はデフォルト値（当日・数量1）を返すこと', function () {
    $result = $this->service->getLastInputForUser($this->user);

    expect($result['resident_id'])->toBeNull()
        ->and($result['charge_item_id'])->toBeNull()
        ->and($result['unit_price'])->toBe(0)
        ->and($result['quantity'])->toBe(1)
        ->and($result['note'])->toBe('')
        ->and($result['date'])->toBe(now()->toDateString());
});

test('直近レコードがある場合は品目・単価・数量・備考を引き継ぐこと', function () {
    $lastCharge = DailyCharge::create([
        'resident_id' => $this->resident1->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-10',
        'unit_price' => 150,
        'quantity' => 3,
        'note' => '午前中',
        'created_by' => $this->user->id,
    ]);

    $result = $this->service->getLastInputForUser($this->user);

    expect($result['charge_item_id'])->toBe($this->chargeItem->id)
        ->and($result['unit_price'])->toBe(150)
        ->and($result['quantity'])->toBe(3)
        ->and($result['note'])->toBe('午前中')
        // 入居者と日付は初期化される（連続記録用）
        ->and($result['resident_id'])->toBeNull()
        ->and($result['date'])->toBe(now()->toDateString());
});

test('自分の記録のみ対象で他人の記録を引き継がないこと', function () {
    $otherUser = User::factory()->create(['is_admin' => true]);
    DailyCharge::create([
        'resident_id' => $this->resident1->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-10',
        'unit_price' => 150,
        'quantity' => 5,
        'note' => '他人の記録',
        'created_by' => $otherUser->id,
    ]);

    // 自分のレコードを作成
    $myCharge = DailyCharge::create([
        'resident_id' => $this->resident2->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-11',
        'unit_price' => 200,
        'quantity' => 2,
        'note' => '自分の記録',
        'created_by' => $this->user->id,
    ]);

    $result = $this->service->getLastInputForUser($this->user);

    expect($result['quantity'])->toBe(2)
        ->and($result['note'])->toBe('自分の記録');
});

test('退去済み入居者の記録は候補から除外されること', function () {
    // 退去済み入居者
    $movedOutResident = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '201',
        'name' => '退去済み入居者',
        'status' => 'moved_out',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
        'move_out_date' => '2026-09-30',
    ]);

    DailyCharge::create([
        'resident_id' => $movedOutResident->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-09-20',
        'unit_price' => 150,
        'quantity' => 3,
        'note' => '退去済みの記録',
        'created_by' => $this->user->id,
    ]);

    // 在籍中の入居者での最新レコードを作成
    DailyCharge::create([
        'resident_id' => $this->resident1->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-10',
        'unit_price' => 180,
        'quantity' => 2,
        'note' => '在籍中の記録',
        'created_by' => $this->user->id,
    ]);

    $result = $this->service->getLastInputForUser($this->user);

    expect($result['unit_price'])->toBe(180)
        ->and($result['note'])->toBe('在籍中の記録');
});

test('定型備考の上位5件が集計されること', function () {
    // 同一備考を複数回記録
    DailyCharge::create([
        'resident_id' => $this->resident1->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-01',
        'unit_price' => 150,
        'quantity' => 2,
        'note' => '午前中',
        'created_by' => $this->user->id,
    ]);

    DailyCharge::create([
        'resident_id' => $this->resident1->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-02',
        'unit_price' => 150,
        'quantity' => 2,
        'note' => '午前中',
        'created_by' => $this->user->id,
    ]);

    DailyCharge::create([
        'resident_id' => $this->resident1->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-03',
        'unit_price' => 150,
        'quantity' => 1,
        'note' => '夜間交換',
        'created_by' => $this->user->id,
    ]);

    DailyCharge::create([
        'resident_id' => $this->resident1->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-04',
        'unit_price' => 150,
        'quantity' => 1,
        'note' => '夕方',
        'created_by' => $this->user->id,
    ]);

    $notes = $this->service->getFrequentNotesForUser($this->user, 5);

    expect($notes)->toHaveCount(3)
        ->and($notes[0])->toBe('午前中') // 2回
        ->and($notes[1])->toBe('夜間交換')
        ->and($notes[2])->toBe('夕方');
});

test('備考がnullや空文字のレコードは集計に含まれないこと', function () {
    DailyCharge::create([
        'resident_id' => $this->resident1->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-01',
        'unit_price' => 150,
        'quantity' => 2,
        'note' => null,
        'created_by' => $this->user->id,
    ]);

    DailyCharge::create([
        'resident_id' => $this->resident1->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-02',
        'unit_price' => 150,
        'quantity' => 2,
        'note' => '',
        'created_by' => $this->user->id,
    ]);

    DailyCharge::create([
        'resident_id' => $this->resident1->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-03',
        'unit_price' => 150,
        'quantity' => 1,
        'note' => '有効な備考',
        'created_by' => $this->user->id,
    ]);

    $notes = $this->service->getFrequentNotesForUser($this->user, 5);

    expect($notes)->toHaveCount(1)
        ->and($notes[0])->toBe('有効な備考');
});