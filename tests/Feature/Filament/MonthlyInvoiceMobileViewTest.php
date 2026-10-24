<?php

use App\Enums\InvoiceStatus;
use App\Filament\Resources\MonthlyInvoiceResource\Pages\ListMonthlyInvoices;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->user);

    $this->resident = Resident::create([
        'room_number' => '101',
        'name' => 'テスト入居者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
    ]);
});

test('月次請求一覧テーブルに主要情報が表示されること', function () {
    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 5000,
        'status' => InvoiceStatus::Unbilled,
    ]);

    Livewire::test(ListMonthlyInvoices::class)
        ->assertSuccessful()
        ->assertSee('101')
        ->assertSee('テスト入居者')
        ->assertSee('2026-10');
});

test('ステータスクイックフィルタタブが機能すること', function () {
    MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $billedResident = Resident::create([
        'room_number' => '102',
        'name' => '請求済入居者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
    ]);

    MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $billedResident->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Billed,
    ]);

    // 未請求タブ
    Livewire::test(ListMonthlyInvoices::class)
        ->set('activeTab', 'unbilled')
        ->assertSee('テスト入居者')
        ->assertDontSee('請求済入居者');

    // 請求済タブ
    Livewire::test(ListMonthlyInvoices::class)
        ->set('activeTab', 'billed')
        ->assertSee('請求済入居者')
        ->assertDontSee('テスト入居者');
});
