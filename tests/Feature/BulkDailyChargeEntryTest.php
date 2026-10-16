<?php

use App\Enums\ResidentStatus;
use App\Filament\Pages\BulkDailyChargeEntry;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
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

    $this->resident2 = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '102',
        'name' => 'テスト入居者2',
        'status' => ResidentStatus::Active,
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);
});

test('自費一括入力ページが正常にマウントされ入居者一覧が表示されること', function () {
    Livewire::test(BulkDailyChargeEntry::class)
        ->assertSuccessful()
        ->assertSee('紙おむつM')
        ->assertSee('テスト入居者1')
        ->assertSee('テスト入居者2');
});

    test('選択した入居者のみ自費記録が一括登録されること', function () {
        $component = Livewire::test(BulkDailyChargeEntry::class)
            ->set('date', '2026-10-14')
            ->set('chargeItemId', $this->chargeItem->id)
            ->set('entries.0.selected', true)
            ->set('entries.0.quantity', 3)
            ->set('entries.0.note', '午前3枚')
            ->set('entries.1.selected', false)
            ->call('saveBulk')
            ->assertHasNoErrors()
            ->call('executeSave')
            ->assertHasNoErrors();

        // 101号室の入居者1のみDailyChargeが作成されること
        $charges = DailyCharge::where('charge_item_id', $this->chargeItem->id)->get();
        expect($charges->count())->toBe(1)
            ->and($charges->first()->resident_id)->toBe($this->resident1->id)
            ->and($charges->first()->quantity)->toBe(3)
            ->and($charges->first()->unit_price)->toBe(150)
            ->and($charges->first()->note)->toBe('午前3枚');
    });

test('施設管理者は自施設の入居者のみロードされること', function () {
    $facilityB = Facility::factory()->create();
    $facilityBAdmin = User::factory()->create([
        'is_admin' => true,
        'role' => 'facility_admin',
        'facility_id' => $facilityB->id,
    ]);

    $residentB = Resident::create([
        'facility_id' => $facilityB->id,
        'room_number' => '901',
        'name' => '施設B入居者',
        'status' => ResidentStatus::Active,
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    $this->actingAs($facilityBAdmin);

    Livewire::test(BulkDailyChargeEntry::class)
        ->assertSuccessful()
        ->assertSee('施設B入居者')
        ->assertDontSee('テスト入居者1');
});

test('請求確定済みの年月は自費一括登録がスキップされること', function () {
    \App\Models\MonthlyInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident1->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => \App\Enums\InvoiceStatus::Billed,
    ]);

    $component = Livewire::test(BulkDailyChargeEntry::class)
        ->set('date', '2026-10-15')
        ->set('chargeItemId', $this->chargeItem->id)
        ->set('entries.0.selected', true)
        ->set('entries.0.quantity', 2)
        ->call('saveBulk');

    // 確定済みの resident1 のレコードは作られないこと
    expect(DailyCharge::where('resident_id', $this->resident1->id)->count())->toBe(0);
});
