<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use ZipArchive;
use Exception;

class DatabaseBackup extends Command
{
    protected $signature = 'backup:database 
                            {--type=full : バックアップタイプ (full|database|storage)}
                            {--destination=local : 保存先 (local|s3)}
                            {--retention=30 : 保持日数}
                            {--compress : 圧縮して保存}
                            {--notify : 完了通知を送信}';

    protected $description = 'データベースとストレージのバックアップを実行';

    private array $backupInfo = [];

    public function handle(): int
    {
        $this->info('=== データベースバックアップ開始 ===');
        $this->backupInfo['started_at'] = Carbon::now()->toDateTimeString();
        $this->backupInfo['type'] = $this->option('type');
        $this->backupInfo['destination'] = $this->option('destination');

        try {
            // バックアップディレクトリの準備
            $backupDir = $this->prepareBackupDirectory();
            
            // バックアップタイプに応じた処理
            switch ($this->option('type')) {
                case 'full':
                    $this->backupDatabase($backupDir);
                    $this->backupStorage($backupDir);
                    break;
                case 'database':
                    $this->backupDatabase($backupDir);
                    break;
                case 'storage':
                    $this->backupStorage($backupDir);
                    break;
                default:
                    $this->error("不明なバックアップタイプ: {$this->option('type')}");
                    return self::FAILURE;
            }

            // 圧縮
            if ($this->option('compress')) {
                $zipPath = $this->compressBackup($backupDir);
                $this->backupInfo['zip_path'] = $zipPath;
                $this->backupInfo['zip_size'] = filesize($zipPath);
            }

            // 古いバックアップの削除
            $this->cleanupOldBackups($this->option('retention'));

            // S3アップロード
            if ($this->option('destination') === 's3') {
                $this->uploadToS3($backupDir);
            }

            $this->backupInfo['completed_at'] = Carbon::now()->toDateTimeString();
            $this->backupInfo['status'] = 'success';
            $this->logBackupResult();

            $this->info('=== バックアップ完了 ===');
            
            if ($this->option('notify')) {
                $this->sendNotification();
            }

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->backupInfo['completed_at'] = Carbon::now()->toDateTimeString();
            $this->backupInfo['status'] = 'failed';
            $this->backupInfo['error'] = $e->getMessage();
            $this->logBackupResult();
            
            $this->error("バックアップ失敗: {$e->getMessage()}");
            
            if ($this->option('notify')) {
                $this->sendNotification($e);
            }

            return self::FAILURE;
        }
    }

    private function prepareBackupDirectory(): string
    {
        $timestamp = Carbon::now()->format('Ymd_His');
        $backupDir = storage_path("app/backups/backup_{$timestamp}");
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $this->info("バックアップディレクトリ: {$backupDir}");
        $this->backupInfo['backup_dir'] = $backupDir;
        
        return $backupDir;
    }

    private function backupDatabase(string $backupDir): void
    {
        $this->info('データベースバックアップ中...');
        
        $dbConfig = Config::get('database.connections.' . Config::get('database.default'));
        $driver = $dbConfig['driver'] ?? 'mysql';
        
        $dumpFile = $backupDir . '/database_' . Carbon::now()->format('Ymd_His') . '.sql';
        
        switch ($driver) {
            case 'mysql':
                $this->backupMySQL($dbConfig, $dumpFile);
                break;
            case 'pgsql':
            case 'postgres':
                $this->backupPostgreSQL($dbConfig, $dumpFile);
                break;
            case 'sqlite':
                $this->backupSQLite($dbConfig, $dumpFile);
                break;
            default:
                throw new Exception("未対応のデータベースドライバー: {$driver}");
        }
        
        $this->backupInfo['database_dump'] = $dumpFile;
        $this->backupInfo['database_size'] = filesize($dumpFile);
        $this->info("データベースダンプ完了: {$dumpFile} (" . $this->formatBytes($this->backupInfo['database_size']) . ")");
    }

    private function backupMySQL(array $config, string $dumpFile): void
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'] ?? '';
        
        $command = "mysqldump --host={$host} --port={$port} --user={$username}";
        
        if ($password) {
            $command .= " --password={$password}";
        }
        
        $command .= " --single-transaction --routines --triggers --events {$database} > {$dumpFile} 2>&1";
        
