<?php

use App\Filament\Pages\InvoiceSimulationPage;
use App\Models\MonthlyInvoice;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->user);
});

test('シミュレーションページが正常に表示されること', function () {
    Livewire::test(InvoiceSimulationPage::class)
        ->assertSuccessful()
        ->assertSee('請求計算シミュレーター');
});

test('日割り条件を入力して試算が正しく実行されMonthlyInvoiceがDB作成されないこと', function () {
    $initialCount = MonthlyInvoice::count();

    Livewire::test(InvoiceSimulationPage::class)
        ->fillForm([
            'year_month' => '2026-10',
            'move_in_date' => '2026-10-16',
            'move_out_date' => null,
            'base_rent' => 62000,
            'base_management_fee' => 31000,
            'service_total' => 5000,
        ])
        ->call('simulate')
        ->assertSet('hasCalculated', true)
        ->assertSet('resultLivingDays', 16)
        ->assertSet('resultRent', 32000)
        ->assertSet('resultManagementFee', 16000)
        ->assertSet('resultTotalAmount', 53000); // 32000 + 16000 + 5000

    expect(MonthlyInvoice::count())->toBe($initialCount);
});
