<?php

use App\Enums\ResidentStatus;

test('ResidentStatus enum has consistent label/color/icon', function () {
    expect(ResidentStatus::Active->getLabel())->toBe('居住中')
        ->and(ResidentStatus::Active->getColor())->toBe('success')
        ->and(ResidentStatus::Active->getIcon())->toBe('heroicon-o-check-circle');
    
    expect(ResidentStatus::MovedOut->getLabel())->toBe('退去済み')
        ->and(ResidentStatus::MovedOut->getColor())->toBe('gray')
        ->and(ResidentStatus::MovedOut->getIcon())->toBe('heroicon-o-x-circle');
});

test('ResidentStatus enum implements Filament UI contracts', function () {
    expect(ResidentStatus::Active instanceof \Filament\Support\Contracts\HasLabel)->toBeTrue()
        ->and(ResidentStatus::Active instanceof \Filament\Support\Contracts\HasColor)->toBeTrue()
        ->and(ResidentStatus::Active instanceof \Filament\Support\Contracts\HasIcon)->toBeTrue();
});