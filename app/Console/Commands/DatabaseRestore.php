<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use ZipArchive;
use Exception;

class DatabaseRestore extends Command
{
    protected $signature = 'backup:restore 
                            {backup_file : 復元するバックアップファイルまたはディレクトリのパス}
                            {--type=full : 復元タイプ (full|database|storage)}
                            {--force : 確認なしで実行}
                            {--dry-run : 実際の復元を行わず、手順のみ表示}';

    protected $description = 'データベースとストレージをバックアップから復元';

    private array $restoreInfo = [];

    public function handle(): int
    {
        $this->info('=== データベース復元開始 ===');
        $this->restoreInfo['started_at'] = Carbon::now()->toDateTimeString();
        $this->restoreInfo['type'] = $this->option('type');
        $this->restoreInfo['backup_file'] = $this->argument('backup_file');

        // バックアップファイル/ディレクトリの存在確認
        $backupPath = $this->resolveBackupPath($this->argument('backup_file'));
        if (!$backupPath) {
            $this->error('バックアップが見つかりません: ' . $this->argument('backup_file'));
            return self::FAILURE;
        }

        $this->restoreInfo['resolved_path'] = $backupPath;

        // 確認プロンプト
        if (!$this->option('force') && !$this->option('dry-run')) {
            $this->warn('⚠️  警告: この操作は現在のデータを上書きします！');
            $this->warn('バックアップパス: ' . $backupPath);
            $this->warn('復元タイプ: ' . $this->option('type'));
            
            if (!$this->confirm('本当に実行しますか？')) {
                $this->info('キャンセルしました');
                return self::SUCCESS;
            }
        }

        if ($this->option('dry-run')) {
            $this->info('=== ドライランモード: 実際の復元は行いません ===');
        }

        try {
            // ZIPファイルの場合は展開
            $workingDir = $backupPath;
            $tempDir = null;
            
            if (is_file($backupPath) && pathinfo($backupPath, PATHINFO_EXTENSION) === 'zip') {
                $this->info('ZIPファイルを展開中...');
                $tempDir = storage_path('app/restore_temp_' . Carbon::now()->format('Ymd_His'));
                $workingDir = $this->extractZip($backupPath, $tempDir);
                $this->restoreInfo['extracted_to'] = $workingDir;
            }

            // 復元タイプに応じた処理
            switch ($this->option('type')) {
                case 'full':
                    if (!$this->option('dry-run')) {
                        $this->restoreDatabase($workingDir);
                        $this->restoreStorage($workingDir);
                    } else {
                        $this->showDryRunSteps($workingDir, ['database', 'storage']);
                    }
                    break;
                case 'database':
                    if (!$this->option('dry-run')) {
                        $this->restoreDatabase($workingDir);
                    } else {
                        $this->showDryRunSteps($workingDir, ['database']);
                    }
                    break;
                case 'storage':
                    if (!$this->option('dry-run')) {
                        $this->restoreStorage($workingDir);
                    } else {
                        $this->showDryRunSteps($workingDir, ['storage']);
                    }
                    break;
                default:
                    $this->error("不明な復元タイプ: {$this->option('type')}");
                    return self::FAILURE;
            }

            // 一時ディレクトリの削除
            if ($tempDir && is_dir($tempDir)) {
                $this->deleteDirectory($tempDir);
            }

            $this->restoreInfo['completed_at'] = Carbon::now()->toDateTimeString();
            $this->restoreInfo['status'] = 'success';
            $this->logRestoreResult();

            $this->info('=== 復元完了 ===');
            
            if (!$this->option('dry-run')) {
                $this->warn('※ アプリケーションキャッシュのクリアを推奨します: php artisan optimize:clear');
            }

            return self::SUCCESS;

        } catch (Exception $e) {
            // 一時ディレクトリの削除
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->deleteDirectory($tempDir);
            }
            
            $this->restoreInfo['completed_at'] = Carbon::now()->toDateTimeString();
            $this->restoreInfo['status'] = 'failed';
            $this->restoreInfo['error'] = $e->getMessage();
            $this->logRestoreResult();
            
            $this->error("復元失敗: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function resolveBackupPath(string $path): ?string
    {
        // 絶対パスの場合
        if (is_file($path) || is_dir($path)) {
            return $path;
        }
        
        // ストレージ内のバックアップディレクトリから検索
        $backupBaseDir = storage_path('app/backups');
        
        // 完全一致
        $fullPath = $backupBaseDir . '/' . $path;
        if (is_file($fullPath) || is_dir($fullPath)) {
            return $fullPath;
        }
        
        // パターンマッチで検索
        $patterns = [
            $backupBaseDir . '/' . $path,
            $backupBaseDir . '/backup_' . $path,
            $backupBaseDir . '/' . $path . '.zip',
            $backupBaseDir . '/backup_' . $path . '.zip',
        ];
        
        foreach ($patterns as $pattern) {
            $matches = glob($pattern);
            if (!empty($matches)) {
                return $matches[0];
            }
            
            // 部分一致で検索
            $partialMatches = glob($backupBaseDir . '/*' . $path . '*');
            if (!empty($partialMatches)) {
                return $partialMatches[0];
            }
        }
        
        return null;
    }

    private function extractZip(string $zipPath, string $destDir): string
    {
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath) !== true) {
            throw new Exception('ZIPファイルを開けません: ' . $zipPath);
        }
        
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        
        $zip->extractTo($destDir);
        $zip->close();
        
        // 展開されたディレクトリを特定（通常は単一のサブディレクトリ）
        $entries = scandir($destDir);
        $entries = array_diff($entries, ['.', '..']);
        
        if (count($entries) === 1 && is_dir($destDir . '/' . $entries[0])) {
            return $destDir . '/' . $entries[0];
        }
        
        return $destDir;
    }

