<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ResidentStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';       // 居住中
    case MovedOut = 'moved_out';  // 退去済み

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Active => '居住中',
            self::MovedOut => '退去済み',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::MovedOut => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::MovedOut => 'heroicon-o-x-circle',
        };
    }
}
