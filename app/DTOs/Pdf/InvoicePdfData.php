<?php

namespace App\DTOs\Pdf;

use App\Models\MonthlyInvoice;
use Carbon\Carbon;

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
        public string $dateMode = 'auto',
        public ?string $customIssuedAt = null,
    ) {}

    public static function fromInvoice(MonthlyInvoice $invoice, ?array $facility = null, ?array $templateConfig = null): self
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

        $dateMode = $invoice->invoice_date_mode ?? 'auto';
        $customIssuedAt = $invoice->custom_invoice_date?->format('Y-m-d');

        $issuedAt = self::resolveIssuedAt($invoice, $dateMode, $customIssuedAt);

        return new self(
            resident: ResidentPdfData::fromModel($resident),
            facility: FacilityPdfData::fromConfig($facility, $resident->facility_id ?? null),
            taxInfo: TaxInfoPdfData::fromInvoice($invoice),
            dailyCharges: $dailyCharges,
            billingYearMonth: $invoice->billing_year_month,
            invoiceNumber: 'INV-' . str_replace('-', '', $invoice->billing_year_month) . '-' . str_pad($resident->id, 3, '0', STR_PAD_LEFT),
            issuedAt: $issuedAt,
            paymentMethodLabel: $invoice->payment_method?->getLabel() ?? '銀行振込',
            rentSubtotal: (int) $invoice->rent_subtotal,
            managementFeeSubtotal: (int) $invoice->management_fee_subtotal,
            serviceSubtotal: (int) $invoice->service_subtotal,
            dateMode: $dateMode,
            customIssuedAt: $customIssuedAt,
        );
    }

    /**
     * 発行日を解決する
     *
     * @param  string  $dateMode  'auto' または 'manual'
     * @param  string|null  $customIssuedAt  手動指定時の日付 (Y-m-d 形式)
     */
    private static function resolveIssuedAt(MonthlyInvoice $invoice, string $dateMode, ?string $customIssuedAt): string
    {
        if ($dateMode === 'manual' && $customIssuedAt) {
            try {
                return Carbon::parse($customIssuedAt)->format('Y年m月d日');
            } catch (\Throwable) {
                // パース失敗時は自動モードにフォールバック
            }
        }

        // auto mode: 請求年月の初日を使用
        try {
            return Carbon::createFromFormat('Y-m', $invoice->billing_year_month)->format('Y年m月d日');
        } catch (\Throwable) {
            return now()->format('Y年m月d日');
        }
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
            'date_mode' => $this->dateMode,
            'custom_issued_at' => $this->customIssuedAt,
        ];
    }
}