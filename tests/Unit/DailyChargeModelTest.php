<?php

use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\Resident;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->chargeItem = ChargeItem::create([
        'name' => 'テスト品目',
        'default_price' => 500,
    ]);
});

test('resident_idなしの場合はバリデーションスキップされること（NOT NULL制約のため例外は想定内）', function () {
    // resident_id は NOT NULL 制約のため、resident_id なしではDBエラーとなる
    // バリデーションスキップの挙動は DomainException が発生しないことで確認
    expect(fn () => DailyCharge::create([
        'date' => '2026-10-01',
        'unit_price' => 500,
        'quantity' => 1,
    ]))->toThrow(QueryException::class);
});

test('リレーション未ロードでもresident_id経由で在籍期間バリデーションが機能すること', function () {
    $resident = Resident::create([
        'room_number' => '301',
        'name' => '未ロードテスト',
        'move_in_date' => '2026-01-01',
        'move_out_date' => '2026-09-30',
    ]);

    // 在籍期間外 → DomainException
    expect(fn () => DailyCharge::create([
        'resident_id' => $resident->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-01',
        'unit_price' => 500,
        'quantity' => 1,
    ]))->toThrow(DomainException::class);
});

test('リレーションロード済みの場合もバリデーションが正しく機能すること', function () {
    $resident = Resident::create([
        'room_number' => '302',
        'name' => 'ロード済みテスト',
        'move_in_date' => '2026-10-01',
    ]);

    // 在籍期間内 → 保存可能
    $charge = new DailyCharge([
        'date' => '2026-10-05',
        'unit_price' => 300,
        'quantity' => 2,
    ]);
    $charge->charge_item_id = $this->chargeItem->id;
    $charge->resident_id = $resident->id;
    $charge->setRelation('resident', $resident);
    $charge->save();

    expect($charge->exists)->toBeTrue();
});

test('入居日ちょうどの利用日は保存可能であること', function () {
    $resident = Resident::create([
        'room_number' => '303',
        'name' => '境界入居日',
        'move_in_date' => '2026-10-01',
    ]);

    $charge = DailyCharge::create([
        'resident_id' => $resident->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-01',
        'unit_price' => 100,
        'quantity' => 1,
    ]);

    expect($charge->exists)->toBeTrue();
});

test('退去日ちょうどの利用日は保存可能であること', function () {
    $resident = Resident::create([
        'room_number' => '304',
        'name' => '境界退去日',
        'move_in_date' => '2026-01-01',
        'move_out_date' => '2026-10-31',
    ]);

    $charge = DailyCharge::create([
        'resident_id' => $resident->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-31',
        'unit_price' => 100,
        'quantity' => 1,
    ]);

    expect($charge->exists)->toBeTrue();
});

test('退去日翌日の利用日はDomainExceptionが発生すること', function () {
    $resident = Resident::create([
        'room_number' => '305',
        'name' => '退去後テスト',
        'move_in_date' => '2026-01-01',
        'move_out_date' => '2026-10-31',
    ]);

    expect(fn () => DailyCharge::create([
        'resident_id' => $resident->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-11-01',
        'unit_price' => 100,
        'quantity' => 1,
    ]))->toThrow(DomainException::class);
});

test('入居日前の利用日はDomainExceptionが発生すること', function () {
    $resident = Resident::create([
        'room_number' => '306',
        'name' => '入居前テスト',
        'move_in_date' => '2026-10-10',
    ]);

    expect(fn () => DailyCharge::create([
        'resident_id' => $resident->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-01',
        'unit_price' => 100,
        'quantity' => 1,
    ]))->toThrow(DomainException::class);
});
