<?php

namespace App\DTOs\Pdf;

use App\Models\MonthlyInvoice;

readonly class TaxInfoPdfData
{
    public function __construct(
        public int $nonTaxable,
        public int $taxable,
        public int $taxRate,
        public int $taxAmount,
        public int $totalWithTax,
    ) {}

    public static function fromInvoice(MonthlyInvoice $invoice): self
    {
        return new self(
            nonTaxable: (int) $invoice->non_taxable_amount,
            taxable: (int) $invoice->taxable_amount,
            taxRate: (int) $invoice->tax_rate,
            taxAmount: (int) $invoice->tax_amount,
            totalWithTax: (int) $invoice->total_with_tax,
        );
    }

    public function toArray(): array
    {
        return [
            'non_taxable' => $this->nonTaxable,
            'taxable' => $this->taxable,
            'tax_rate' => $this->taxRate,
            'tax_amount' => $this->taxAmount,
            'total_with_tax' => $this->totalWithTax,
        ];
    }
}
