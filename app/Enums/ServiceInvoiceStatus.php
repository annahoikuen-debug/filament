<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ServiceInvoiceStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Sent = 'sent';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => '下書き',
            self::Confirmed => '確定済み',
            self::Sent => '送信済み',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Confirmed => 'info',
            self::Sent => 'success',
        };
    }
}
