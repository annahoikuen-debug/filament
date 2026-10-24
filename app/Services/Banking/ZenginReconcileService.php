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
 *
 * 安全性のためのガード:
 * - 金額照合: 結果ファイルの引落金額が請求額（税込）と一致しない場合は消込しない
 * - 冪等性: 同一ファイルの再取込で二重消込・二重記録をしない
 */
class ZenginReconcileService
{
    public function __construct(
        protected ZenginResultParser $parser,
    ) {}

    /**
     * 結果ファイルを取込し、請求ステータスを自動更新する
     *
     * @param  string  $content  全銀結果ファイルの内容
     * @param  string  $yearMonth  対象請求年月 (Y-m)
     * @param  ?string  $paidAt  入金日 (Y-m-d)
     * @return array{success_count: int, failed_count: int, mismatch_count: int, skipped_count: int, success_amount: int, failed_amount: int, mismatch_amount: int, details: array}
     */
    public function reconcile(string $content, string $yearMonth, ?string $paidAt = null): array
    {
        $parsed = $this->parser->parse($content);

        $successCount = 0;
        $failedCount = 0;
        $mismatchCount = 0;
        $skippedCount = 0;
        $successAmount = 0;
        $failedAmount = 0;
        $mismatchAmount = 0;
        $details = ['success' => [], 'failed' => [], 'mismatch' => [], 'skipped' => []];

        DB::transaction(function () use ($parsed, $yearMonth, $paidAt, &$successCount, &$failedCount, &$mismatchCount, &$skippedCount, &$successAmount, &$failedAmount, &$mismatchAmount, &$details) {
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

            // 同一ファイル内の重複エントリ防止（同一請求書は1回のみ処理）
            $processedInvoiceIds = [];

            foreach ($allEntries as $entry) {
                $invoice = $invoices->get($entry['resident_id']);

                if (! $invoice) {
                    Log::warning('ZenginReconcile: 該当請求なし', ['resident_id' => $entry['resident_id'], 'year_month' => $yearMonth]);

                    continue;
                }

                if (isset($processedInvoiceIds[$invoice->id])) {
                    $skippedCount++;
                    $details['skipped'][] = [
                        'resident_id' => $entry['resident_id'],
                        'amount' => $entry['amount'],
                        'reason' => '同一ファイル内の重複エントリ',
                    ];

                    continue;
                }
                $processedInvoiceIds[$invoice->id] = true;

                $account = $accounts->get($entry['resident_id']);

                if ($entry['is_success']) {
                    // 冪等性: 振替成功済みの請求書は再取込時にスキップ（二重消込防止）
                    $alreadyPaid = DirectDebitResult::query()
                        ->where('monthly_invoice_id', $invoice->id)
                        ->where('billing_year_month', $yearMonth)
                        ->where('is_success', true)
                        ->exists();

                    if ($alreadyPaid) {
                        $skippedCount++;
                        $details['skipped'][] = [
                            'resident_id' => $entry['resident_id'],
                            'amount' => $entry['amount'],
                            'reason' => '振替成功済み（再取込による重複をスキップ）',
                        ];

                        continue;
                    }

                    // 金額照合: 依頼ファイルの引落額（= total_with_tax）と一致しない場合は消込しない
                    $expectedAmount = (int) $invoice->total_with_tax;
                    if ((int) $entry['amount'] !== $expectedAmount) {
                        $mismatchCount++;
                        $mismatchAmount += (int) $entry['amount'];
                        $details['mismatch'][] = [
                            'resident_id' => $entry['resident_id'],
                            'expected_amount' => $expectedAmount,
                            'file_amount' => (int) $entry['amount'],
                        ];

                        Log::error('ZenginReconcile: 金額不一致のため消込せず（人手確認が必要です）', [
                            'resident_id' => $entry['resident_id'],
                            'monthly_invoice_id' => $invoice->id,
                            'year_month' => $yearMonth,
                            'expected_amount' => $expectedAmount,
                            'file_amount' => (int) $entry['amount'],
                        ]);

                        continue;
                    }

                    // 振替成功: markAsPaid で消込（領収書番号は自動採番）
                    if ($invoice->status !== InvoiceStatus::Paid) {
                        $invoice->markAsPaid(PaymentMethod::DirectDebit, $paidAt);
                    }
                    $successCount++;
                    $successAmount += (int) $entry['amount'];
                    $details['success'][] = ['resident_id' => $entry['resident_id'], 'amount' => (int) $entry['amount']];

                    DirectDebitResult::create([
                        'monthly_invoice_id' => $invoice->id,
                        'resident_bank_account_id' => $account?->id,
                        'billing_year_month' => $yearMonth,
                        'amount' => $entry['amount'],
                        'result_code' => '0',
                        'reason' => null,
                        'is_success' => true,
                        'processed_at' => $paidAt ?? now()->toDateString(),
                    ]);
                } else {
                    // 振替不能: 同一エントリの再取込を重複記録しない
                    $alreadyFailed = DirectDebitResult::query()
                        ->where('monthly_invoice_id', $invoice->id)
                        ->where('billing_year_month', $yearMonth)
                        ->where('is_success', false)
                        ->where('result_code', $entry['code'])
                        ->exists();

                    if ($alreadyFailed) {
                        $skippedCount++;
                        $details['skipped'][] = [
                            'resident_id' => $entry['resident_id'],
                            'amount' => $entry['amount'],
                            'reason' => '同一の振替不能結果を記録済み（再取込による重複をスキップ）',
                        ];

                        continue;
                    }

                    // 振替不能: failed相当としてアラート記録
                    $invoice->update([
                        'status' => InvoiceStatus::Billed,
                    ]);
                    $failedCount++;
                    $failedAmount += (int) $entry['amount'];
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

                    DirectDebitResult::create([
                        'monthly_invoice_id' => $invoice->id,
                        'resident_bank_account_id' => $account?->id,
                        'billing_year_month' => $yearMonth,
                        'amount' => $entry['amount'],
                        'result_code' => $entry['code'],
                        'reason' => $entry['reason'] ?? null,
                        'is_success' => false,
                        'processed_at' => $paidAt ?? now()->toDateString(),
                    ]);
                }
            }
        });

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'mismatch_count' => $mismatchCount,
            'skipped_count' => $skippedCount,
            'success_amount' => $successAmount,
            'failed_amount' => $failedAmount,
            'mismatch_amount' => $mismatchAmount,
            'details' => $details,
        ];
    }
}
