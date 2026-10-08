<?php

namespace App\Services\Invoice;

use App\Models\Resident;
use Carbon\Carbon;

class ProrationCalculator
{
    /**
     * 指定月の在籍日数を計算
     */
    public function calculateLivingDays(Resident $resident, int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        $moveIn = $resident->move_in_date ? Carbon::parse($resident->move_in_date) : null;
        $moveOut = $resident->move_out_date ? Carbon::parse($resident->move_out_date) : null;

        // 期間外の場合は0日
        if ($moveIn && $moveIn > $end) {
            return 0;
        }
        if ($moveOut && $moveOut < $start) {
            return 0;
        }

        $effectiveStart = $moveIn ? max($start, $moveIn) : $start;
        $effectiveEnd = $moveOut ? min($end, $moveOut) : $end;

        return $effectiveStart->diffInDays($effectiveEnd) + 1; // 両端を含む
    }

    /**
     * 日割り計算を行う
     */
    public function calculateProratedAmount(int $monthlyAmount, int $year, int $month, int $livingDays): int
    {
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        if ($daysInMonth === 0 || $livingDays === 0) {
            return 0;
        }

        return (int) round(($monthlyAmount / $daysInMonth) * $livingDays);
    }

    /**
     * 家賃・管理費の日割り計算を一括実行
     *
     * @return array{rent: int, management_fee: int}
     */
    public function calculateFixedCosts(Resident $resident, int $year, int $month): array
    {
        $livingDays = $this->calculateLivingDays($resident, $year, $month);
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        if ($daysInMonth === 0 || $livingDays === 0) {
            return ['rent' => 0, 'management_fee' => 0];
        }

        $rent = $this->calculateProratedAmount($resident->base_rent, $year, $month, $livingDays);
        $managementFee = $this->calculateProratedAmount($resident->base_management_fee, $year, $month, $livingDays);

        return ['rent' => $rent, 'management_fee' => $managementFee];
    }
}