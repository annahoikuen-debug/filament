<?php

use App\Filament\Pages\IntegratedBillingDashboard;
use App\Models\Facility;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->user);
    $this->facility = Facility::factory()->create();
});

test('統合請求管理ダッシュボードが正常に表示されること', function () {
    Livewire::test(IntegratedBillingDashboard::class)
        ->assertSuccessful();
});

test('CSVエクスポートアクションが実行できること', function () {
    Livewire::test(IntegratedBillingDashboard::class)
        ->callTableAction('exportCsv')
        ->assertHasNoTableActionErrors();
});
