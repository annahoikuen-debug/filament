<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ResidentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';       // 入居中
    case MovedOut = 'moved_out';  // 退去

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Active => '入居中',
            self::MovedOut => '退去',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::MovedOut => 'gray',
        };
    }
}