        $this->executeCommand($command, 'MySQLダンプ');
    }

    private function backupPostgreSQL(array $config, string $dumpFile): void
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 5432;
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'] ?? '';
        
        $env = [];
        if ($password) {
            $env['PGPASSWORD'] = $password;
        }
        
        $command = "pg_dump --host={$host} --port={$port} --username={$username} --no-password --format=custom --file={$dumpFile} {$database} 2>&1";
        
        $this->executeCommand($command, 'PostgreSQLダンプ', $env);
    }

    private function backupSQLite(array $config, string $dumpFile): void
    {
        $database = $config['database'];
        
        // SQLiteの場合はファイルコピーで対応
        if (file_exists($database)) {
            copy($database, $dumpFile);
        } else {
            // メモリDBの場合はダンプ
            $command = "sqlite3 {$database} .dump > {$dumpFile} 2>&1";
            $this->executeCommand($command, 'SQLiteダンプ');
        }
    }

    private function backupStorage(string $backupDir): void
    {
        $this->info('ストレージバックアップ中...');
        
        $storagePath = storage_path('app');
        $backupStoragePath = $backupDir . '/storage';
        
        // 除外ディレクトリ
        $exclude = ['backups', 'debugbar', 'telescope'];
        
        $this->copyDirectory($storagePath, $backupStoragePath, $exclude);
        
        $this->backupInfo['storage_backup'] = $backupStoragePath;
        $this->backupInfo['storage_size'] = $this->getDirectorySize($backupStoragePath);
        $this->info("ストレージバックアップ完了: {$backupStoragePath} (" . $this->formatBytes($this->backupInfo['storage_size']) . ")");
    }

    private function copyDirectory(string $source, string $destination, array $exclude = []): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            $relativePath = $file->getPathname();
            $relativePath = substr($relativePath, strlen($source) + 1);
            
            // 除外チェック
            $skip = false;
            foreach ($exclude as $ex) {
                if (str_starts_with($relativePath, $ex . '/') || $relativePath === $ex) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;
            
            $targetPath = $destination . '/' . $relativePath;
            
            if ($file->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                copy($file->getPathname(), $targetPath);
            }
        }
    }

    private function compressBackup(string $backupDir): string
    {
        $this->info('バックアップ圧縮中...');
        
        $zipPath = $backupDir . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('ZIPファイルの作成に失敗しました');
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($backupDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($backupDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        
        $zip->close();
        
        // 元のディレクトリを削除（圧縮後）
        $this->deleteDirectory($backupDir);
        
        $this->info("圧縮完了: {$zipPath} (" . $this->formatBytes(filesize($zipPath)) . ")");
        
        return $zipPath;
    }

    private function cleanupOldBackups(int $retentionDays): void
    {
        $this->info("古いバックアップを削除中 (保持日数: {$retentionDays}日)...");
        
        $backupBaseDir = storage_path('app/backups');
        if (!is_dir($backupBaseDir)) {
            return;
        }
        
        $cutoff = Carbon::now()->subDays($retentionDays);
        $deleted = 0;
        
        $dirs = glob($backupBaseDir . '/backup_*');
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $dirName = basename($dir);
                // backup_YYYYMMDD_HHMMSS 形式から日付を抽出
                if (preg_match('/backup_(\d{8}_\d{6})/', $dirName, $matches)) {
                    $dateStr = $matches[1];
                    $backupDate = Carbon::createFromFormat('Ymd_His', $dateStr);
                    
                    if ($backupDate && $backupDate->lt($cutoff)) {
                        $this->deleteDirectory($dir);
                        $deleted++;
                        $this->line("  削除: {$dirName}");
                    }
                }
            }
        }
        
        // ZIPファイルもチェック
        $zips = glob($backupBaseDir . '/backup_*.zip');
        foreach ($zips as $zip) {
            $zipName = basename($zip);
            if (preg_match('/backup_(\d{8}_\d{6})/', $zipName, $matches)) {
                $dateStr = $matches[1];
                $backupDate = Carbon::createFromFormat('Ymd_His', $dateStr);
                
                if ($backupDate && $backupDate->lt($cutoff)) {
                    unlink($zip);
                    $deleted++;
                    $this->line("  削除: {$zipName}");
                }
            }
        }
        
        $this->info("古いバックアップ {$deleted} 件を削除しました");
        $this->backupInfo['deleted_old_backups'] = $deleted;
    }

    private function uploadToS3(string $backupDir): void
    {
        $this->info('S3へアップロード中...');
        
        $disk = Storage::disk('s3');
        $prefix = 'database-backups/' . Carbon::now()->format('Y/m/d/');
        
        if (is_dir($backupDir)) {
            // ディレクトリの場合は中身をアップロード
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($backupDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $relativePath = substr($file->getPathname(), strlen($backupDir) + 1);
                    $disk->put($prefix . $relativePath, file_get_contents($file->getPathname()));
                }
            }
        } elseif (is_file($backupDir)) {
            // ZIPファイルの場合
            $fileName = basename($backupDir);
            $disk->put($prefix . $fileName, file_get_contents($backupDir));
        }
        
        $this->info("S3アップロード完了: s3://{$disk->getConfig('bucket')}/{$prefix}");
        $this->backupInfo['s3_path'] = $prefix;
    }

    private function sendNotification(?Exception $error = null): void
    {
        // 通知実装（メール、Slack、Webhook等）
        $this->info('通知送信機能は別途実装が必要です');
        $this->backupInfo['notification_sent'] = true;
    }

    private function logBackupResult(): void
    {
        $logFile = storage_path('logs/backup-' . Carbon::now()->format('Y-m-d') . '.log');
        $logEntry = Carbon::now()->toDateTimeString() . ' ' . json_encode($this->backupInfo, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    private function executeCommand(string $command, string $description, array $env = []): void
    {
        $this->line("  実行中: {$description}");
        
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        
        $process = proc_open($command, $descriptors, $pipes, null, $env);
        
        if (!is_resource($process)) {
            throw new Exception("プロセス開始失敗: {$description}");
        }
        
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $returnCode = proc_close($process);
        
        if ($returnCode !== 0) {
            throw new Exception("{$description} 失敗 (コード: {$returnCode}): {$stderr}");
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function getDirectorySize(string $dir): int
    {
        $size = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}