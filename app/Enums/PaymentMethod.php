<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasLabel
{
    case BankTransfer = 'bank_transfer'; // 銀行振込
    case DirectDebit = 'direct_debit';   // 口座振替
    case Cash = 'cash';                  // 現金

    public function getLabel(): ?string
    {
        return match ($this) {
            self::BankTransfer => '銀行振込',
            self::DirectDebit => '口座振替',
            self::Cash => '現金',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::BankTransfer => 'info',
            self::DirectDebit => 'primary',
            self::Cash => 'warning',
        };
    }
}
