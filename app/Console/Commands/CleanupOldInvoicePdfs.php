<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CleanupOldInvoicePdfs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:cleanup-pdfs 
                            {--years=5 : 保存期間（年）}
                            {--dry-run : 実際には削除せず、対象ファイルのみ表示}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '指定期間より古い請求書PDFキャッシュファイルを削除します（デフォルト: 5年）';

    public function handle(): int
    {
        $years = (int) $this->option('years');
        $dryRun = (bool) $this->option('dry-run');
        $cutoffDate = Carbon::now()->subYears($years)->startOfMonth();

        $this->info("請求書PDFクリーンアップを開始します（保存期間: {$years}年、カットオフ: {$cutoffDate->format('Y-m-d')}）");

        if ($dryRun) {
            $this->warn('※ DRY-RUN モード: 実際には削除しません');
        }

        $invoicesDir = storage_path('app/invoices');

        if (! File::exists($invoicesDir)) {
            $this->info('請求書ディレクトリが存在しません。');

            return Command::SUCCESS;
        }

        $yearMonthDirs = File::directories($invoicesDir);
        $deletedCount = 0;
        $deletedSize = 0;
        $keptCount = 0;

        foreach ($yearMonthDirs as $dir) {
            $dirName = basename($dir); // 形式: YYYY-MM

            if (! preg_match('/^\d{4}-\d{2}$/', $dirName)) {
                $this->line("  スキップ (不正な形式): {$dirName}");

                continue;
            }

            try {
                $dirDate = Carbon::createFromFormat('Y-m', $dirName)->startOfMonth();
            } catch (\Exception $e) {
                $this->line("  スキップ (日付パース失敗): {$dirName}");

                continue;
            }

            if ($dirDate->lt($cutoffDate)) {
                // 対象: 削除
                $files = File::files($dir);
                $dirSize = 0;
                $fileCount = count($files);

                foreach ($files as $file) {
                    $dirSize += $file->getSize();
                }

                $this->line("  対象: {$dirName} ({$dirName}) - ".$this->formatBytes($dirSize).", {$fileCount} ファイル");

                if (! $dryRun) {
                    File::deleteDirectory($dir);
                    $this->line('    -> 削除完了');
                }

                $deletedCount++;
                $deletedSize += $dirSize;
            } else {
                $this->line("  保持: {$dirName} (カットオフ以降)");
                $keptCount++;
            }
        }

        $this->newLine();
        $this->table(
            ['項目', '値'],
            [
                ['削除対象ディレクトリ数', $deletedCount],
                ['保持ディレクトリ数', $keptCount],
                ['削除対象総サイズ', $this->formatBytes($deletedSize)],
                ['モード', $dryRun ? 'DRY-RUN (削除なし)' : '実行済み'],
            ]
        );

        if (! $dryRun && $deletedCount > 0) {
            $this->info("クリーンアップ完了: {$deletedCount} ディレクトリを削除しました (".$this->formatBytes($deletedSize).')');
            Log::info('Invoice PDF cleanup completed', [
                'deleted_dirs' => $deletedCount,
                'deleted_bytes' => $deletedSize,
                'retention_years' => $years,
            ]);
        } elseif ($dryRun) {
            $this->info('DRY-RUN 完了。実際に削除するには --dry-run オプションを外して実行してください。');
        } else {
            $this->info('削除対象のファイルはありませんでした。');
        }

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
