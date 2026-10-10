<?php

namespace App\Services\Invoice;

use App\Enums\InvoiceStatus;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoicePersister
{
    private const MAX_RETRIES = 3;

    /**
     * 請求書を作成または更新（楽観ロック対応・デッドロック耐性付き）
     *
     * @param  array{
     *     resident: Resident,
     *     year_month: string,
     *     rent_subtotal: int,
     *     management_subtotal: int,
     *     service_subtotal: int,
     *     total_amount: int,
     *     taxable_amount: int,
     *     tax_amount: int,
     *     tax_rate: float,
     *     tax_breakdown: array,
     *     force_update: bool,
     * }  $data
     * @return array{created: int, updated: int, skipped: int, conflicts: int, deadlocks: int}
     */
    public function persist(array $data): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'conflicts' => 0, 'deadlocks' => 0];

        $residentId = $data['resident']->id;
        $yearMonth = $data['year_month'];

        // リトライループ（デッドロック対策）
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                return $this->doPersist($data, $stats);
            } catch (QueryException $e) {
                if ($this->isDeadlock($e) && $attempt < self::MAX_RETRIES) {
                    $stats['deadlocks']++;
                    Log::warning('Invoice persist deadlock detected, retrying', [
                        'resident_id' => $residentId,
                        'year_month' => $yearMonth,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    // 少し待機してからリトライ
                    usleep(100_000 * $attempt); // 100ms, 200ms, 300ms...

                    continue;
                }
                throw $e;
            }
        }

        return $stats;
    }

    /**
     * 実際の永続化処理
     */
    private function doPersist(array $data, array &$stats): array
    {
        $residentId = $data['resident']->id;
        $yearMonth = $data['year_month'];

        // SELECT ... FOR UPDATE SKIP LOCKED でデッドロックリスクを軽減
        // 注: MySQL 8.0+ / PostgreSQL 9.5+ でサポート
        $invoice = MonthlyInvoice::where('resident_id', $residentId)
            ->where('billing_year_month', $yearMonth)
            ->when(
                DB::getDriverName() === 'mysql' || DB::getDriverName() === 'pgsql',
                fn ($q) => $q->lockForUpdate()->skipLocked(),
                fn ($q) => $q->lockForUpdate()
            )
            ->first();

        if (! $invoice) {
            // 新規作成（INSERT はデッドロックしにくいが、ユニーク制約違反の可能性あり）
            try {
                MonthlyInvoice::create([
                    'billing_year_month' => $yearMonth,
                    'resident_id' => $residentId,
                    'facility_id' => $data['resident']->facility_id,
                    'rent_subtotal' => $data['rent_subtotal'],
                    'management_fee_subtotal' => $data['management_subtotal'],
                    'service_subtotal' => $data['service_subtotal'],
                    'total_amount' => $data['total_amount'],
                    'taxable_amount' => $data['taxable_amount'],
                    'tax_amount' => $data['tax_amount'],
                    'tax_rate' => $data['tax_rate'],
                    'tax_breakdown' => $data['tax_breakdown'],
                    'status' => InvoiceStatus::Unbilled,
                    'version' => 0,
                ]);
                $stats['created']++;
            } catch (QueryException $e) {
                // ユニーク制約違反（同時作成）の場合は更新処理へフォールバック
                if ($this->isUniqueViolation($e)) {
                    return $this->doUpdateExisting($data, $stats);
                }
                throw $e;
            }
        } else {
            return $this->doUpdateExisting($data, $stats, $invoice);
        }

        return $stats;
    }

    /**
     * 既存レコードの更新処理
     */
    private function doUpdateExisting(array $data, array &$stats, ?MonthlyInvoice $invoice = null): array
    {
        $residentId = $data['resident']->id;
        $yearMonth = $data['year_month'];

        // インスタンスが渡されていない場合は再取得
        if (! $invoice) {
            $invoice = MonthlyInvoice::where('resident_id', $residentId)
                ->where('billing_year_month', $yearMonth)
                ->lockForUpdate()
                ->first();

            if (! $invoice) {
                // 競合で消えた？ → 作成扱い
                return $this->doPersist($data, $stats);
            }
        }

        // 既に請求済・入金済の場合は通常保護（強制フラグがない限りスキップ）
        if ($invoice->status !== InvoiceStatus::Unbilled && ! $data['force_update']) {
            $stats['skipped']++;

            return $stats;
        }

        // バージョンを保存してから更新を試みる（楽観ロック）
        $currentVersion = $invoice->version ?? 0;

        $updated = $invoice->where('version', $currentVersion)
            ->update([
                'rent_subtotal' => $data['rent_subtotal'],
                'management_fee_subtotal' => $data['management_subtotal'],
                'service_subtotal' => $data['service_subtotal'],
                'total_amount' => $data['total_amount'],
                'taxable_amount' => $data['taxable_amount'],
                'tax_amount' => $data['tax_amount'],
                'tax_rate' => $data['tax_rate'],
                'tax_breakdown' => $data['tax_breakdown'],
                'version' => $currentVersion + 1,
            ]);

        if ($updated) {
            $stats['updated']++;
        } else {
            // バージョン競合: 他のプロセスが更新済み
            $stats['conflicts']++;
            $stats['skipped']++;
        }

        return $stats;
    }

    /**
     * デッドロックエラーかどうか判定
     */
    private function isDeadlock(QueryException $e): bool
    {
        $code = $e->getCode();
        $message = $e->getMessage();

        // MySQL: 1213 (ER_LOCK_DEADLOCK), 1205 (ER_LOCK_WAIT_TIMEOUT)
        // PostgreSQL: 40P01 (deadlock_detected), 55P03 (lock_not_available)
        // SQL Server: 1205
        $deadlockCodes = [1213, 1205, 40001, 55003];

        if (in_array($code, $deadlockCodes, true)) {
            return true;
        }

        // エラーメッセージベースの判定（ドライバによってコードが異なる場合）
        return str_contains(strtolower($message), 'deadlock')
            || str_contains(strtolower($message), 'lock wait timeout');
    }

    /**
     * ユニーク制約違反かどうか判定
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        $code = $e->getCode();
        $message = $e->getMessage();

        // MySQL: 1062 (ER_DUP_ENTRY)
        // PostgreSQL: 23505 (unique_violation)
        // SQLite: 19 (SQLITE_CONSTRAINT_UNIQUE) / 2067
        // SQL Server: 2601, 2627
        $uniqueCodes = [1062, 23505, 19, 2067, 2601, 2627];

        if (in_array($code, $uniqueCodes, true)) {
            return true;
        }

        return str_contains(strtolower($message), 'duplicate entry')
            || str_contains(strtolower($message), 'unique constraint')
            || str_contains(strtolower($message), 'unique violation');
    }
}
