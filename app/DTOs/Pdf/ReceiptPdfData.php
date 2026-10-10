<?php

namespace App\DTOs\Pdf;

use App\Models\MonthlyInvoice;
use Carbon\Carbon;

readonly class ReceiptPdfData
{
    public function __construct(
        public ResidentPdfData $resident,
        public FacilityPdfData $facility,
        public TaxInfoPdfData $taxInfo,
        public string $billingYearMonth,
        public string $receiptNumber,
        public string $receivedAt,
        public ?string $paymentMethodLabel,
        public int $rentSubtotal,
        public int $managementFeeSubtotal,
        public int $serviceSubtotal,
        public string $dateMode = 'auto',
        public ?string $customReceivedAt = null,
    ) {}

    public static function fromInvoice(MonthlyInvoice $invoice, ?array $facility = null, ?array $templateConfig = null): self
    {
        $invoice->loadMissing(['resident']);

        $resident = $invoice->resident;

        $dateMode = $invoice->receipt_date_mode ?? 'auto';
        $customReceivedAt = $invoice->custom_receipt_date?->format('Y-m-d');

        $receivedAt = self::resolveReceivedAt($invoice, $dateMode, $customReceivedAt);

        return new self(
            resident: ResidentPdfData::fromModel($resident),
            facility: FacilityPdfData::fromConfig($facility, $resident->facility_id ?? null),
            taxInfo: TaxInfoPdfData::fromInvoice($invoice),
            billingYearMonth: $invoice->billing_year_month,
            receiptNumber: $invoice->receipt_number ?? 'REC-'.str_replace('-', '', $invoice->billing_year_month).'-'.str_pad($resident->id, 3, '0', STR_PAD_LEFT),
            receivedAt: $receivedAt,
            paymentMethodLabel: $invoice->payment_method?->getLabel() ?? '銀行振込',
            rentSubtotal: (int) $invoice->rent_subtotal,
            managementFeeSubtotal: (int) $invoice->management_fee_subtotal,
            serviceSubtotal: (int) $invoice->service_subtotal,
            dateMode: $dateMode,
            customReceivedAt: $customReceivedAt,
        );
    }

    /**
     * 領収日を解決する
     *
     * @param  string  $dateMode  'auto' または 'manual'
     * @param  string|null  $customReceivedAt  手動指定時の日付 (Y-m-d 形式)
     */
    private static function resolveReceivedAt(MonthlyInvoice $invoice, string $dateMode, ?string $customReceivedAt): string
    {
        if ($dateMode === 'manual' && $customReceivedAt) {
            try {
                return Carbon::parse($customReceivedAt)->format('Y年m月d日');
            } catch (\Throwable) {
                // パース失敗時は自動モードにフォールバック
            }
        }

        // auto mode: 入金日を使用、なければ現在日時
        if ($invoice->paid_at) {
            try {
                return Carbon::parse($invoice->paid_at)->format('Y年m月d日');
            } catch (\Throwable) {
                // パース失敗時は現在日時にフォールバック
            }
        }

        return now()->format('Y年m月d日');
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
            'date_mode' => $this->dateMode,
            'custom_received_at' => $this->customReceivedAt,
        ];
    }
}
