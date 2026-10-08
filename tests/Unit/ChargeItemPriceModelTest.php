<?php

use App\Models\ChargeItem;
use App\Models\ChargeItemPrice;
use Carbon\Carbon;

test('fillableフィールドが設定されること（ただしguardedなのでid以外は設定可能）', function () {
    $chargeItem = ChargeItem::factory()->create();
    $chargeItemPrice = ChargeItemPrice::create([
        'charge_item_id' => $chargeItem->id,
        'price' => 1500,
        'effective_from' => '2026-10-01',
        'effective_until' => '2026-10-31',
    ]);

    expect($chargeItemPrice->exists)->toBeTrue()
        ->and($chargeItemPrice->price)->toBe(1500)
        ->and($chargeItemPrice->effective_from)->toEqual(Carbon::create(2026, 10, 1))
        ->and($chargeItemPrice->effective_until)->toEqual(Carbon::create(2026, 10, 31));
});

test('guardedがidのみで他のフィールドは一括代入可能であること', function () {
    $chargeItem = ChargeItem::factory()->create();
    $chargeItemPrice = ChargeItemPrice::create([
        'id' => 99999,
        'charge_item_id' => $chargeItem->id,
        'price' => 1500,
        'effective_from' => '2026-10-01',
    ]);

    expect($chargeItemPrice->id)->not->toBe(99999);
});

test('不正なフィールド名での一括代入は無視されること', function () {
    $chargeItem = ChargeItem::factory()->create();
    $chargeItemPrice = ChargeItemPrice::create([
        'charge_item_id' => $chargeItem->id,
        'price' => 1500,
        'effective_from' => '2026-10-01',
        'unknown_field' => 'テスト',
    ]);

    expect($chargeItemPrice->exists)->toBeTrue()
        ->and($chargeItemPrice->getAttribute('unknown_field'))->toBeNull();
});

test('キャストが正しく動作すること', function () {
    $chargeItem = ChargeItem::factory()->create();
    $chargeItemPrice = ChargeItemPrice::create([
        'charge_item_id' => $chargeItem->id,
        'price' => '2000',
        'effective_from' => '2026-10-01',
        'effective_until' => '2026-10-31',
    ]);

    expect($chargeItemPrice->price)->toBe(2000)
        ->and($chargeItemPrice->effective_from)->toBeInstanceOf(Carbon::class)
        ->and($chargeItemPrice->effective_until)->toBeInstanceOf(Carbon::class);
});

test('chargeItemリレーションが正しく動作すること', function () {
    $chargeItem = ChargeItem::factory()->create();
    $chargeItemPrice = ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
    ]);

    expect($chargeItemPrice->chargeItem->id)->toBe($chargeItem->id);
});

test('scopeCurrentメソッドが現在有効な価格のみ取得すること', function () {
    $chargeItem = ChargeItem::factory()->create();
    
    // 現在有効な価格
    $current = ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 1500,
        'effective_from' => Carbon::today()->subDay(),
        'effective_until' => null,
    ]);
    
    // 過去の価格（有効期限切れ）
    $expired = ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 1000,
        'effective_from' => Carbon::today()->subMonth(),
        'effective_until' => Carbon::today()->subDay(),
    ]);
    
    // 未来の価格（まだ有効ではない）
    $future = ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 2000,
        'effective_from' => Carbon::today()->addDay(),
        'effective_until' => null,
    ]);

    $currentPrices = ChargeItemPrice::current()->get();
    expect($currentPrices->count())->toBe(1)
        ->and($currentPrices->first()->id)->toBe($current->id);
});

test('scopeForDateメソッドが指定日の有効な価格を取得すること', function () {
    $chargeItem = ChargeItem::factory()->create();
    $targetDate = Carbon::create(2026, 10, 15);
    
    // 指定日の有効な価格
    $valid = ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 1500,
        'effective_from' => Carbon::create(2026, 10, 10),
        'effective_until' => Carbon::create(2026, 10, 20),
    ]);
    
    // 指定日より前で期限切れの価格
    $expired = ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 1000,
        'effective_from' => Carbon::create(2026, 9, 1),
        'effective_until' => Carbon::create(2026, 9, 30),
    ]);
    
    // 指定日より後の価格（まだ有効ではない）
    $future = ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 2000,
        'effective_from' => Carbon::create(2026, 11, 1),
        'effective_until' => Carbon::create(2026, 11, 30),
    ]);

    $prices = ChargeItemPrice::forDate($targetDate)->get();
    expect($prices->count())->toBe(1)
        ->and($prices->first()->id)->toBe($valid->id);
});

test('scopeForDateメソッドで境界条件を正しく扱うこと', function () {
    $chargeItem = ChargeItem::factory()->create();
    $targetDate = Carbon::create(2026, 10, 15);
    
    // 開始日が今日
    $startToday = ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 1500,
        'effective_from' => $targetDate,
        'effective_until' => Carbon::create(2026, 10, 31),
    ]);
    
    // 終了日が今日
    $endToday = ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 1600,
        'effective_from' => Carbon::create(2026, 10, 1),
        'effective_until' => $targetDate,
    ]);
    
    // 昨日終了（今日には有効ではない）
    $yesterdayEnd = ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 1000,
        'effective_from' => Carbon::create(2026, 10, 1),
        'effective_until' => $targetDate->subDay(),
    ]);
    
    // 明日開始（今日には有効ではない）
    $tomorrowStart = ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 2000,
        'effective_from' => $targetDate->addDay(),
        'effective_until' => Carbon::create(2026, 10, 31),
    ]);

    $prices = ChargeItemPrice::forDate($targetDate)->get();
    expect($prices->count())->toBe(2)
        ->and($prices->pluck('price'))->toContain(1500)
        ->and($prices->pluck('price'))->toContain(1600);
});