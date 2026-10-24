<?php

namespace App\Services;

use App\Models\ChargeItemPrice;
use App\Models\DailyCharge;
use App\Models\DailyChargePreset;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Step 11-12: プリセット一括展開サービス
 *
 * - 選択プリセット × 複数入居者 × 対象日付 から DailyCharge バルクインサート用レコード配列を生成
 * - 曜日パターン（毎週月・水・金 / 平日のみ）による日付範囲一括展開
 * - 同一日・同一品目の重複スキップ（DB一意チェック）
 */
class DailyChargePresetService
{
    /**
     * プリセットを複数入居者・複数日付へ展開し、レコード配列を返す
     *
     * @param  DailyChargePreset  $preset  プリセット
     * @param  array<int>  $residentIds  入居者ID
     * @param  array<string>|Collection<string>  $dates  対象日（Y-m-d）
     * @param  array<int, int>  $quantityOverrides  charge_item_id => 数量 の上書き
     * @return array<int, array<string, mixed>> DailyCharge 生成用レコード配列
     */
    public function buildRecords(DailyChargePreset $preset, array $residentIds, array|Collection $dates, array $quantityOverrides = []): array
    {
        $preset->loadMissing(['items.chargeItem']);

        // 品目ごとの価格履歴を一括取得（N+1防止）
        $itemIds = $preset->items->pluck('charge_item_id')->unique();
        $priceMap = $this->buildPriceMap($itemIds, collect($dates));

        $records = [];

        foreach ($residentIds as $residentId) {
            foreach ($dates as $date) {
                foreach ($preset->items as $presetItem) {
                    $quantity = $quantityOverrides[$presetItem->charge_item_id] ?? $presetItem->default_quantity;
                    if ($quantity <= 0) {
                        continue;
                    }

                    $unitPrice = $priceMap[$presetItem->charge_item_id][$date]
                        ?? $presetItem->chargeItem?->default_price
                        ?? 0;

                    $records[] = [
                        'resident_id' => $residentId,
                        'charge_item_id' => $presetItem->charge_item_id,
                        'date' => $date,
                        'unit_price' => (int) $unitPrice,
                        'quantity' => (int) $quantity,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        return $records;
    }

    /**
     * レコードを一括登録（重複スキップ付き）
     *
     * @return array{created: int, skipped: int}
     */
    public function bulkCreate(array $records): array
    {
        if ($records === []) {
            return ['created' => 0, 'skipped' => 0];
        }

        // 同一日・同一品目・同一入居者の重複検出（Step 14: 排他制御）
        $existing = $this->existingKeyMap($records);
        $seen = [];
        $toInsert = [];
        $skipped = 0;

        foreach ($records as $record) {
            $key = $record['resident_id'].'|'.$record['charge_item_id'].'|'.$record['date'];
            if (isset($existing[$key]) || isset($seen[$key])) {
                $skipped++;

                continue;
            }
            $seen[$key] = true;
            $toInsert[] = $record;
        }

        // バルクインサート（Observerを回避し高速化。facility_idは事前補完）
        if ($toInsert !== []) {
            $facilityMap = Resident::whereIn('id', collect($toInsert)->pluck('resident_id')->unique())
                ->pluck('facility_id', 'id');

            foreach ($toInsert as &$record) {
                $record['facility_id'] = $record['facility_id'] ?? $facilityMap[$record['resident_id']] ?? null;
            }
            unset($record);

            foreach (array_chunk($toInsert, 500) as $chunk) {
                DailyCharge::query()->insert($chunk);
            }
        }

        return ['created' => count($toInsert), 'skipped' => $skipped];
    }

    /**
     * プリセットを日付範囲＋曜日パターンへ展開して一括登録（Step 12）
     *
     * @param  array<int>  $residentIds
     * @param  array<int>  $weekdays  0=日 ... 6=土（Carbon::SUNDAY 等）。空配列=範囲内すべて
     * @param  array<int, int>  $quantityOverrides
     * @return array{created: int, skipped: int, applied_dates: list<string>}
     */
    public function applySchedule(
        DailyChargePreset $preset,
        array $residentIds,
        string $startDate,
        string $endDate,
        array $weekdays = [],
        array $quantityOverrides = [],
    ): array {
        $dates = $this->expandDates($startDate, $endDate, $weekdays);
        if ($dates === []) {
            return ['created' => 0, 'skipped' => 0, 'applied_dates' => []];
        }

        $records = $this->buildRecords($preset, $residentIds, $dates, $quantityOverrides);
        $result = $this->bulkCreate($records);

        return $result + ['applied_dates' => $dates];
    }

    /**
     * 日付範囲を曜日パターンで展開
     *
     * @param  array<int>  $weekdays  空=全日
     * @return list<string> Y-m-d
     */
    public function expandDates(string $startDate, string $endDate, array $weekdays = []): array
    {
        $start = CarbonImmutable::parse($startDate);
        $end = CarbonImmutable::parse($endDate);
        if ($end->lt($start)) {
            return [];
        }

        $dates = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            if ($weekdays === [] || in_array($d->dayOfWeek, $weekdays, true)) {
                $dates[] = $d->toDateString();
            }
        }

        return $dates;
    }

    /**
     * 価格履歴マップ [charge_item_id][date] => price
     */
    protected function buildPriceMap(Collection $itemIds, Collection $dates): array
    {
        $map = [];
        if ($itemIds->isEmpty() || $dates->isEmpty()) {
            return $map;
        }

        $min = $dates->min();
        $max = $dates->max();

        $prices = ChargeItemPrice::query()
            ->whereIn('charge_item_id', $itemIds)
            ->where(function ($q) use ($min, $max) {
                $q->where(function ($qq) use ($min, $max) {
                    $qq->whereDate('effective_from', '<=', $max)
                        ->where(function ($qqq) use ($min) {
                            $qqq->whereNull('effective_until')->orWhereDate('effective_until', '>=', $min);
                        });
                });
            })
            ->orderBy('effective_from')
            ->get(['charge_item_id', 'price', 'effective_from', 'effective_until']);

        foreach ($dates as $date) {
            foreach ($itemIds as $itemId) {
                $match = $prices->first(function ($p) use ($itemId, $date) {
                    return $p->charge_item_id === $itemId
                        && $p->effective_from->lte(Carbon::parse($date))
                        && ($p->effective_until === null || $p->effective_until->gte(Carbon::parse($date)));
                });
                if ($match) {
                    $map[$itemId][$date] = $match->price;
                }
            }
        }

        return $map;
    }

    /**
     * 既存レコードのキーマップ（同一日・同一品目の重複防止用）
     */
    protected function existingKeyMap(array $records): array
    {
        $residentIds = array_column($records, 'resident_id');
        $itemIds = array_column($records, 'charge_item_id');
        $dates = array_column($records, 'date');

        return DailyCharge::query()
            ->whereIn('resident_id', $residentIds)
            ->whereIn('charge_item_id', $itemIds)
            ->whereIn('date', $dates)
            ->get(['resident_id', 'charge_item_id', 'date'])
            ->keyBy(fn ($row) => $row->resident_id.'|'.$row->charge_item_id.'|'.$row->date->toDateString())
            ->map(fn () => true)
            ->all();
    }
}
