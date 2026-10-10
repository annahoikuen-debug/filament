<?php

namespace App\Services\Invoice;

use App\Enums\TaxType;
use Illuminate\Support\Facades\DB;

class DailyChargeAggregator
{
    /**
     * 指定期間・対象入居者の日々課金を税区分別に集計
     *
     * @param  array<int>  $residentIds
     * @return array<int, array<string, int>> [resident_id => [tax_type => subtotal]]
     */
    public function aggregate(array $residentIds, string $startDate, string $endDate): array
    {
        if (empty($residentIds)) {
            return [];
        }

        $results = DB::table('daily_charges')
            ->join('charge_items', 'daily_charges.charge_item_id', '=', 'charge_items.id')
            ->whereIn('daily_charges.resident_id', $residentIds)
            ->whereBetween('daily_charges.date', [$startDate, $endDate])
            ->selectRaw('daily_charges.resident_id, charge_items.tax_type, SUM(daily_charges.unit_price * daily_charges.quantity) as subtotal')
            ->groupBy('daily_charges.resident_id', 'charge_items.tax_type')
            ->get();

        $aggregates = [];
        foreach ($results as $row) {
            $taxType = $row->tax_type ?? TaxType::Standard->value;
            $aggregates[$row->resident_id][$taxType] = (int) $row->subtotal;
        }

        // 全税区分をデフォルト0で初期化
        foreach ($residentIds as $residentId) {
            if (! isset($aggregates[$residentId])) {
                $aggregates[$residentId] = [];
            }
            foreach (TaxType::cases() as $taxType) {
                $aggregates[$residentId][$taxType->value] = $aggregates[$residentId][$taxType->value] ?? 0;
            }
        }

        return $aggregates;
    }
}
