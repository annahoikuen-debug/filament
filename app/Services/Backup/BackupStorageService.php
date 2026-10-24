<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupStorageService
{
    /**
     * バックアップファイルをクラウド外部ストレージへ退避し、世代管理を実施する
     *
     * @param  string  $localFilePath  ローカルの暗号化ダンプパス
     * @param  string  $disk  退避先ディスク（デフォルト: s3 / テスト時: fake）
     * @param  int  $retentionDaily  保管日数（デフォルト30日）
     */
    public function syncAndRotate(string $localFilePath, string $disk = 's3', int $retentionDaily = 30): bool
    {
        if (! File::exists($localFilePath)) {
            Log::error("Backup file not found for sync: {$localFilePath}");

            return false;
        }

        $fileName = basename($localFilePath);
        $remotePath = 'database_backups/'.now()->format('Y/m')."/{$fileName}";

        try {
            $stream = fopen($localFilePath, 'r');
            Storage::disk($disk)->put($remotePath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            Log::info("Backup synced to cloud storage: {$remotePath}");

            // 世代管理: 保持日数を超えた古いバックアップを削除
            $this->rotateOldBackups($disk, $retentionDaily);

            return true;
        } catch (\Throwable $e) {
            Log::error("Failed to sync backup to cloud: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);

            return false;
        }
    }

    /**
     * 保持期限切れの古いバックアップを削除
     */
    protected function rotateOldBackups(string $disk, int $retentionDays): void
    {
        $allFiles = Storage::disk($disk)->allFiles('database_backups');
        $cutoff = now()->subDays($retentionDays)->timestamp;

        foreach ($allFiles as $file) {
            $lastModified = Storage::disk($disk)->lastModified($file);
            if ($lastModified < $cutoff) {
                Storage::disk($disk)->delete($file);
                Log::info("Rotated out old backup: {$file}");
            }
        }
    }
}
