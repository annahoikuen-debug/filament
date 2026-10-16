<?php

use App\Filament\Widgets\BillingAnomalySummaryWidget;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->user);
});

test('異常値サマリーウィジェットが正常にレンダリングされること', function () {
    Livewire::test(BillingAnomalySummaryWidget::class)
        ->assertSuccessful()
        ->assertSee('月次請求 異常値・整合性チェック');
});
