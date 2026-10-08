<?php

namespace App\Services\Invoice;

use App\Enums\TaxType;
use App\Models\TaxSetting;
use Carbon\Carbon;

class TaxCalculator
{
    public function __construct(
        private float $standardRate = 10.0,
        private float $reducedRate = 8.0,
    ) {}

    /**
     * 請求月での税率を取得
     */
    public static function getRatesForDate(Carbon $billingDate): array
    {
        $standardRate = TaxSetting::getRateForDate($billingDate);
        $reducedRate = TaxSetting::currentReducedRate();

        return [
            'standard' => $standardRate,
            'reduced' => $reducedRate,
        ];
    }

    /**
     * 標準税率課税額・軽減税率課税額・非課税額から税額を計算
     *
     * @return array{standard_tax: int, reduced_tax: int, total_tax: int, tax_breakdown: array}
     */
    public function calculate(
        int $standardTaxable,
        int $reducedTaxable,
        int $nonTaxable,
        ?float $standardRate = null,
        ?float $reducedRate = null
    ): array {
        $standardRate = $standardRate ?? $this->standardRate;
        $reducedRate = $reducedRate ?? $this->reducedRate;

        $standardTax = (int) round($standardTaxable * ($standardRate / 100));
        $reducedTax = (int) round($reducedTaxable * ($reducedRate / 100));
        $totalTax = $standardTax + $reducedTax;

        $taxBreakdown = [
            'standard' => [
                'taxable_amount' => $standardTaxable,
                'rate' => $standardRate,
                'tax_amount' => $standardTax,
            ],
            'reduced' => [
                'taxable_amount' => $reducedTaxable,
                'rate' => $reducedRate,
                'tax_amount' => $reducedTax,
            ],
            'non_taxable' => [
                'amount' => $nonTaxable,
            ],
        ];

        return [
            'standard_tax' => $standardTax,
            'reduced_tax' => $reducedTax,
            'total_tax' => $totalTax,
            'tax_breakdown' => $taxBreakdown,
        ];
    }
}