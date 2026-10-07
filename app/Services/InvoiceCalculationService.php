<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceCalculationService
{
    /**
     * 指定年月の請求データを一括生成・再計算する
     *
     * @param  string  $yearMonth  'YYYY-MM' 形式 (例: '2026-10')
     * @param  bool  $forceUpdate  確定済み（請求済・入金済）のデータも上書き再計算するか
     * @return array{created: int, updated: int, skipped: int, conflicts: int, total_residents: int}
     */
    public function generateForMonth(string $yearMonth, bool $forceUpdate = false): array
    {
        Log::info('Invoice generation started', [
            'year_month' => $yearMonth,
            'force_update' => $forceUpdate,
        ]);

        // DBには 'Y-m-d H:i:s' 形式で保存されるため、日時境界で比較する
        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth()->format('Y-m-d H:i:s');
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth()->endOfDay()->format('Y-m-d H:i:s');

        // 1. 対象月に在籍していたResidentを取得（月中入居・月中退去を含む）
        $residents = Resident::where(function ($query) use ($startDate) {
            $query->whereNull('move_out_date')
                ->orWhere('move_out_date', '>=', $startDate);
        })
            ->where(function ($query) use ($endDate) {
                $query->whereNull('move_in_date')
                    ->orWhere('move_in_date', '<=', $endDate);
            })
            ->where('status', ResidentStatus::Active)
            ->orderBy('room_number')
            ->get();

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'conflicts' => 0,
            'total_residents' => $residents->count(),
        ];

        DB::transaction(function () use ($residents, $yearMonth, $startDate, $endDate, $forceUpdate, &$stats) {
            foreach ($residents as $resident) {
                // 2. 各Residentのその月の daily_charges を合計する
                $serviceSubtotal = (int) (DailyCharge::where('resident_id', $resident->id)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->selectRaw('SUM(unit_price * quantity) as total')
                    ->value('total') ?? 0);

                // 3. 固定費（家賃＋管理費）の按分計算
                $year = (int) substr($yearMonth, 0, 4);
                $month = (int) substr($yearMonth, 5, 2);

                $rentSubtotal = $resident->getProratedAmount($year, $month, $resident->base_rent);
                $managementSubtotal = $resident->getProratedAmount($year, $month, $resident->base_management_fee);
                $totalAmount = $rentSubtotal + $managementSubtotal + $serviceSubtotal;

                // 既存の請求レコードを確認（悲観ロックで取得）
                $invoice = MonthlyInvoice::where('resident_id', $resident->id)
                    ->where('billing_year_month', $yearMonth)
                    ->lockForUpdate()
                    ->first();

                if (! $invoice) {
                    // 新規作成
                    MonthlyInvoice::create([
                        'billing_year_month' => $yearMonth,
                        'resident_id' => $resident->id,
                        'rent_subtotal' => $rentSubtotal,
                        'management_fee_subtotal' => $managementSubtotal,
                        'service_subtotal' => $serviceSubtotal,
                        'total_amount' => $totalAmount,
                        'status' => InvoiceStatus::Unbilled,
                        'version' => 0,
                    ]);
                    $stats['created']++;
                } else {
                    // 既に請求済・入金済の場合は通常保護（強制フラグがない限りスキップ）
                    if ($invoice->status !== InvoiceStatus::Unbilled && ! $forceUpdate) {
                        $stats['skipped']++;

                        continue;
                    }

                    // バージョンを保存してから更新を試みる
                    $currentVersion = $invoice->version ?? 0;

                    $updated = $invoice->where('version', $currentVersion)
                        ->update([
                            'rent_subtotal' => $rentSubtotal,
                            'management_fee_subtotal' => $managementSubtotal,
                            'service_subtotal' => $serviceSubtotal,
                            'total_amount' => $totalAmount,
                            'version' => $currentVersion + 1,
                        ]);

                    if ($updated) {
                        $stats['updated']++;
                    } else {
                        // バージョン競合: 他のプロセスが更新済み
                        $stats['conflicts']++;
                        $stats['skipped']++; // 競合時はスキップ扱い
                        Log::warning('Invoice version conflict', [
                            'resident_id' => $resident->id,
                            'year_month' => $yearMonth,
                            'expected_version' => $currentVersion,
                        ]);
                    }
                }
            }
        });

        Log::info('Invoice generation completed', $stats);

        return $stats;
    }
}
