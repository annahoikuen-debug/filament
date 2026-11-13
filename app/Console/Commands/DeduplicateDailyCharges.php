<?php

namespace App\Console\Commands;

use App\Models\DailyCharge;
use App\Models\MonthlyClosingState;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 日次利用料の重複レコードを検出・統合するコマンド。
 *
 * 同一入居者×同一品目×同一日が複数行存在する場合、
 * 数量を加算して1行に集約し、不要な行を削除する。
 *
 * --dry-run オプションで実際に変更せず重複グループを一覧表示できる。
 */
class DeduplicateDailyCharges extends Command
{
    protected $signature = 'daily-charges:deduplicate
                            {--dry-run : 実際に変更せず、重複グループのみ表示}
                            {--year-month= : 対象年月 (YYYY-MM形式、省略時は全期間)}';

    protected $description = '日次利用料の重複レコードを検出し、数量加算で1行に統合する';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $yearMonth = $this->option('year-month');

        $this->info('=== 日次利用料 重複チェック・統合 ===');
        $this->info('ドライラン: '.($dryRun ? 'ON' : 'OFF'));
        if ($yearMonth) {
            $this->info("対象年月: {$yearMonth}");
        }

        $query = DailyCharge::select('resident_id', 'charge_item_id', 'date', DB::raw('COUNT(*) as cnt'))
            ->selectRaw('SUM(quantity) as total_quantity')
            ->groupBy('resident_id', 'charge_item_id', 'date')
            ->having('cnt', '>', 1);

        if ($yearMonth) {
            $query->whereBetween('date', [
                "{$yearMonth}-01",
                date('Y-m-t', strtotime("{$yearMonth}-01")),
            ]);
        }

        $duplicates = $query->get();

        if ($duplicates->isEmpty()) {
            $this->info('重複レコードは見つかりませんでした。');

            return self::SUCCESS;
        }

        $this->warn("重複グループ: {$duplicates->count()} 件");

        if ($dryRun) {
            $this->table(
                ['入居者ID', '品目ID', '日付', '重複行数', '合計数量'],
                $duplicates->map(fn ($d) => [
                    $d->resident_id,
                    $d->charge_item_id,
                    $d->date,
                    $d->cnt,
                    $d->total_quantity,
                ])->toArray()
            );

            $this->info('--dry-run モードのため変更は行いません。');
            $this->info('実際に統合するには --dry-run オプションを外して実行してください。');

            return self::SUCCESS;
        }

        $merged = 0;
        $deleted = 0;
        $errors = 0;

        foreach ($duplicates as $dup) {
            try {
                // 同じキーの全行を取得（ID昇順：最古を保持）
                $rows = DailyCharge::where('resident_id', $dup->resident_id)
                    ->where('charge_item_id', $dup->charge_item_id)
                    ->where('date', $dup->date)
                    ->orderBy('id', 'asc')
                    ->get();

                $keepRow = $rows->first();
                $deleteRows = $rows->slice(1);

                // 数量を合算して保持行を更新
                $keepRow->quantity = (int) $dup->total_quantity;
                $keepRow->save();

                // 重複行を削除（月次締めガードをバイパスして直接削除）
                foreach ($deleteRows as $row) {
                    DB::table('daily_charges')->where('id', $row->id)->delete();
                    $deleted++;
                }

                $merged++;
                $this->info("統合: resident={$dup->resident_id} item={$dup->charge_item_id} date={$dup->date} → 数量 {$dup->total_quantity}");

            } catch (\Throwable $e) {
                $errors++;
                Log::error("DailyCharge dedup error: resident={$dup->resident_id} item={$dup->charge_item_id} date={$dup->date}", [
                    'error' => $e->getMessage(),
                ]);
                $this->error("エラー: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("統合グループ: {$merged} 件 / 削除行: {$deleted} 件 / エラー: {$errors} 件");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
