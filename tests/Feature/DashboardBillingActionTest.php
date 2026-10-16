<?php

use App\Enums\InvoiceStatus;
use App\Filament\Pages\Dashboard;
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
        'name' => 'ダッシュボードテスト入居者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
    ]);
});

test('ダッシュボードの請求生成ボタンラベルが未請求件数に応じて動的に変化すること', function () {
    $currentYm = now()->format('Y-m');

    // 請求データが0件のとき
    $component = Livewire::test(Dashboard::class);
    $action = $component->instance()->getAction('generateBilling');
    expect($action)->not->toBeNull()
        ->and((string) $action->getLabel())->toBe('請求データを一括生成する');

    // 未請求データが1件あるとき
    MonthlyInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => $currentYm,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $component = Livewire::test(Dashboard::class);
    $action = $component->instance()->getAction('generateBilling');
    expect((string) $action->getLabel())->toContain('未請求');
});

test('ダッシュボードの請求生成アクションが正常に呼び出し可能であること', function () {
    $component = Livewire::test(Dashboard::class);

    $component->call('mountAction', 'generateBilling')
        ->assertHasNoErrors()
        ->assertActionMounted('generateBilling');
});

