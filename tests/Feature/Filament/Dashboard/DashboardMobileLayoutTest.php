<?php

use App\Filament\Widgets\PendingInvoicesWidget;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\RecentActivityWidget;
use App\Filament\Widgets\UnpaidInvoicesAlert;
use App\Filament\Widgets\UnprocessedDailyChargesWidget;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->user);
});

test('ダッシュボードの主要ウィジェットが存在し動作すること', function () {
    expect(class_exists(UnprocessedDailyChargesWidget::class))->toBeTrue()
        ->and(class_exists(UnpaidInvoicesAlert::class))->toBeTrue()
        ->and(class_exists(PendingInvoicesWidget::class))->toBeTrue()
        ->and(class_exists(QuickActionsWidget::class))->toBeTrue();
});

test('クイックアクションウィジェットに必要なURLが含まれていること', function () {
    $widget = new QuickActionsWidget();
    $html = $widget->view();

    expect($html)->toContain('daily-charges/create')
        ->and($html)->toContain('monthly-invoices/create');
});
