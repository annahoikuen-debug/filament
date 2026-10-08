<?php

namespace App\DTOs\Pdf;

use App\Models\MonthlyInvoice;

readonly class ReceiptPdfData
{
    public function __construct(
        public ResidentPdfData $resident,
        public FacilityPdfData $facility,
        public TaxInfoPdfData $taxInfo,
        public string $billingYearMonth,
        public string $receiptNumber,
        public string $receivedAt,
        public string $paymentMethodLabel,
        public int $rentSubtotal,
        public int $managementFeeSubtotal,
        public int $serviceSubtotal,
    ) {}

    public static function fromInvoice(MonthlyInvoice $invoice, ?array $facility = null): self
    {
        $invoice->loadMissing(['resident']);

        $resident = $invoice->resident;

        return new self(
            resident: ResidentPdfData::fromModel($resident),
            facility: FacilityPdfData::fromConfig($facility, $resident->facility_id ?? null),
            taxInfo: TaxInfoPdfData::fromInvoice($invoice),
            billingYearMonth: $invoice->billing_year_month,
            receiptNumber: $invoice->receipt_number ?? 'REC-' . str_replace('-', '', $invoice->billing_year_month) . '-' . str_pad($resident->id, 3, '0', STR_PAD_LEFT),
            receivedAt: $invoice->paid_at
                ? \Carbon\Carbon::parse($invoice->paid_at)->format('Y年m月d日')
                : now()->format('Y年m月d日'),
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
            'billing_year_month' => $this->billingYearMonth,
            'receipt_number' => $this->receiptNumber,
            'received_at' => $this->receivedAt,
            'payment_method_label' => $this->paymentMethodLabel,
            'rent_subtotal' => $this->rentSubtotal,
            'management_fee_subtotal' => $this->managementFeeSubtotal,
            'service_subtotal' => $this->serviceSubtotal,
        ];
    }
}