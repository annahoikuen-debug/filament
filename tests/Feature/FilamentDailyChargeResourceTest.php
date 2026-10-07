<?php

use App\Enums\ResidentStatus;
use App\Filament\Resources\DailyChargeResource\Pages\ListDailyCharges;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->resident = Resident::create([
        'room_number' => '101',
        'name' => 'テスト入居者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    $this->item = ChargeItem::create([
        'name' => 'おむつ',
        'default_price' => 200,
        'is_active' => true,
    ]);

    DailyCharge::create([
        'resident_id' => $this->resident->id,
        'charge_item_id' => $this->item->id,
        'date' => now()->toDateString(),
        'unit_price' => 200,
        'quantity' => 1,
    ]);
});

test('DailyChargeResource一覧画面がエラーなく正常に表示されること', function () {
    Livewire::test(ListDailyCharges::class)
        ->assertSuccessful();
});
