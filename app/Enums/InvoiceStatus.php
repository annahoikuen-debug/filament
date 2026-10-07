<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasColor, HasLabel
{
    case Unbilled = 'unbilled'; // 未請求
    case Billed = 'billed';     // 請求済
    case Paid = 'paid';         // 入金済

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Unbilled => '未請求',
            self::Billed => '請求済',
            self::Paid => '入金済',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Unbilled => 'warning',
            self::Billed => 'info',
            self::Paid => 'success',
        };
    }
}
