<?php

use App\Filament\Widgets\UnprocessedDailyChargesWidget;
use App\Filament\Widgets\PendingInvoicesWidget;
use App\Filament\Widgets\RecentActivityWidget;
use App\Filament\Widgets\QuickActionsWidget;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('All 4 new widget classes exist and are valid', function () {
    expect(class_exists(UnprocessedDailyChargesWidget::class))->toBeTrue()
        ->and(class_exists(PendingInvoicesWidget::class))->toBeTrue()
        ->and(class_exists(RecentActivityWidget::class))->toBeTrue()
        ->and(class_exists(QuickActionsWidget::class))->toBeTrue();
});

test('QuickActionsWidget generates action links', function () {
    $widget = new QuickActionsWidget();
    $view = $widget->view();
    
    expect($view)->toContain('href');
});

test('UnprocessedDailyChargesWidget class structure', function () {
    $reflection = new ReflectionClass(UnprocessedDailyChargesWidget::class);
    expect($reflection->getParentClass()->getName())->toBe('Filament\Widgets\StatsOverviewWidget')
        ->and($reflection->hasMethod('getStats'))->toBeTrue()
        ->and($reflection->hasMethod('getHeading'))->toBeTrue();
});

test('PendingInvoicesWidget class structure', function () {
    $reflection = new ReflectionClass(PendingInvoicesWidget::class);
    expect($reflection->getParentClass()->getName())->toBe('Filament\Widgets\StatsOverviewWidget')
        ->and($reflection->hasMethod('getStats'))->toBeTrue();
});

test('RecentActivityWidget class structure', function () {
    $reflection = new ReflectionClass(RecentActivityWidget::class);
    expect($reflection->hasMethod('view'))->toBeTrue();
});

test('QuickActionsWidget class structure', function () {
    $reflection = new ReflectionClass(QuickActionsWidget::class);
    expect($reflection->hasMethod('view'))->toBeTrue();
});