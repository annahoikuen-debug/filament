<?php

namespace App\Services\Invoice;

use App\Enums\TaxType;
use App\Models\RecurringCharge;
use App\Models\Resident;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecurringChargeAggregator
{
    /**
     * 対象入居者の定期課金を取得し、税区分別に集計
     *
     * @param  array<int>  $residentIds
     * @return array<int, array<string, int>> [resident_id => [tax_type => subtotal]]
     */
    public function aggregate(array $residentIds, string $yearMonth, Carbon $billingDate): array
    {
        if (empty($residentIds)) {
            return [];
        }

        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth();

        $charges = RecurringCharge::whereIn('resident_id', $residentIds)
            ->where('is_active', true)
            ->where('start_date', '<=', $endDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $startDate);
            })
            ->with('chargeItem')
            ->get()
            ->groupBy('resident_id');

        $aggregates = [];
        foreach ($residentIds as $residentId) {
            $aggregates[$residentId] = [
                TaxType::Standard->value => 0,
                TaxType::Reduced->value => 0,
                TaxType::NonTaxable->value => 0,
            ];
        }

        foreach ($charges as $residentId => $residentCharges) {
            /** @var Collection<int, RecurringCharge> $residentCharges */
            foreach ($residentCharges as $recurring) {
                $chargeItem = $recurring->chargeItem;
                if (! $chargeItem) {
                    continue;
                }

                // 適用日数を計算（月額の場合は満月、日額の場合は在籍日数）
                // 注: 入居者の move_in/out は別途 Resident から取得する必要があるため、
                // ここでは全日数として計算し、呼び出し側で調整する
                $applicableDays = $this->calculateApplicableDays($recurring, $yearMonth);

                if ($applicableDays === 0) {
                    continue;
                }

                $unitPrice = $chargeItem->getPriceForDate($billingDate) ?? 0;
                if ($unitPrice === 0) {
                    continue;
                }

                $quantity = $recurring->quantity;
                if ($recurring->frequency === 'monthly') {
                    $subtotal = $unitPrice * $quantity;
                } else {
                    $subtotal = $unitPrice * $quantity * $applicableDays;
                }

                $taxType = $chargeItem->tax_type ?? TaxType::Standard;
                $aggregates[$residentId][$taxType->value] += $subtotal;
            }
        }

        return $aggregates;
    }

    /**
     * 定期課金の適用日数を計算
     * （入居期間考慮は呼び出し側で行うため、ここでは月内全日数を返す）
     */
    private function calculateApplicableDays(RecurringCharge $recurring, string $yearMonth): int
    {
        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth();

        $chargeStart = Carbon::parse($recurring->start_date);
        $chargeEnd = $recurring->end_date ? Carbon::parse($recurring->end_date) : null;

        $effectiveStart = $chargeStart > $startDate ? $chargeStart : $startDate;
        $effectiveEnd = $chargeEnd && $chargeEnd < $endDate ? $chargeEnd : $endDate;

        if ($effectiveStart > $effectiveEnd) {
            return 0;
        }

        return $effectiveStart->diffInDays($effectiveEnd) + 1;
    }
}
