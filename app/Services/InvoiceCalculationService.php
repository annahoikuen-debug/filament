<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\TaxSetting;
use App\Services\Invoice\DailyChargeAggregator;
use App\Services\Invoice\InvoicePersister;
use App\Services\Invoice\ProrationCalculator;
use App\Services\Invoice\RecurringChargeAggregator;
use App\Services\Invoice\TaxCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceCalculationService
{
    private const CHUNK_SIZE = 100;

    public function __construct(
        private DailyChargeAggregator $dailyChargeAggregator,
        private RecurringChargeAggregator $recurringChargeAggregator,
        private ProrationCalculator $prorationCalculator,
        private TaxCalculator $taxCalculator,
        private InvoicePersister $invoicePersister,
    ) {}

    /**
     * 単一請求書の計算・更新（GenerateMonthlyInvoicesコマンド用）
     *
     * @param MonthlyInvoice $invoice 計算対象の請求書インスタンス
     * @return void
     */
    public function calculate(MonthlyInvoice $invoice): void
    {
        $yearMonth = $invoice->billing_year_month;
        $resident = $invoice->resident;

        // 請求月に対応する税率を取得（履歴管理対応）
        $billingDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $rates = TaxCalculator::getRatesForDate($billingDate);
        $standardRate = $rates['standard'];
        $reducedRate = $rates['reduced'];

        // 期間境界
        $startDate = $billingDate->copy()->startOfMonth()->format('Y-m-d H:i:s');
        $endDate = $billingDate->copy()->endOfMonth()->endOfDay()->format('Y-m-d H:i:s');

        // 日々課金集計
        $dailyAggregates = $this->dailyChargeAggregator->aggregate([$resident->id], $startDate, $endDate);
        $residentDailyAggregates = $dailyAggregates[$resident->id] ?? [
            'standard' => 0,
            'reduced' => 0,
            'non_taxable' => 0,
        ];

        $serviceStandardTaxable = (int) ($residentDailyAggregates['standard'] ?? 0);
        $serviceReducedTaxable = (int) ($residentDailyAggregates['reduced'] ?? 0);
        $serviceNonTaxable = (int) ($residentDailyAggregates['non_taxable'] ?? 0);

        // 定期課金集計
        $recurringAggregates = $this->recurringChargeAggregator->aggregate([$resident->id], $yearMonth, $billingDate);
        $residentRecurringAggregates = $recurringAggregates[$resident->id] ?? [
            'standard' => 0,
            'reduced' => 0,
            'non_taxable' => 0,
        ];

        $serviceStandardTaxable += (int) ($residentRecurringAggregates['standard'] ?? 0);
        $serviceReducedTaxable += (int) ($residentRecurringAggregates['reduced'] ?? 0);
        $serviceNonTaxable += (int) ($residentRecurringAggregates['non_taxable'] ?? 0);

        $serviceSubtotal = $serviceStandardTaxable + $serviceReducedTaxable + $serviceNonTaxable;

        // 固定費（家賃＋管理費）の按分計算
        $year = (int) substr($yearMonth, 0, 4);
        $month = (int) substr($yearMonth, 5, 2);

        $fixedCosts = $this->prorationCalculator->calculateFixedCosts($resident, $year, $month);
        $rentSubtotal = $fixedCosts['rent'];
        $managementSubtotal = $fixedCosts['management_fee'];

        // 家賃は非課税、管理費は標準税率課税とする（仕様）
        $rentNonTaxable = $rentSubtotal;
        $managementStandardTaxable = $managementSubtotal;

        // 税額計算
        $standardTaxableTotal = $managementStandardTaxable + $serviceStandardTaxable;
        $reducedTaxableTotal = $serviceReducedTaxable;
        $nonTaxableTotal = $rentNonTaxable + $serviceNonTaxable;

        $taxResult = $this->taxCalculator->calculate(
            $standardTaxableTotal,
            $reducedTaxableTotal,
            $nonTaxableTotal,
            $standardRate,
            $reducedRate
        );

        $totalAmount = $rentSubtotal + $managementSubtotal + $serviceSubtotal;
        $taxableAmount = $standardTaxableTotal + $reducedTaxableTotal;

        // 請求書インスタンスに計算結果をセット（保存は呼び出し元で行う）
        $invoice->rent_subtotal = $rentSubtotal;
        $invoice->management_fee_subtotal = $managementSubtotal;
        $invoice->service_subtotal = $serviceSubtotal;
        $invoice->total_amount = $totalAmount;
        $invoice->taxable_amount = $taxableAmount;
        $invoice->tax_amount = $taxResult['total_tax'];
        $invoice->tax_rate = $standardRate;
        $invoice->tax_breakdown = $taxResult['tax_breakdown'];
    }

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
        $rates = TaxCalculator::getRatesForDate($billingDate);
        $standardRate = $rates['standard'];
        $reducedRate = $rates['reduced'];

        // 1. 対象月に在籍していたResidentを取得（月中入居・月中退去を含む）
        $baseQuery = Resident::where(function ($query) use ($startDate) {
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
            $baseQuery->where('facility_id', $facilityId);
        }

        // 総件数を取得（統計用）
        $totalResidents = $baseQuery->count();

        // 2. 全対象者のIDを取得（軽量）
        $allResidentIds = $baseQuery->pluck('id')->toArray();

        // 3. 日々課金の税区分別集計を1クエリで取得（全対象者分）
        $dailyAggregates = $this->dailyChargeAggregator->aggregate($allResidentIds, $startDate, $endDate);

        // 4. 定期課金を一括取得・集計（全対象者分）
        $recurringAggregates = $this->recurringChargeAggregator->aggregate($allResidentIds, $yearMonth, $billingDate);

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'conflicts' => 0,
            'total_residents' => $totalResidents,
        ];

        // 5. チャンク処理でメモリ効率化（トランザクションはチャンク単位）
        $baseQuery->select('id', 'facility_id', 'room_number', 'name', 'base_rent', 'base_management_fee', 'move_in_date', 'move_out_date')
            ->chunkById(self::CHUNK_SIZE, function ($residents) use (
                $yearMonth,
                $startDate,
                $endDate,
                $forceUpdate,
                &$stats,
                $standardRate,
                $reducedRate,
                $dailyAggregates,
                $recurringAggregates,
                $billingDate
            ) {
                DB::transaction(function () use ($residents, $yearMonth, $startDate, $endDate, $forceUpdate, &$stats, $standardRate, $reducedRate, $dailyAggregates, $recurringAggregates, $billingDate) {
                    foreach ($residents as $resident) {
                        $this->processResidentInvoice(
                            $resident,
                            $yearMonth,
                            $forceUpdate,
                            $stats,
                            $standardRate,
                            $reducedRate,
                            $dailyAggregates,
                            $recurringAggregates,
                            $billingDate
                        );
                    }
                });

                return true;
            });

        Log::info('Invoice generation completed', $stats);

        return $stats;
    }

    /**
     * 単一入居者の請求処理
     */
    private function processResidentInvoice(
        Resident $resident,
        string $yearMonth,
        bool $forceUpdate,
        array &$stats,
        float $standardRate,
        float $reducedRate,
        array $dailyAggregates,
        array $recurringAggregates,
        Carbon $billingDate
    ): void {
        // 日々課金集計を取得
        $residentDailyAggregates = $dailyAggregates[$resident->id] ?? [
            'standard' => 0,
            'reduced' => 0,
            'non_taxable' => 0,
        ];

        $serviceStandardTaxable = (int) ($residentDailyAggregates['standard'] ?? 0);
        $serviceReducedTaxable = (int) ($residentDailyAggregates['reduced'] ?? 0);
        $serviceNonTaxable = (int) ($residentDailyAggregates['non_taxable'] ?? 0);

        // 定期課金集計を加算
        $residentRecurringAggregates = $recurringAggregates[$resident->id] ?? [
            'standard' => 0,
            'reduced' => 0,
            'non_taxable' => 0,
        ];

        $serviceStandardTaxable += (int) ($residentRecurringAggregates['standard'] ?? 0);
        $serviceReducedTaxable += (int) ($residentRecurringAggregates['reduced'] ?? 0);
        $serviceNonTaxable += (int) ($residentRecurringAggregates['non_taxable'] ?? 0);

        $serviceSubtotal = $serviceStandardTaxable + $serviceReducedTaxable + $serviceNonTaxable;

        // 固定費（家賃＋管理費）の按分計算
        $year = (int) substr($yearMonth, 0, 4);
        $month = (int) substr($yearMonth, 5, 2);

        $fixedCosts = $this->prorationCalculator->calculateFixedCosts($resident, $year, $month);
        $rentSubtotal = $fixedCosts['rent'];
        $managementSubtotal = $fixedCosts['management_fee'];

        // 家賃は非課税、管理費は標準税率課税とする（仕様）
        $rentNonTaxable = $rentSubtotal;
        $managementStandardTaxable = $managementSubtotal;

        // 税額計算
        $standardTaxableTotal = $managementStandardTaxable + $serviceStandardTaxable;
        $reducedTaxableTotal = $serviceReducedTaxable;
        $nonTaxableTotal = $rentNonTaxable + $serviceNonTaxable;

        $taxResult = $this->taxCalculator->calculate(
            $standardTaxableTotal,
            $reducedTaxableTotal,
            $nonTaxableTotal,
            $standardRate,
            $reducedRate
        );

        $totalAmount = $rentSubtotal + $managementSubtotal + $serviceSubtotal;
        $taxableAmount = $standardTaxableTotal + $reducedTaxableTotal;

        // 請求書を永続化
        $persistStats = $this->invoicePersister->persist([
            'resident' => $resident,
            'year_month' => $yearMonth,
            'rent_subtotal' => $rentSubtotal,
            'management_subtotal' => $managementSubtotal,
            'service_subtotal' => $serviceSubtotal,
            'total_amount' => $totalAmount,
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $taxResult['total_tax'],
            'tax_rate' => $standardRate,
            'tax_breakdown' => $taxResult['tax_breakdown'],
            'force_update' => $forceUpdate,
        ]);

        $stats['created'] += $persistStats['created'];
        $stats['updated'] += $persistStats['updated'];
        $stats['skipped'] += $persistStats['skipped'];
        $stats['conflicts'] += $persistStats['conflicts'];
    }
}