<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'billing:generate-monthly 
        {--year-month= : 対象年月 (YYYY-MM形式、省略時は前月)}
        {--facility-id= : 対象施設ID (省略時は全施設)}
        {--force : 確定済み(Billed/Paid)も強制再計算}
        {--dry-run : 実際の保存を行わずシミュレーションのみ実行}';

    protected $description = '月次請求データを自動生成・更新する（毎月1日実行推奨）';

    public function __construct(
        private InvoiceCalculationService $calculationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $yearMonth = $this->option('year-month') ?? Carbon::now()->subMonth()->format('Y-m');
        $facilityId = $this->option('facility-id') ? (int) $this->option('facility-id') : null;
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if (! $dryRun) {
            $this->info('月次請求一括生成前の安全バックアップを実行中...');
            try {
                $this->callSilently('backup:database', [
                    '--type' => 'database',
                    '--compress' => true,
                ]);
            } catch (\Throwable $e) {
                $this->warn('事前バックアップに失敗しましたが処理を続行します: '.$e->getMessage());
            }
        }

        $this->info('=== 月次請求生成開始 ===');
        $this->info("対象年月: {$yearMonth}");
        $this->info('対象施設: '.($facilityId ? "ID {$facilityId}" : '全施設'));
        $this->info('強制再計算: '.($force ? 'ON' : 'OFF'));
        $this->info('ドライラン: '.($dryRun ? 'ON (保存しない)' : 'OFF'));

        // 年月形式バリデーション
        if (! preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            $this->error("年月形式が不正です: {$yearMonth} (YYYY-MM形式で指定してください)");

            return self::FAILURE;
        }

        try {
            // 月一括トランザクションは行わず、入居者ごとのチャンクトランザクションで
            // 部分失敗時に全体を巻き戻さず、長時間のロック取得も避ける
            $result = $this->processMonthlyInvoices($yearMonth, $facilityId, $force, $dryRun);

            $this->outputResults($result, $dryRun);

            // アクティビティログ記録（monthly_invoice ログ名を使用）
            activity('monthly_invoice')
                ->withProperties([
                    'year_month' => $yearMonth,
                    'facility_id' => $facilityId,
                    'force' => $force,
                    'dry_run' => $dryRun,
                    'result' => $result,
                ])
                ->log("月次請求生成コマンド実行: {$yearMonth}");

            // 住民ごとの処理エラーが1件でもあれば失敗終了する
            // （スケジューラの onFailure が反応できるようにするため）
            if ($result['errors'] > 0) {
                Log::error('GenerateMonthlyInvoices completed with errors', [
                    'year_month' => $yearMonth,
                    'facility_id' => $facilityId,
                    'errors' => $result['errors'],
                ]);

                return self::FAILURE;
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("エラーが発生しました: {$e->getMessage()}");
            Log::error('GenerateMonthlyInvoices failed', [
                'year_month' => $yearMonth,
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            activity('monthly_invoice')
                ->withProperties([
                    'year_month' => $yearMonth,
                    'facility_id' => $facilityId,
                    'force' => $force,
                    'dry_run' => $dryRun,
                    'error' => $e->getMessage(),
                ])
                ->log("月次請求生成コマンド失敗: {$yearMonth}");

            return self::FAILURE;
        }
    }

    private function processMonthlyInvoices(string $yearMonth, ?int $facilityId, bool $force, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        // 対象入居者を取得（指定年月に在籍している入居者）
        $query = Resident::query()
            ->whereHas('facility', fn ($q) => $q->where('is_active', true))
            ->when($facilityId, fn ($q) => $q->where('facility_id', $facilityId));

        $residents = $query->get();

        $this->info("対象入居者数: {$residents->count()}");

        $progressBar = null;
        if ($residents->count() > 0) {
            $progressBar = $this->output->createProgressBar($residents->count());
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $progressBar->start();
        }

        foreach ($residents as $resident) {
            try {
                // 指定年月と在籍期間が重複しているかチェック
                // （月初日在居判定だと月中入居者が対象月の請求から漏れるため）
                $monthStart = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
                $monthEnd = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth();
                if (! $resident->isResidentDuring($monthStart, $monthEnd)) {
                    $skipped++;
                    $progressBar?->advance();

                    continue;
                }

                // 既存請求データ確認
                $existingInvoice = MonthlyInvoice::where('resident_id', $resident->id)
                    ->where('billing_year_month', $yearMonth)
                    ->first();

                if ($existingInvoice) {
                    // 確定済みで force オプションがない場合はスキップ
                    if (! $force && $existingInvoice->status !== InvoiceStatus::Unbilled) {
                        $skipped++;
                        $progressBar?->advance();

                        continue;
                    }

                    if (! $dryRun) {
                        // 再計算・更新（入居者ごとのトランザクション）
                        DB::transaction(function () use ($existingInvoice) {
                            $this->calculationService->calculate($existingInvoice);
                            $existingInvoice->save();
                        });
                    }
                    $updated++;
                } else {
                    // 新規作成（入居者ごとのトランザクション）
                    if (! $dryRun) {
                        DB::transaction(function () use ($resident, $yearMonth) {
                            $invoice = new MonthlyInvoice([
                                'resident_id' => $resident->id,
                                'facility_id' => $resident->facility_id,
                                'billing_year_month' => $yearMonth,
                                'status' => InvoiceStatus::Unbilled,
                                'version' => 0,
                            ]);
                            $this->calculationService->calculate($invoice);
                            $invoice->save();
                        });
                    }
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::error("GenerateMonthlyInvoices: resident {$resident->id} 処理エラー", [
                    'resident_id' => $resident->id,
                    'year_month' => $yearMonth,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar?->advance();
        }

        if ($progressBar) {
            $progressBar->finish();
            $this->newLine();
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

    private function outputResults(array $result, bool $dryRun): void
    {
        $this->newLine();
        $this->info('=== 実行結果 '.($dryRun ? '(ドライラン)' : '').' ===');
        $this->table(
            ['項目', '件数'],
            [
                ['新規作成', $result['created']],
                ['更新', $result['updated']],
                ['スキップ', $result['skipped']],
                ['エラー', $result['errors']],
            ]
        );

        if ($result['errors'] > 0) {
            $this->warn("エラーが {$result['errors']} 件発生しました。ログを確認してください。");
        }

        if ($dryRun) {
            $this->warn('ドライランモードのため、データは保存されていません。実際に実行するには --dry-run オプションを外してください。');
        }
    }
}
