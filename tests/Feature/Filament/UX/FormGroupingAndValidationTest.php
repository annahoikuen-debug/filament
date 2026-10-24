<?php

use App\Enums\ResidentStatus;
use App\Filament\Resources\DailyChargeResource\Pages\CreateDailyCharge;
use App\Models\ChargeItem;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->resident = Resident::create([
        'room_number' => '101',
        'name' => 'テスト入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    $this->item = ChargeItem::create([
        'name' => 'テスト品目',
        'default_price' => 1000,
        'is_active' => true,
    ]);
});

test('DailyCharge create page renders successfully', function () {
    Livewire::test(CreateDailyCharge::class)
        ->assertSuccessful();
});

test('DailyCharge form contains required fields', function () {
    Livewire::test(CreateDailyCharge::class)
        ->assertSeeHtml('入居者')
        ->assertSeeHtml('品目')
        ->assertSeeHtml('利用日');
});
