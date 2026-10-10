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
        public ?int $invoiceId,
        public string $billingYearMonth,
        public string $invoiceNumber,
        public string $issuedAt,
        public ?string $paymentMethodLabel,
        public int $rentSubtotal,
        public int $managementFeeSubtotal,
        public int $serviceSubtotal,
        public string $dateMode = 'auto',
        public ?string $customIssuedAt = null,
        public ?array $calculationBasis = null,
        public bool $showCalculationBasis = true,
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

        // 計算根拠データを生成
        $calculationBasis = self::buildCalculationBasis($invoice);
        $showCalculationBasis = $templateConfig['show_calculation_basis'] ?? true;

        return new self(
            invoiceId: $invoice->id,
            resident: ResidentPdfData::fromModel($resident),
            facility: FacilityPdfData::fromConfig($facility, $resident->facility_id ?? null),
            taxInfo: TaxInfoPdfData::fromInvoice($invoice),
            dailyCharges: $dailyCharges,
            billingYearMonth: $invoice->billing_year_month,
            invoiceNumber: 'INV-'.str_replace('-', '', $invoice->billing_year_month).'-'.str_pad($resident->id, 3, '0', STR_PAD_LEFT),
            issuedAt: $issuedAt,
            paymentMethodLabel: $invoice->payment_method?->getLabel() ?? '銀行振込',
            rentSubtotal: (int) $invoice->rent_subtotal,
            managementFeeSubtotal: (int) $invoice->management_fee_subtotal,
            serviceSubtotal: (int) $invoice->service_subtotal,
            dateMode: $dateMode,
            customIssuedAt: $customIssuedAt,
            calculationBasis: $calculationBasis,
            showCalculationBasis: $showCalculationBasis,
        );
    }

    /**
     * 計算根拠データを構築
     *
     * @return array{
     *     rent: array{monthly_amount: int, days_in_month: int, living_days: int, prorated_amount: int, is_prorated: bool},
     *     management_fee: array{monthly_amount: int, days_in_month: int, living_days: int, prorated_amount: int, is_prorated: bool},
     *     tax_breakdown: array{
     *         standard: array{taxable_amount: int, rate: float, tax_amount: int},
     *         reduced: array{taxable_amount: int, rate: float, tax_amount: int},
     *         non_taxable: array{amount: int}
     *     },
     *     total: array{subtotal: int, tax_amount: int, total_with_tax: int}
     * }
     */
    private static function buildCalculationBasis(MonthlyInvoice $invoice): array
    {
        $yearMonth = $invoice->billing_year_month;
        $year = (int) substr($yearMonth, 0, 4);
        $month = (int) substr($yearMonth, 5, 2);
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        $resident = $invoice->resident;
        $moveInDate = $resident->move_in_date ? Carbon::parse($resident->move_in_date) : null;
        $moveOutDate = $resident->move_out_date ? Carbon::parse($resident->move_out_date) : null;

        // 在籍日数を計算
        $livingDays = $daysInMonth;
        $isProrated = false;

        if ($moveInDate && $moveInDate->format('Y-m') === $yearMonth) {
            // 月中途入居
            $livingDays = $daysInMonth - $moveInDate->day + 1;
            $isProrated = true;
        } elseif ($moveOutDate && $moveOutDate->format('Y-m') === $yearMonth) {
            // 月中退去
            $livingDays = $moveOutDate->day;
            $isProrated = true;
        } elseif ($moveInDate && $moveOutDate && $moveInDate->lt($yearMonth) && $moveOutDate->gt($yearMonth)) {
            // 期間中ずっと在籍（通常月）
            $isProrated = false;
        }

        // 家賃・管理費の月額
        $rentMonthly = (int) $resident->base_rent;
        $managementFeeMonthly = (int) $resident->base_management_fee;

        // 按分計算
        $rentProrated = $isProrated && $daysInMonth > 0
            ? (int) round(($rentMonthly / $daysInMonth) * $livingDays)
            : $rentMonthly;
        $managementFeeProrated = $isProrated && $daysInMonth > 0
            ? (int) round(($managementFeeMonthly / $daysInMonth) * $livingDays)
            : $managementFeeMonthly;

        // tax_breakdown から税額内訳を取得
        $taxBreakdown = $invoice->tax_breakdown ?? [
            'standard' => ['taxable_amount' => 0, 'rate' => 0, 'tax_amount' => 0],
            'reduced' => ['taxable_amount' => 0, 'rate' => 0, 'tax_amount' => 0],
            'non_taxable' => ['amount' => 0],
        ];

        return [
            'rent' => [
                'monthly_amount' => $rentMonthly,
                'days_in_month' => $daysInMonth,
                'living_days' => $livingDays,
                'prorated_amount' => $rentProrated,
                'is_prorated' => $isProrated,
            ],
            'management_fee' => [
                'monthly_amount' => $managementFeeMonthly,
                'days_in_month' => $daysInMonth,
                'living_days' => $livingDays,
                'prorated_amount' => $managementFeeProrated,
                'is_prorated' => $isProrated,
            ],
            'tax_breakdown' => [
                'standard' => [
                    'taxable_amount' => (int) ($taxBreakdown['standard']['taxable_amount'] ?? 0),
                    'rate' => (float) ($taxBreakdown['standard']['rate'] ?? 0),
                    'tax_amount' => (int) ($taxBreakdown['standard']['tax_amount'] ?? 0),
                ],
                'reduced' => [
                    'taxable_amount' => (int) ($taxBreakdown['reduced']['taxable_amount'] ?? 0),
                    'rate' => (float) ($taxBreakdown['reduced']['rate'] ?? 0),
                    'tax_amount' => (int) ($taxBreakdown['reduced']['tax_amount'] ?? 0),
                ],
                'non_taxable' => [
                    'amount' => (int) ($taxBreakdown['non_taxable']['amount'] ?? 0),
                ],
            ],
            'total' => [
                'subtotal' => $rentProrated + $managementFeeProrated + (int) $invoice->service_subtotal,
                'tax_amount' => (int) $invoice->tax_amount,
                'total_with_tax' => (int) $invoice->total_with_tax,
            ],
        ];
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
            'invoice_id' => $this->invoiceId,
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
            'calculation_basis' => $this->calculationBasis,
            'show_calculation_basis' => $this->showCalculationBasis,
        ];
    }
}
