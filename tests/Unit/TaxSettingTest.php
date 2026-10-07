<?php

use App\Models\TaxSetting;
use Carbon\Carbon;

test('現在有効な税率設定を取得できること', function () {
    // 現在有効な設定を作成
    $setting = TaxSetting::create([
        'standard_rate' => 10,
        'reduced_rate' => 8,
        'effective_from' => Carbon::today()->subDays(10),
        'effective_until' => null,
        'is_active' => true,
    ]);

    $current = TaxSetting::current();
    
    expect($current)->not->toBeNull()
        ->and($current->standard_rate)->toBe(10)
        ->and($current->reduced_rate)->toBe(8);
});

test('期限切れの設定は取得されないこと', function () {
    // 期限切れの設定
    TaxSetting::create([
        'standard_rate' => 5,
        'reduced_rate' => 3,
        'effective_from' => Carbon::today()->subDays(30),
        'effective_until' => Carbon::today()->subDays(1),
        'is_active' => true,
    ]);

    // 現在有効な設定
    $currentSetting = TaxSetting::create([
        'standard_rate' => 10,
        'reduced_rate' => 8,
        'effective_from' => Carbon::today()->subDays(10),
        'effective_until' => null,
        'is_active' => true,
    ]);

    $current = TaxSetting::current();
    
    expect($current->standard_rate)->toBe(10);
});

test('無効フラグの設定は取得されないこと', function () {
    TaxSetting::create([
        'standard_rate' => 10,
        'reduced_rate' => 8,
        'effective_from' => Carbon::today()->subDays(10),
        'is_active' => false, // 無効
    ]);

    $current = TaxSetting::current();
    
    expect($current)->toBeNull();
});

test('指定日の税率を取得できること', function () {
    TaxSetting::create([
        'standard_rate' => 5,
        'reduced_rate' => 3,
        'effective_from' => '2020-01-01',
        'effective_until' => '2020-12-31',
        'is_active' => true,
    ]);

    TaxSetting::create([
        'standard_rate' => 8,
        'reduced_rate' => 5,
        'effective_from' => '2021-01-01',
        'effective_until' => '2023-09-30',
        'is_active' => true,
    ]);

    TaxSetting::create([
        'standard_rate' => 10,
        'reduced_rate' => 8,
        'effective_from' => '2023-10-01',
        'is_active' => true,
    ]);

    // 2020年の税率
    expect(TaxSetting::getRateForDate(Carbon::create(2020, 6, 1)))->toBe(5);
    
    // 2022年の税率
    expect(TaxSetting::getRateForDate(Carbon::create(2022, 6, 1)))->toBe(8);
    
    // 2024年の税率
    expect(TaxSetting::getRateForDate(Carbon::create(2024, 6, 1)))->toBe(10);
});

test('設定がない場合はデフォルト10%が返ること', function () {
    // 全設定を削除
    TaxSetting::query()->delete();
    
    expect(TaxSetting::currentStandardRate())->toBe(10);
    expect(TaxSetting::currentReducedRate())->toBe(8);
});

test('config配列への変換が正しく動作すること', function () {
    $setting = TaxSetting::create([
        'standard_rate' => 10,
        'reduced_rate' => 8,
        'effective_from' => '2026-01-01',
        'effective_until' => '2026-12-31',
        'scope' => 'all',
        'is_active' => true,
    ]);

    $config = $setting->toConfigArray();
    
    expect($config['standard_rate'])->toBe(10);
    expect($config['reduced_rate'])->toBe(8);
    expect($config['effective_from'])->toBe('2026-01-01');
    expect($config['effective_until'])->toBe('2026-12-31');
    expect($config['scope'])->toBe('all');
});