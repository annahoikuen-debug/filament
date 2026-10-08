<?php

use App\Models\ChargeItem;
use App\Models\ChargeItemPrice;
use Carbon\Carbon;

test('fillableフィールドが設定されること（ただしguardedなのでid以外は設定可能）', function () {
    $chargeItem = ChargeItem::create([
        'default_price' => 1000,
        'is_active' => true,
        'tax_type' => 1, // Assuming 1 is a valid TaxType value
    ]);

    expect($chargeItem->exists)->toBeTrue()
        ->and($chargeItem->default_price)->toBe(1000)
        ->and($chargeItem->is_active)->toBeTrue();
});

test('guardedがidのみで他のフィールドは一括代入可能であること', function () {
    $chargeItem = ChargeItem::create([
        'id' => 99999,
        'default_price' => 1000,
        'is_active' => true,
        'tax_type' => 1,
    ]);

    expect($chargeItem->id)->not->toBe(99999);
});

test('不正なフィールド名での一括代入は無視されること', function () {
    $chargeItem = ChargeItem::create([
        'default_price' => 1000,
        'is_active' => true,
        'tax_type' => 1,
        'unknown_field' => 'テスト',
    ]);

    expect($chargeItem->exists)->toBeTrue()
        ->and($chargeItem->getAttribute('unknown_field'))->toBeNull();
});

test('キャストが正しく動作すること', function () {
    $chargeItem = ChargeItem::create([
        'default_price' => '1500',
        'is_active' => 'true',
        'tax_type' => '1',
    ]);

    expect($chargeItem->default_price)->toBe(1500)
        ->and($chargeItem->is_active)->toBeTrue()
        ->and($chargeItem->tax_type)->toBe(1); // Assuming tax_type casts to int or enum
});

test('dailyChargesリレーションが正しく動作すること', function () {
    $chargeItem = ChargeItem::factory()->hasDailyCharges(1)->create();

    expect($chargeItem->dailyCharges->first()->charge_item_id)->toBe($chargeItem->id);
});

test('priceHistoryリレーションが正しく動作すること', function () {
    $chargeItem = ChargeItem::factory()->hasPriceHistory(1)->create();

    expect($chargeItem->priceHistory->first()->charge_item_id)->toBe($chargeItem->id);
});

test('currentPriceメソッドが現在有効な価格を取得すること', function () {
    $chargeItem = ChargeItem::factory()->create([
        'default_price' => 1000,
    ]);

    // 現在の日付で有効な価格履歴を作成
    $currentPrice = ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 1500,
        'effective_from' => Carbon::today()->subDay(),
        'effective_until' => null,
    ]);

    // 未来の価格履歴（まだ有効ではない）
    ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 2000,
        'effective_from' => Carbon::today()->addDay(),
        'effective_until' => null,
    ]);

    expect($chargeItem->currentPrice())->toBe(1500);
});

test('currentPriceメソッドが履歴がない場合はデフォルト価格を返すこと', function () {
    $chargeItem = ChargeItem::factory()->create([
        'default_price' => 1200,
    ]);

    // 価格履歴がない場合
    expect($chargeItem->currentPrice())->toBe(1200);
});

test('getPriceForDateメソッドが指定日の有効な価格を取得すること', function () {
    $chargeItem = ChargeItem::factory()->create([
        'default_price' => 1000,
    ]);

    $targetDate = Carbon::create(2026, 10, 15);
    
    // 指定日の有効な価格履歴
    ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 1500,
        'effective_from' => Carbon::create(2026, 10, 10),
        'effective_until' => Carbon::create(2026, 10, 20),
    ]);

    expect($chargeItem->getPriceForDate($targetDate))->toBe(1500);
});

test('getPriceForDateメソッドが履歴がない場合はデフォルト価格を返すこと', function () {
    $chargeItem = ChargeItem::factory()->create([
        'default_price' => 1200,
    ]);

    $targetDate = Carbon::create(2026, 10, 15);
    // 価格履歴がない場合
    expect($chargeItem->getPriceForDate($targetDate))->toBe(1200);
});

test('getEffectivePriceメソッドがデフォルト単価が0より大きい場合は価格を返すこと', function () {
    $chargeItem = ChargeItem::factory()->create([
        'default_price' => 1500,
    ]);
    ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 2000,
        'effective_from' => Carbon::today()->subDay(),
    ]);

    expect($chargeItem->getEffectivePrice())->toBe(2000);
});

test('getEffectivePriceメソッドがデフォルト単価が0以下の場合はnullを返すこと', function () {
    $chargeItem = ChargeItem::factory()->create([
        'default_price' => 0,
    ]);
    ChargeItemPrice::factory()->create([
        'charge_item_id' => $chargeItem->id,
        'price' => 1000,
        'effective_from' => Carbon::today()->subDay(),
    ]);

    expect($chargeItem->getEffectivePrice())->toBeNull();
});

test('getEffectivePriceメソッドが価格履歴がない場合とデフォルト単価が0の場合はnullを返すこと', function () {
    $chargeItem = ChargeItem::factory()->create([
        'default_price' => 0,
    ]);

    expect($chargeItem->getEffectivePrice())->toBeNull();
});

test('scopeActiveメソッドが現在有効な品目のみ取得すること', function () {
    $active = ChargeItem::create([
        'default_price' => 1000,
        'is_active' => true,
    ]);

    $inactive = ChargeItem::create([
        'default_price' => 1000,
        'is_active' => false,
    ]);

    $activeItems = ChargeItem::active()->get();
    expect($activeItems->count())->toBe(1)
        ->and($activeItems->first()->id)->toBe($active->id);
});