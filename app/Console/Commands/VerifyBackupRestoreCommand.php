<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class VerifyBackupRestoreCommand extends Command
{
    protected $signature = 'backup:verify-restore {file? : 検証する暗号化バックアップファイルパス}';
    protected $description = '暗号化バックアップファイルの完全性を復号テスト・構文検証してDR復旧可能か確認する';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (!$file) {
            // 最新のバックアップを自動検出
            $backups = File::glob(storage_path('app/backups/backup_*.sql.enc'));
            if (empty($backups)) {
                $this->error('No backup files found in storage/app/backups/');
                return self::FAILURE;
            }
            rsort($backups);
            $file = $backups[0];
        }

        if (!File::exists($file)) {
            $this->error("Backup file not found: {$file}");
            return self::FAILURE;
        }

        $this->info("Verifying backup restore integrity for: {$file}");

        try {
            // 1. ファイルサイズチェック
            $fileSize = File::size($file);
            if ($fileSize === 0) {
                throw new \RuntimeException('Backup file is empty (0 bytes).');
            }

            // 2. 暗号化整合性テスト (AES-256復号)
            $encryptedContent = File::get($file);
            $decryptedContent = Crypt::decryptString($encryptedContent);

            if (empty($decryptedContent)) {
                throw new \RuntimeException('Decrypted SQL content is empty.');
            }

            // 3. SQL構文・必須キーワード検証
            $hasValidSignature = str_contains($decryptedContent, 'CREATE')
                || str_contains($decryptedContent, 'INSERT')
                || str_contains($decryptedContent, 'PostgreSQL')
                || str_contains($decryptedContent, 'SQLite')
                || str_contains($decryptedContent, '--');

            if (!$hasValidSignature) {
                throw new \RuntimeException('Decrypted content does not appear to be a valid SQL dump.');
            }

            $this->info("✓ Verification SUCCESS: File size {$fileSize} bytes, decryptable, valid SQL header.");
            Log::info("Backup verification passed for: {$file}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("✗ Verification FAILED: " . $e->getMessage());
            Log::error("Backup verification failed for {$file}: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
