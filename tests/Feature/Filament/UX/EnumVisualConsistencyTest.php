<?php

use App\Enums\ResidentStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

test('ResidentStatus enum has consistent label/color/icon', function () {
    expect(ResidentStatus::Active->getLabel())->toBe('居住中')
        ->and(ResidentStatus::Active->getColor())->toBe('success')
        ->and(ResidentStatus::Active->getIcon())->toBe('heroicon-o-check-circle');

    expect(ResidentStatus::MovedOut->getLabel())->toBe('退去済み')
        ->and(ResidentStatus::MovedOut->getColor())->toBe('gray')
        ->and(ResidentStatus::MovedOut->getIcon())->toBe('heroicon-o-x-circle');
});

test('ResidentStatus enum implements Filament UI contracts', function () {
    expect(ResidentStatus::Active instanceof HasLabel)->toBeTrue()
        ->and(ResidentStatus::Active instanceof HasColor)->toBeTrue()
        ->and(ResidentStatus::Active instanceof HasIcon)->toBeTrue();
});
