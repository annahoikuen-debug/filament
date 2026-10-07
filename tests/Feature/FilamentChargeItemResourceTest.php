<?php

use App\Filament\Resources\ChargeItemResource;
use App\Models\ChargeItem;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('ChargeItemResource一覧画面がエラーなく正常に表示されること', function () {
    $this->get(ChargeItemResource::getUrl('index'))
        ->assertSuccessful();
});

test('getEffectivePriceがデフォルト単価>0の場合に価格を返すこと', function () {
    $item = ChargeItem::create([
        'name' => '理美容代',
        'default_price' => 3000,
        'is_active' => true,
    ]);

    expect($item->getEffectivePrice())->toBe(3000);
});

test('getEffectivePriceがデフォルト単価0の場合にnullを返すこと', function () {
    $item = ChargeItem::create([
        'name' => '立替金',
        'default_price' => 0,
        'is_active' => true,
    ]);

    expect($item->getEffectivePrice())->toBeNull();
});

test('デフォルト価格0でもデータ保存が可能であること', function () {
    $item = ChargeItem::create([
        'name' => '日用品',
        'default_price' => 0,
        'is_active' => true,
    ]);

    expect($item->exists)->toBeTrue()
        ->and($item->default_price)->toBe(0);
});
