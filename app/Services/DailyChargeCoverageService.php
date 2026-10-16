<?php

namespace App\Services;

use App\Models\DailyCharge;
use App\Models\Resident;
use Carbon\Carbon;

class DailyChargeCoverageService
{
    /**
     * 月間の各入居者×日次の自費記録入力マトリクスを取得
     *
     * @return array{
     *     daysInMonth: int,
     *     matrix: array<int, array{
     *         resident: Resident,
     *         days: array<int, bool>,
     *         totalCount: int,
     *         missingDaysCount: int
     *     }>
     * }
     */
    public function getMonthlyCoverage(string $yearMonth, ?int $facilityId = null): array
    {
        [$year, $month] = explode('-', $yearMonth);
        $daysInMonth = Carbon::create((int) $year, (int) $month)->daysInMonth;

        $residentQuery = Resident::query()
            ->where('status', '!=', 'moved_out')
            ->orderBy('room_number');

        if ($facilityId) {
            $residentQuery->where('facility_id', $facilityId);
        }

        $residents = $residentQuery->get();

        $charges = DailyCharge::query()
            ->whereYear('date', (int) $year)
            ->whereMonth('date', (int) $month)
            ->whereIn('resident_id', $residents->pluck('id'))
            ->select('resident_id', 'date')
            ->get();

        $chargeMap = [];
        foreach ($charges as $c) {
            $day = (int) Carbon::parse($c->date)->day;
            $chargeMap[$c->resident_id][$day] = true;
        }

        $matrix = [];
        foreach ($residents as $r) {
            $days = [];
            $recordedCount = 0;
            $missingDays = 0;

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $hasRecord = ! empty($chargeMap[$r->id][$d]);
                $days[$d] = $hasRecord;
                if ($hasRecord) {
                    $recordedCount++;
                } else {
                    $missingDays++;
                }
            }

            $matrix[] = [
                'resident' => $r,
                'days' => $days,
                'totalCount' => $recordedCount,
                'missingDaysCount' => $missingDays,
            ];
        }

        return [
            'daysInMonth' => $daysInMonth,
            'matrix' => $matrix,
        ];
    }
}
