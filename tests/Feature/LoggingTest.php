<?php

use App\Models\ChargeItem;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;
use Illuminate\Support\Facades\Log;

test('請求生成時にinfoログが出力されること', function () {
    Log::spy();

    $resident = Resident::create([
        'room_number' => '1101',
        'name' => 'ログテスト入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    ChargeItem::create(['name' => 'テスト品目', 'default_price' => 1000]);

    $service = app(InvoiceCalculationService::class);
    $service->generateForMonth('2026-10');

    Log::shouldHaveReceived('info')->atLeast()->once();
});

test('ログレベルinfoが適切に使用されていること', function () {
    Log::spy();

    Resident::create([
        'room_number' => '1102',
        'name' => 'ログレベルテスト',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    $service = app(InvoiceCalculationService::class);
    $service->generateForMonth('2026-10');

    Log::shouldHaveReceived('info')
        ->with('Invoice generation started', Mockery::on(fn ($arg) => isset($arg['year_month']) && $arg['year_month'] === '2026-10'))
        ->atLeast()->once();

    Log::shouldHaveReceived('info')
        ->with('Invoice generation completed', Mockery::type('array'))
        ->atLeast()->once();
});
