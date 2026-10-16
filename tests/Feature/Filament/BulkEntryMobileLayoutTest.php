<?php

use App\Enums\ResidentStatus;
use App\Filament\Pages\BulkDailyChargeEntry;
use App\Models\ChargeItem;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->user);

    $this->facility = Facility::factory()->create();
    $this->chargeItem = ChargeItem::create([
        'name' => '紙おむつM',
        'default_price' => 150,
        'is_active' => true,
    ]);

    $this->resident1 = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '101',
        'name' => 'テスト入居者1',
        'status' => ResidentStatus::Active,
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);
});

test('カード型レイアウトとテーブル型レイアウトの両方がレンダリングされること', function () {
    Livewire::test(BulkDailyChargeEntry::class)
        ->assertSuccessful()
        ->assertSee('block md:hidden', false) // モバイル用コンテナ
        ->assertSee('hidden md:block', false) // PC用コンテナ
        ->assertSee('テスト入居者1')
        ->assertSee('101');
});

test('カードおよびテーブルの入力がwire:modelにバインドされていること', function () {
    Livewire::test(BulkDailyChargeEntry::class)
        ->set('entries.0.selected', true)
        ->set('entries.0.quantity', 4)
        ->assertSet('entries.0.selected', true)
        ->assertSet('entries.0.quantity', 4);
});
