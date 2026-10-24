<?php

namespace Tests\Feature;

use App\Services\Backup\BackupStorageService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupAndRestoreTest extends TestCase
{
    private string $testBackupPath;

    protected function setUp(): void
    {
        parent::setUp();
        File::ensureDirectoryExists(storage_path('app/backups'));
        $this->testBackupPath = storage_path('app/backups/test_sample_backup.sql.enc');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testBackupPath)) {
            File::delete($this->testBackupPath);
        }
        parent::tearDown();
    }

    public function test_backup_database_command_creates_encrypted_dump(): void
    {
        $exitCode = Artisan::call('backup:database', [
            '--output' => $this->testBackupPath,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertTrue(File::exists($this->testBackupPath));

        // 暗号化されていることを検証（平文のSQLキーワードが外から見えない）
        $content = File::get($this->testBackupPath);
        $this->assertStringNotContainsString('CREATE TABLE', $content);

        // 復号できることを検証
        $decrypted = Crypt::decryptString($content);
        $this->assertNotEmpty($decrypted);
    }

    public function test_verify_restore_command_validates_healthy_and_corrupt_dumps(): void
    {
        // 1. 正常なダンプ
        $healthySql = 'CREATE TABLE users (id int); INSERT INTO users VALUES (1);';
        File::put($this->testBackupPath, Crypt::encryptString($healthySql));

        $exitCodeSuccess = Artisan::call('backup:verify-restore', [
            'file' => $this->testBackupPath,
        ]);
        $this->assertEquals(0, $exitCodeSuccess);

        // 2. 破損したファイル（暗号化キー不一致や改ざん）
        File::put($this->testBackupPath, 'CORRUPTED_GARBAGE_DATA_12345');

        $exitCodeFail = Artisan::call('backup:verify-restore', [
            'file' => $this->testBackupPath,
        ]);
        $this->assertEquals(1, $exitCodeFail);
    }

    public function test_backup_storage_service_syncs_and_rotates_old_files(): void
    {
        Storage::fake('s3');

        $healthySql = 'CREATE TABLE dummy (id int);';
        File::put($this->testBackupPath, Crypt::encryptString($healthySql));

        $service = new BackupStorageService;
        $success = $service->syncAndRotate($this->testBackupPath, 's3', 30);

        $this->assertTrue($success);

        // S3 にファイルが存在することを確認
        $files = Storage::disk('s3')->allFiles('database_backups');
        $this->assertNotEmpty($files);
        $this->assertStringEndsWith('test_sample_backup.sql.enc', $files[0]);
    }
}
