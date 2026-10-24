<?php

namespace App\Services;

use App\Enums\ResidentStatus;
use App\Enums\ServiceInvoiceStatus;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\ServiceInvoice;
use Carbon\Carbon;

/**
 * Step 17-18: 月次締め整合性・異常値検知エンジン
 *
 * チェックルール:
 *   1. 当月在籍しているのに請求額が0円の入居者
 *   2. 入退去日と請求対象期間の不整合
 *   3. 未確定（下書き）状態の介護サービス請求
 *   4. 自費利用記録が0件の入居者（長期不在理由の未記載）
 *   5. 前月比で請求金額が±30%以上乖離している異常データ
 */
class BillingAnomalyDetector
{
    /** 前月比乖離のしきい値（30%） */
    public const MONTH_OVER_MONTH_THRESHOLD = 0.30;

    /**
     * 異常検知を実行
     *
     * @param  string  $yearMonth  'YYYY-MM'
     * @return array{errors: list<array>, warnings: list<array>, error_count: int, warning_count: int, checked_at: string}
     */
    public function detect(string $yearMonth, ?int $facilityId = null): array
    {
        $start = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $end = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth();

        $residents = Resident::query()
            ->when($facilityId, fn ($q) => $q->where('facility_id', $facilityId))
            ->where(function ($q) use ($start) {
                $q->whereNull('move_out_date')->orWhere('move_out_date', '>=', $start);
            })
            ->where(function ($q) use ($end) {
                $q->whereNull('move_in_date')->orWhere('move_in_date', '<=', $end);
            })
            ->where('status', ResidentStatus::Active)
            ->get();

        $invoices = MonthlyInvoice::query()
            ->forYearMonth($yearMonth)
            ->when($facilityId, fn ($q) => $q->forFacility($facilityId))
            ->get()
            ->keyBy('resident_id');

        $prevInvoices = MonthlyInvoice::query()
            ->forYearMonth(Carbon::createFromFormat('Y-m', $yearMonth)->subMonth()->format('Y-m'))
            ->when($facilityId, fn ($q) => $q->forFacility($facilityId))
            ->get()
            ->keyBy('resident_id');

        $errors = [];
        $warnings = [];

        foreach ($residents as $resident) {
            $invoice = $invoices->get($resident->id);

            // Rule 1: 在籍しているのに請求額が0円（Error）
            if (! $invoice || (int) $invoice->total_with_tax === 0) {
                $errors[] = [
                    'rule' => 'zero_amount',
                    'rule_label' => '請求額0円',
                    'severity' => 'error',
                    'resident_id' => $resident->id,
                    'resident_name' => $resident->name,
                    'room_number' => $resident->room_number,
                    'message' => "{$resident->name}（{$resident->room_number}）は当月在籍中ですが請求額が0円です。",
                ];
            }

            // Rule 2: 入退去日と請求対象期間の不整合（Error）
            if ($invoice) {
                $moveIn = $resident->move_in_date ? Carbon::parse($resident->move_in_date) : null;
                $moveOut = $resident->move_out_date ? Carbon::parse($resident->move_out_date) : null;

                $shouldProrate = ($moveIn && $moveIn->gt($start)) || ($moveOut && $moveOut->lt($end));
                $isFullMonth = (int) $invoice->rent_subtotal >= (int) $resident->base_rent;

                if ($shouldProrate && $isFullMonth) {
                    $errors[] = [
                        'rule' => 'period_mismatch',
                        'rule_label' => '入退去日不整合',
                        'severity' => 'error',
                        'resident_id' => $resident->id,
                        'resident_name' => $resident->name,
                        'room_number' => $resident->room_number,
                        'message' => "{$resident->name}（{$resident->room_number}）は月中入退去ですが満額請求になっています（入居日: ".
                            ($moveIn?->format('Y/m/d') ?? '未設定').' / 退去日: '.($moveOut?->format('Y/m/d') ?? '未設定').'）。',
                    ];
                }
            }

            // Rule 4: 自費利用記録が0件かつ長期不在の記載なし（Warning）
            $dailyChargeCount = DailyCharge::where('resident_id', $resident->id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->count();

            if ($dailyChargeCount === 0 && ($resident->move_out_date === null || Carbon::parse($resident->move_out_date)->gt($end))) {
                $warnings[] = [
                    'rule' => 'no_daily_charges',
                    'rule_label' => '自費記録0件',
                    'severity' => 'warning',
                    'resident_id' => $resident->id,
                    'resident_name' => $resident->name,
                    'room_number' => $resident->room_number,
                    'message' => "{$resident->name}（{$resident->room_number}）は当月の自費利用記録が0件です（長期不在等の理由確認が必要）。",
                ];
            }

            // Rule 5: 前月比±30%以上の乖離（Warning）
            $prev = $prevInvoices->get($resident->id);
            if ($invoice && $prev && (int) $prev->total_with_tax > 0) {
                $diff = abs((int) $invoice->total_with_tax - (int) $prev->total_with_tax)
                    / (int) $prev->total_with_tax;
                if ($diff >= self::MONTH_OVER_MONTH_THRESHOLD) {
                    $direction = (int) $invoice->total_with_tax > (int) $prev->total_with_tax ? '増加' : '減少';
                    $warnings[] = [
                        'rule' => 'month_over_month_deviation',
                        'rule_label' => '前月比乖離',
                        'severity' => 'warning',
                        'resident_id' => $resident->id,
                        'resident_name' => $resident->name,
                        'room_number' => $resident->room_number,
                        'message' => "{$resident->name}（{$resident->room_number}）の請求額が前月比で".
                            round($diff * 100)."%{$direction}しています（前月: ".
                            number_format($prev->total_with_tax).'円 → 当月: '.number_format($invoice->total_with_tax).'円）。',
                    ];
                }
            }
        }

        // Rule 3: 未確定（下書き）状態の介護サービス請求（ServiceInvoice）（Warning）
        $draftServiceInvoices = ServiceInvoice::query()
            ->whereIn('resident_id', $residents->pluck('id'))
            ->where('billing_year_month', $yearMonth)
            ->where('status', ServiceInvoiceStatus::Draft->value)
            ->get();

        foreach ($draftServiceInvoices as $serviceInvoice) {
            $resident = $residents->firstWhere('id', $serviceInvoice->resident_id);
            $name = $resident?->name ?? "入居者ID {$serviceInvoice->resident_id}";
            $warnings[] = [
                'rule' => 'draft_care_invoice',
                'rule_label' => '未確定請求',
                'severity' => 'warning',
                'resident_id' => $serviceInvoice->resident_id,
                'resident_name' => $name,
                'room_number' => $resident?->room_number,
                'message' => "{$name}の介護サービス請求が未確定（下書き）状態です。確定処理を行ってください。",
            ];
        }

        return [
            'errors' => array_values($errors),
            'warnings' => array_values($warnings),
            'error_count' => count($errors),
            'warning_count' => count($warnings),
            'checked_at' => now()->toISOString(),
        ];
    }

    /**
     * 締め処理進行可否（Errorが1件でもあれば不可）
     */
    public function canProceedToClosing(array $result): bool
    {
        return ($result['error_count'] ?? 1) === 0;
    }

    /**
     * 検知結果を件数サマリ文字列へ
     */
    public function summarize(array $result): string
    {
        if ($result['error_count'] === 0 && $result['warning_count'] === 0) {
            return '異常は検出されませんでした。';
        }

        $parts = [];
        if ($result['error_count'] > 0) {
            $parts[] = "Error {$result['error_count']}件";
        }
        if ($result['warning_count'] > 0) {
            $parts[] = "Warning {$result['warning_count']}件";
        }

        return implode(' / ', $parts).'が検出されました。';
    }
}
