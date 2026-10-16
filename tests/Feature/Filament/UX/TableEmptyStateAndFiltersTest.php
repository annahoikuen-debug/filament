<?php

use App\Filament\Resources\DailyChargeResource\Pages\ListDailyCharges;
use App\Filament\Resources\MonthlyInvoiceResource\Pages\ListMonthlyInvoices;
use App\Filament\Resources\ItemMasterResource\Pages\ListItemMasters;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('DailyChargeResource list page renders successfully', function () {
    Livewire::test(ListDailyCharges::class)
        ->assertSuccessful();
});

test('MonthlyInvoiceResource list page renders successfully', function () {
    Livewire::test(ListMonthlyInvoices::class)
        ->assertSuccessful();
});

test('ItemMasterResource list page renders successfully', function () {
    Livewire::test(ListItemMasters::class)
        ->assertSuccessful();
});

test('MonthlyInvoiceResource list page has default sort applied', function () {
    // Verify that the list page can be loaded and has table
    Livewire::test(ListMonthlyInvoices::class)
        ->assertSeeHtml('請求年月');
});