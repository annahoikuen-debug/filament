<?php

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Filament\Pages\BulkDailyChargeEntry;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
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

test('saveBulk呼び出しで確認モーダルが開き件数・金額がプレビューされること', function () {
    Livewire::test(BulkDailyChargeEntry::class)
        ->set('date', '2026-10-15')
        ->set('chargeItemId', $this->chargeItem->id)
        ->set('entries.0.selected', true)
        ->set('entries.0.quantity', 3)
        ->call('saveBulk')
        ->assertSet('showConfirmModal', true)
        ->assertSet('confirmSelectedCount', 1)
        ->assertSet('confirmTotalAmount', 450); // 3 * 150
});

test('確認モーダルでexecuteSaveを実行すると保存されモーダルが閉じること', function () {
    Livewire::test(BulkDailyChargeEntry::class)
        ->set('date', '2026-10-15')
        ->set('chargeItemId', $this->chargeItem->id)
        ->set('entries.0.selected', true)
        ->set('entries.0.quantity', 2)
        ->call('saveBulk')
        ->assertSet('showConfirmModal', true)
        ->call('executeSave')
        ->assertSet('showConfirmModal', false);

    expect(DailyCharge::where('resident_id', $this->resident1->id)->count())->toBe(1);
});

test('確定月入居者が含まれる場合スキップ件数が確認モーダルに反映されること', function () {
    MonthlyInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident1->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Billed,
    ]);

    Livewire::test(BulkDailyChargeEntry::class)
        ->set('date', '2026-10-15')
        ->set('chargeItemId', $this->chargeItem->id)
        ->set('entries.0.selected', true)
        ->set('entries.0.quantity', 2)
        ->set('entries.1.selected', true)
        ->set('entries.1.quantity', 1)
        ->call('saveBulk')
        ->assertSet('showConfirmModal', true)
        ->assertSet('confirmSelectedCount', 2)
        ->assertSet('confirmSkippedLocked', 1);
});
