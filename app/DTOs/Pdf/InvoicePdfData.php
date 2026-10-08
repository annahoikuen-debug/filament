<?php

namespace App\DTOs\Pdf;

use App\Models\MonthlyInvoice;

readonly class InvoicePdfData
{
    public function __construct(
        public ResidentPdfData $resident,
        public FacilityPdfData $facility,
        public TaxInfoPdfData $taxInfo,
        public array $dailyCharges,
        public string $billingYearMonth,
        public string $invoiceNumber,
        public string $issuedAt,
        public ?string $paymentMethodLabel,
        public int $rentSubtotal,
        public int $managementFeeSubtotal,
        public int $serviceSubtotal,
    ) {}

    public static function fromInvoice(MonthlyInvoice $invoice, ?array $facility = null): self
    {
        $invoice->loadMissing([
            'resident.dailyCharges' => function ($query) use ($invoice) {
                $query->forYearMonth($invoice->billing_year_month)
                    ->with('chargeItem')
                    ->orderBy('date');
            },
        ]);

        $resident = $invoice->resident;
        $dailyCharges = $resident->dailyCharges->map(
            fn ($charge) => DailyChargePdfData::fromModel($charge)
        )->toArray();

        return new self(
            resident: ResidentPdfData::fromModel($resident),
            facility: FacilityPdfData::fromConfig($facility, $resident->facility_id ?? null),
            taxInfo: TaxInfoPdfData::fromInvoice($invoice),
            dailyCharges: $dailyCharges,
            billingYearMonth: $invoice->billing_year_month,
            invoiceNumber: 'INV-' . str_replace('-', '', $invoice->billing_year_month) . '-' . str_pad($resident->id, 3, '0', STR_PAD_LEFT),
            issuedAt: now()->format('Y年m月d日'),
            paymentMethodLabel: $invoice->payment_method?->getLabel() ?? '銀行振込',
            rentSubtotal: (int) $invoice->rent_subtotal,
            managementFeeSubtotal: (int) $invoice->management_fee_subtotal,
            serviceSubtotal: (int) $invoice->service_subtotal,
        );
    }

    public function toArray(): array
    {
        return [
            'resident' => $this->resident->toArray(),
            'facility' => $this->facility->toArray(),
            'tax_info' => $this->taxInfo->toArray(),
            'daily_charges' => array_map(fn ($c) => $c->toArray(), $this->dailyCharges),
            'billing_year_month' => $this->billingYearMonth,
            'invoice_number' => $this->invoiceNumber,
            'issued_at' => $this->issuedAt,
            'payment_method_label' => $this->paymentMethodLabel,
            'rent_subtotal' => $this->rentSubtotal,
            'management_fee_subtotal' => $this->managementFeeSubtotal,
            'service_subtotal' => $this->serviceSubtotal,
        ];
    }
}