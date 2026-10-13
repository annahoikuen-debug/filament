<?php

namespace App\Services\Banking;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\DirectDebitResult;
use App\Models\MonthlyInvoice;
use App\Models\ResidentBankAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Step 5: 自動リコンサイル（消込・ステータス更新）トランザクション処理
 */
class ZenginReconcileService
{
    public function __construct(
        protected ZenginResultParser $parser,
    ) {
    }

    /**
     * 結果ファイルを取込し、請求ステータスを自動更新する
     *
     * @param  string  $content  全銀結果ファイルの内容
     * @param  string  $yearMonth  対象請求年月 (Y-m)
     * @param  ?string  $paidAt  入金日 (Y-m-d)
     * @return array{success_count: int, failed_count: int, success_amount: int, failed_amount: int, details: array}
     */
    public function reconcile(string $content, string $yearMonth, ?string $paidAt = null): array
    {
        $parsed = $this->parser->parse($content);

        $successCount = 0;
        $failedCount = 0;
        $successAmount = 0;
        $failedAmount = 0;
        $details = ['success' => [], 'failed' => []];

        DB::transaction(function () use ($parsed, $yearMonth, $paidAt, &$successCount, &$failedCount, &$successAmount, &$failedAmount, &$details) {
            $allEntries = collect($parsed['success'])
                ->map(fn ($e) => $e + ['is_success' => true])
                ->merge(collect($parsed['failed'])->map(fn ($e) => $e + ['is_success' => false]));

            $residentIds = $allEntries->pluck('resident_id')->unique();
            $accounts = ResidentBankAccount::query()
                ->whereIn('resident_id', $residentIds)
                ->where('is_active', true)
                ->get()
                ->groupBy('resident_id')
                ->map(fn ($g) => $g->first());

            $invoices = MonthlyInvoice::query()
                ->forYearMonth($yearMonth)
                ->whereIn('resident_id', $residentIds)
                ->get()
                ->keyBy('resident_id');

            foreach ($allEntries as $entry) {
                $invoice = $invoices->get($entry['resident_id']);

                if (! $invoice) {
                    Log::warning('ZenginReconcile: 該当請求なし', ['resident_id' => $entry['resident_id'], 'year_month' => $yearMonth]);
                    continue;
                }

                $account = $accounts->get($entry['resident_id']);

                if ($entry['is_success']) {
                    // 振替成功: markAsPaid で消込（領収書番号は自動採番）
                    if ($invoice->status !== InvoiceStatus::Paid) {
                        $invoice->markAsPaid(PaymentMethod::DirectDebit, $paidAt);
                    }
                    $successCount++;
                    $successAmount += $entry['amount'];
                    $details['success'][] = ['resident_id' => $entry['resident_id'], 'amount' => $entry['amount']];
                } else {
                    // 振替不能: failed相当としてアラート記録
                    $invoice->update([
                        'status' => InvoiceStatus::Billed,
                    ]);
                    $failedCount++;
                    $failedAmount += $entry['amount'];
                    $details['failed'][] = [
                        'resident_id' => $entry['resident_id'],
                        'amount' => $entry['amount'],
                        'code' => $entry['code'],
                        'reason' => $entry['reason'],
                    ];

                    Log::warning('ZenginReconcile: 振替不能', [
                        'resident_id' => $entry['resident_id'],
                        'code' => $entry['code'],
                        'reason' => $entry['reason'],
                    ]);
                }

                DirectDebitResult::create([
                    'monthly_invoice_id' => $invoice->id,
                    'resident_bank_account_id' => $account?->id,
                    'billing_year_month' => $yearMonth,
                    'amount' => $entry['amount'],
                    'result_code' => $entry['is_success'] ? '0' : $entry['code'],
                    'reason' => $entry['reason'] ?? null,
                    'is_success' => $entry['is_success'],
                    'processed_at' => $paidAt ?? now()->toDateString(),
                ]);
            }
        });

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'success_amount' => $successAmount,
            'failed_amount' => $failedAmount,
            'details' => $details,
        ];
    }
}
