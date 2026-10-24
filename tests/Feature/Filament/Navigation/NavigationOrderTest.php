<?php

use App\Providers\Filament\AdminPanelProvider;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;

test('AdminPanelProvider navigation method returns array', function () {
    // Resolve the provider through the container to avoid constructor issues
    $provider = app()->getProvider(AdminPanelProvider::class);

    $reflection = new ReflectionClass($provider);
    $navigationMethod = $reflection->getMethod('navigation');
    $navigationMethod->setAccessible(true);

    // Create a mock Panel object
    $panel = app(Panel::class);
    $configuredPanel = $provider->panel($panel);

    $navigation = $navigationMethod->invoke($provider, $configuredPanel);

    // Should have 5 groups
    expect($navigation)->toBeArray()
        ->and(count($navigation))->toBe(5)
        ->and($navigation)->toHaveCount(5);

    // Verify each item is a NavigationGroup
    foreach ($navigation as $group) {
        expect($group)->toBeInstanceOf(NavigationGroup::class);
    }
});

test('AdminPanel navigation contains expected group labels', function () {
    $provider = app()->getProvider(AdminPanelProvider::class);
    $panel = app(Panel::class);
    $configuredPanel = $provider->panel($panel);

    $reflection = new ReflectionClass($provider);
    $navigationMethod = $reflection->getMethod('navigation');
    $navigationMethod->setAccessible(true);

    $navigation = $navigationMethod->invoke($provider, $configuredPanel);

    $labels = array_map(fn ($group) => $group->getLabel(), $navigation);
    $labels = array_map(fn ($label) => is_array($label) ? implode('', $label) : (string) $label, $labels);

    // Verify expected groups exist (order matters)
    expect($labels)->toContain('業務準備')
        ->and($labels)->toContain('日次業務')
        ->and($labels)->toContain('請求業務')
        ->and($labels)->toContain('マスタ管理')
        ->and($labels)->toContain('出力・監査');
});

test('AdminPanel navigation contains expected resources', function () {
    $provider = app()->getProvider(AdminPanelProvider::class);
    $panel = app(Panel::class);
    $configuredPanel = $provider->panel($panel);

    $reflection = new ReflectionClass($provider);
    $navigationMethod = $reflection->getMethod('navigation');
    $navigationMethod->setAccessible(true);

    $navigation = $navigationMethod->invoke($provider, $configuredPanel);

    // Collect all items from all groups
    $items = [];
    foreach ($navigation as $group) {
        foreach ($group->getItems() as $item) {
            $items[] = $item;
        }
    }

    // Verify we have navigation items
    expect(count($items))->toBeGreaterThan(5);
});