    private function restoreDatabase(string $backupDir): void
    {
        $this->info('データベース復元中...');
        
        // ダンプファイルを検索
        $dumpFiles = glob($backupDir . '/database_*.sql');
        if (empty($dumpFiles)) {
            $dumpFiles = glob($backupDir . '/database_*.dump');
        }
        if (empty($dumpFiles)) {
            $dumpFiles = glob($backupDir . '/*.sql');
        }
        
        if (empty($dumpFiles)) {
            throw new Exception('データベースダンプファイルが見つかりません: ' . $backupDir);
        }
        
        $dumpFile = $dumpFiles[0];
        $this->info("使用するダンプファイル: {$dumpFile}");
        
        $dbConfig = Config::get('database.connections.' . Config::get('database.default'));
        $driver = $dbConfig['driver'] ?? 'mysql';
        
        switch ($driver) {
            case 'mysql':
                $this->restoreMySQL($dbConfig, $dumpFile);
                break;
            case 'pgsql':
            case 'postgres':
                $this->restorePostgreSQL($dbConfig, $dumpFile);
                break;
            case 'sqlite':
                $this->restoreSQLite($dbConfig, $dumpFile);
                break;
            default:
                throw new Exception("未対応のデータベースドライバー: {$driver}");
        }
        
        $this->restoreInfo['database_restored'] = $dumpFile;
        $this->info("データベース復元完了");
    }

    private function restoreMySQL(array $config, string $dumpFile): void
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'] ?? '';
        
        $command = "mysql --host={$host} --port={$port} --user={$username}";
        
        if ($password) {
            $command .= " --password={$password}";
        }
        
        $command .= " {$database} < {$dumpFile} 2>&1";
        
