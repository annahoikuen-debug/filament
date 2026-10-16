<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ResidentStatus;
use App\Filament\Pages\BulkDailyChargeEntry;
use App\Filament\Resources\DailyChargeResource\Pages\CreateDailyCharge;
use App\Filament\Resources\DailyChargeResource\Pages\ListDailyCharges;
use App\Filament\Resources\MonthlyInvoiceResource\Pages\ListMonthlyInvoices;
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
    $this->resident = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '101',
        'name' => 'モバイル検証入居者',
        'status' => ResidentStatus::Active,
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
    ]);

    $this->chargeItem = ChargeItem::create([
        'name' => '紙おむつM',
        'default_price' => 150,
        'is_active' => true,
    ]);
});

test('日次自費記録一覧および作成画面がモバイルビューでも正常にマウントされること', function () {
    Livewire::withQueryParams([])
        ->test(ListDailyCharges::class)
        ->assertSuccessful()
        ->assertSee('今日の記録（クイック）');

    Livewire::test(CreateDailyCharge::class)
        ->assertSuccessful()
        ->assertSee('保存して続けて入力');
});

test('自費一括入力画面がスマホ用カードビューを含めて正常に機能すること', function () {
    Livewire::test(BulkDailyChargeEntry::class)
        ->assertSuccessful()
        ->assertSee('block md:hidden', false)
        ->set('date', '2026-10-15')
        ->set('chargeItemId', $this->chargeItem->id)
        ->set('entries.0.selected', true)
        ->set('entries.0.quantity', 2)
        ->call('saveBulk')
        ->assertSet('showConfirmModal', true)
        ->call('executeSave')
        ->assertSet('showConfirmModal', false);

    expect(DailyCharge::where('resident_id', $this->resident->id)->count())->toBe(1);
});

test('月次請求一覧がステータスタブおよびモバイルビューを含めて正常に機能すること', function () {
    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 300,
        'status' => InvoiceStatus::Unbilled,
    ]);

    Livewire::test(ListMonthlyInvoices::class)
        ->assertSuccessful()
        ->set('activeTab', 'unbilled')
        ->assertSee('モバイル検証入居者')
        ->callTableAction('markPayment', $invoice, [
            'paid_at' => '2026-10-25',
            'payment_method' => PaymentMethod::BankTransfer->value,
        ])
        ->assertHasNoTableActionErrors();

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Paid);
});
