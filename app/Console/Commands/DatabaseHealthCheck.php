<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabaseHealthCheck extends Command
{
    protected $signature = 'db:health-check {--fix : 破損検出時にREINDEXおよびVACUUMによる自動修復を試みる}';

    protected $description = 'SQLiteデータベースの整合性チェックを実行';

    public function handle(): int
    {
        $connection = config('database.default');

        if ($connection !== 'sqlite') {
            $this->info("現在のDB接続は [{$connection}] です。SQLite整合性チェックをスキップします。");

            return self::SUCCESS;
        }

        $dbPath = config('database.connections.sqlite.database');

        if ($dbPath !== ':memory:' && ! file_exists($dbPath)) {
            $this->error("データベースファイルが存在しません: {$dbPath}");

            return self::FAILURE;
        }

        $this->info("データベース整合性チェック開始: {$dbPath}");
        if ($dbPath !== ':memory:') {
            $this->info('ファイルサイズ: '.$this->formatBytes((int) filesize($dbPath)));
        }

        try {
            $result = DB::select('PRAGMA integrity_check');
            $status = $result[0]->integrity_check ?? 'unknown';

            if ($status === 'ok') {
                $this->info('✓ データベース整合性チェック: 正常 (OK)');

                return self::SUCCESS;
            }

            $this->error("✗ データベース整合性チェック失敗: {$status}");

            if ($this->option('fix')) {
                $this->warn('自動修復を試みます (REINDEX / VACUUM)...');
                DB::statement('REINDEX');
                DB::statement('VACUUM');
                $this->info('REINDEX + VACUUM を実行しました。再度チェックしてください。');
            }

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error("データベースチェック中にエラーが発生しました: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }
}