        $this->executeCommand($command, 'MySQL復元');
    }

    private function restorePostgreSQL(array $config, string $dumpFile): void
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
        
        // カスタム形式かどうか確認
        $isCustom = false;
        $header = file_get_contents($dumpFile, false, null, 0, 5);
        if (str_starts_with($header, 'PGDMP')) {
            $isCustom = true;
        }
        
        if ($isCustom) {
            $command = "pg_restore --host={$host} --port={$port} --username={$username} --no-password --dbname={$database} --clean --if-exists {$dumpFile} 2>&1";
        } else {
            $command = "psql --host={$host} --port={$port} --username={$username} --dbname={$database} < {$dumpFile} 2>&1";
        }
        
        $this->executeCommand($command, 'PostgreSQL復元', $env);
    }

    private function restoreSQLite(array $config, string $dumpFile): void
    {
        $database = $config['database'];
        
        // 既存DBのバックアップ
        if (file_exists($database)) {
            $backup = $database . '.pre_restore_' . Carbon::now()->format('Ymd_His');
            copy($database, $backup);
            $this->info("既存DBをバックアップ: {$backup}");
        }
        
        // ダンプファイルから復元
        if (filesize($dumpFile) > 1000000) { // 1MB以上はsqlite3コマンド使用
            $command = "sqlite3 {$database} < {$dumpFile} 2>&1";
            $this->executeCommand($command, 'SQLite復元');
        } else {
            // 小さいファイルは直接コピー（ダンプがCREATE文のみの場合）
            copy($dumpFile, $database);
        }
        
        $this->info("SQLite復元完了");
    }

    private function restoreStorage(string $backupDir): void
    {
        $this->info('ストレージ復元中...');
        
        $backupStoragePath = $backupDir . '/storage';
        if (!is_dir($backupStoragePath)) {
            $this->warn('ストレージバックアップが見つかりません: ' . $backupStoragePath);
            return;
        }
        
        $storagePath = storage_path('app');
        
        // 現在のストレージをバックアップ
        $currentBackup = storage_path('app_pre_restore_' . Carbon::now()->format('Ymd_His'));
        $this->info("現在のストレージをバックアップ: {$currentBackup}");
        $this->copyDirectory($storagePath, $currentBackup);
        
        // 除外ディレクトリ（バックアップ自体は復元しない）
        $exclude = ['backups', 'debugbar', 'telescope', 'restore_temp_*'];
        
        // 復元実行
        $this->copyDirectory($backupStoragePath, $storagePath, $exclude);
        
        $this->restoreInfo['storage_restored'] = true;
        $this->info("ストレージ復元完了");
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
                if (fnmatch($ex . '/*', $relativePath) || fnmatch($ex, $relativePath) || str_starts_with($relativePath, $ex . '/')) {
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

    private function showDryRunSteps(string $backupDir, array $types): void
    {
        $this->info('=== ドライラン: 実行される手順 ===');
        
        if (in_array('database', $types)) {
            $dumpFiles = glob($backupDir . '/database_*.sql');
            if (empty($dumpFiles)) {
                $dumpFiles = glob($backupDir . '/database_*.dump');
            }
            if (empty($dumpFiles)) {
                $dumpFiles = glob($backupDir . '/*.sql');
            }
            
            if (!empty($dumpFiles)) {
                $this->line("  [DATABASE] ダンプファイル: " . basename($dumpFiles[0]));
                $this->line("  [DATABASE] サイズ: " . $this->formatBytes(filesize($dumpFiles[0])));
                $this->line("  [DATABASE] 実行コマンド: mysql < dump.sql (または pg_restore / sqlite3)");
            } else {
                $this->line("  [DATABASE] ダンプファイルが見つかりません");
            }
        }
        
        if (in_array('storage', $types)) {
            $backupStoragePath = $backupDir . '/storage';
            if (is_dir($backupStoragePath)) {
                $size = $this->getDirectorySize($backupStoragePath);
                $this->line("  [STORAGE] バックアップパス: {$backupStoragePath}");
                $this->line("  [STORAGE] サイズ: " . $this->formatBytes($size));
                $this->line("  [STORAGE] 復元先: " . storage_path('app'));
                $this->line("  [STORAGE] 事前バックアップ: storage/app_pre_restore_YYYYMMDD_HHMMSS");
            } else {
                $this->line("  [STORAGE] ストレージバックアップが見つかりません");
            }
        }
        
        $this->info('=== ドライラン終了 ===');
    }

    private function logRestoreResult(): void
    {
        $logFile = storage_path('logs/restore-' . Carbon::now()->format('Y-m-d') . '.log');
        $logEntry = Carbon::now()->toDateTimeString() . ' ' . json_encode($this->restoreInfo, JSON_UNESCAPED_UNICODE) . PHP_EOL;
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