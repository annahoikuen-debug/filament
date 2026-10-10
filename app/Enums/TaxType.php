<?php

namespace App\Enums;

enum TaxType: string
{
    case NonTaxable = 'non_taxable';
    case Standard = 'standard';
    case Reduced = 'reduced';

    public function label(): string
    {
        return match ($this) {
            self::NonTaxable => '非課税',
            self::Standard => '標準税率',
            self::Reduced => '軽減税率',
        };
    }
}
