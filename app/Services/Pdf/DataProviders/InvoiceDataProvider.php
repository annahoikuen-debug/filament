<?php

namespace App\Services\Pdf\DataProviders;

use App\DTOs\Pdf\InvoicePdfData;
use App\DTOs\Pdf\ReceiptPdfData;
use App\Models\MonthlyInvoice;

class InvoiceDataProvider
{
    public function getInvoiceData(MonthlyInvoice $invoice, ?array $facility = null): InvoicePdfData
    {
        return InvoicePdfData::fromInvoice($invoice, $facility);
    }

    public function getReceiptData(MonthlyInvoice $invoice, ?array $facility = null): ReceiptPdfData
    {
        return ReceiptPdfData::fromInvoice($invoice, $facility);
    }

    /**
     * @return InvoicePdfData[]
     */
    public function getMonthlyInvoicesData(string $yearMonth, ?int $facilityId = null): array
    {
        $query = MonthlyInvoice::with([
            'resident.dailyCharges' => function ($q) use ($yearMonth) {
                $q->forYearMonth($yearMonth)
                  ->with('chargeItem')
                  ->orderBy('date');
            },
        ])->forYearMonth($yearMonth);

        if ($facilityId) {
            $query->whereHas('resident', function ($q) use ($facilityId) {
                $q->where('facility_id', $facilityId);
            });
        }

        return $query->get()
            ->map(fn ($invoice) => $this->getInvoiceData($invoice))
            ->toArray();
    }
}