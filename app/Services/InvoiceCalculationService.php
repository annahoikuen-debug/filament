<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Enums\TaxType;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\RecurringCharge;
use App\Models\Resident;
use App\Models\TaxSetting;
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
     * @param  int|null  $facilityId  施設IDで絞り込み（nullの場合は全施設）
     * @return array{created: int, updated: int, skipped: int, conflicts: int, total_residents: int}
     */
    public function generateForMonth(string $yearMonth, bool $forceUpdate = false, ?int $facilityId = null): array
    {
        Log::info('Invoice generation started', [
            'year_month' => $yearMonth,
            'force_update' => $forceUpdate,
            'facility_id' => $facilityId,
        ]);

        // DBには 'Y-m-d H:i:s' 形式で保存されるため、日時境界で比較する
        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth()->format('Y-m-d H:i:s');
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth()->endOfDay()->format('Y-m-d H:i:s');

        // 請求月に対応する税率を取得（履歴管理対応）
        $billingDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $standardRate = TaxSetting::getRateForDate($billingDate);
        $reducedRate = TaxSetting::currentReducedRate(); // 軽減税率は現行設定から取得

        // 1. 対象月に在籍していたResidentを取得（月中入居・月中退去を含む）
        $query = Resident::where(function ($query) use ($startDate) {
            $query->whereNull('move_out_date')
                ->orWhere('move_out_date', '>=', $startDate);
        })
            ->where(function ($query) use ($endDate) {
                $query->whereNull('move_in_date')
                    ->orWhere('move_in_date', '<=', $endDate);
            })
            ->where('status', ResidentStatus::Active)
            ->orderBy('room_number');

        if ($facilityId) {
            $query->where('facility_id', $facilityId);
        }

        $residents = $query->get();

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'conflicts' => 0,
            'total_residents' => $residents->count(),
        ];

        DB::transaction(function () use ($residents, $yearMonth, $startDate, $endDate, $forceUpdate, &$stats, $standardRate, $reducedRate) {
            foreach ($residents as $resident) {
                // 2. 各Residentのその月の daily_charges を品目ごとに税区分別に合計する
                $dailyCharges = DailyCharge::where('resident_id', $resident->id)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->with('chargeItem')
                    ->get();

                $serviceStandardTaxable = 0;  // 標準税率対象の自費小計
                $serviceReducedTaxable = 0;   // 軽減税率対象の自費小計
                $serviceNonTaxable = 0;       // 非課税の自費小計

                foreach ($dailyCharges as $charge) {
                    $subtotal = $charge->unit_price * $charge->quantity;
                    $taxType = $charge->chargeItem?->tax_type ?? TaxType::Standard;

                    match ($taxType) {
                        TaxType::NonTaxable => $serviceNonTaxable += $subtotal,
                        TaxType::Standard => $serviceStandardTaxable += $subtotal,
                        TaxType::Reduced => $serviceReducedTaxable += $subtotal,
                    };
                }

                // 3. 定期課金を展開して自費に加算
                $recurringCharges = RecurringCharge::where('resident_id', $resident->id)
                    ->forYearMonth($yearMonth)
                    ->with('chargeItem')
                    ->get();

                foreach ($recurringCharges as $recurring) {
                    $chargeItem = $recurring->chargeItem;
                    if (! $chargeItem) {
                        continue;
                    }

                    // 適用日数を計算（月額の場合は満月、日額の場合は在籍日数）
                    $applicableDays = $recurring->getApplicableDays($yearMonth, $resident->move_in_date, $resident->move_out_date);
                    if ($applicableDays === 0) {
                        continue;
                    }

                    // 単価を取得（請求月の価格履歴から）
                    $unitPrice = $chargeItem->getPriceForDate($billingDate) ?? 0;
                    if ($unitPrice === 0) {
                        continue;
                    }

                    // 小計計算
                    $quantity = $recurring->quantity;
                    if ($recurring->frequency === 'monthly') {
                        // 月額の場合：単価 × 数量
                        $subtotal = $unitPrice * $quantity;
                    } else {
                        // 日額の場合：単価 × 数量 × 適用日数
                        $subtotal = $unitPrice * $quantity * $applicableDays;
                    }

                    $taxType = $chargeItem->tax_type ?? TaxType::Standard;

                    match ($taxType) {
                        TaxType::NonTaxable => $serviceNonTaxable += $subtotal,
                        TaxType::Standard => $serviceStandardTaxable += $subtotal,
                        TaxType::Reduced => $serviceReducedTaxable += $subtotal,
                    };
                }

                $serviceSubtotal = $serviceStandardTaxable + $serviceReducedTaxable + $serviceNonTaxable;

                // 4. 固定費（家賃＋管理費）の按分計算
                $year = (int) substr($yearMonth, 0, 4);
                $month = (int) substr($yearMonth, 5, 2);

                $rentSubtotal = $resident->getProratedAmount($year, $month, $resident->base_rent);
                $managementSubtotal = $resident->getProratedAmount($year, $month, $resident->base_management_fee);

                // 家賃は非課税、管理費は標準税率課税とする（仕様）
                $rentNonTaxable = $rentSubtotal;
                $managementStandardTaxable = $managementSubtotal;

                // 5. 税額計算
                $standardTaxableTotal = $managementStandardTaxable + $serviceStandardTaxable;
                $reducedTaxableTotal = $serviceReducedTaxable;
                $nonTaxableTotal = $rentNonTaxable + $serviceNonTaxable;

                $standardTaxAmount = (int) round($standardTaxableTotal * ($standardRate / 100));
                $reducedTaxAmount = (int) round($reducedTaxableTotal * ($reducedRate / 100));
                $taxAmount = $standardTaxAmount + $reducedTaxAmount;

                $totalAmount = $rentSubtotal + $managementSubtotal + $serviceSubtotal;
                $taxableAmount = $standardTaxableTotal + $reducedTaxableTotal;

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
                        'facility_id' => $resident->facility_id,
                        'rent_subtotal' => $rentSubtotal,
                        'management_fee_subtotal' => $managementSubtotal,
                        'service_subtotal' => $serviceSubtotal,
                        'total_amount' => $totalAmount,
                        'taxable_amount' => $taxableAmount,
                        'tax_amount' => $taxAmount,
                        'tax_rate' => $standardRate, // 代表税率として標準税率を保存
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
                            'taxable_amount' => $taxableAmount,
                            'tax_amount' => $taxAmount,
                            'tax_rate' => $standardRate,
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