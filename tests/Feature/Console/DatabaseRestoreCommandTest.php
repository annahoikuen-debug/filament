<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseRestoreCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用バックアップ（ディレクトリ + database_*.sql）を作成
        $this->backupDir = storage_path('app/backups/backup_20260101_000000');
        File::ensureDirectoryExists($this->backupDir);
        file_put_contents(
            $this->backupDir.'/database_20260101_000000.sql',
            "-- dummy dump\nCREATE TABLE dummy_test (id INTEGER);\n"
        );
        File::ensureDirectoryExists($this->backupDir.'/storage');
        file_put_contents($this->backupDir.'/storage/dummy_file.txt', 'dummy storage');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/backups'));
        // 復元で作成された事前バックアップを削除
        foreach (glob(storage_path('app_pre_restore_*')) as $dir) {
            File::deleteDirectory($dir);
        }
        foreach (glob(database_path('*.pre_restore_*')) as $file) {
            @unlink($file);
        }
        foreach (File::glob(database_path('database_*.sqlite.pre_restore_*')) ?: [] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_restore_backup_not_found_fails(): void
    {
        $this->artisan('backup:restore', ['backup_file' => 'nonexistent_backup_xyz', '--force' => true])
            ->expectsOutputToContain('バックアップが見つかりません: nonexistent_backup_xyz')
            ->assertExitCode(1);
    }

    public function test_restore_dry_run_shows_steps(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', database_path('testing_restore.sqlite'));

        $this->artisan('backup:restore', [
            'backup_file' => 'backup_20260101_000000',
            '--type' => 'full',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('ドライランモード')
            ->expectsOutputToContain('=== 復元完了 ===')
            ->assertSuccessful();

        // dry-run なのでダンプは実行されていない（ログは出力される）
        $this->assertFileExists(storage_path('logs/restore-'.now()->format('Y-m-d').'.log'));
    }

    public function test_restore_dry_run_without_dump_files(): void
    {
        // .sql ファイルを削除したバックアップ
        File::delete($this->backupDir.'/database_20260101_000000.sql');

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', database_path('testing_restore.sqlite'));

        $this->artisan('backup:restore', [
            'backup_file' => 'backup_20260101_000000',
            '--type' => 'database',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('ダンプファイルが見つかりません')
            ->assertSuccessful();
    }

    public function test_restore_database_type_with_sqlite(): void
    {
        config()->set('database.default', 'sqlite');
        $dbPath = database_path('testing_restore.sqlite');
        config()->set('database.connections.sqlite.database', $dbPath);

        $this->artisan('backup:restore', [
            'backup_file' => 'backup_20260101_000000',
            '--type' => 'database',
            '--force' => true,
        ])
            ->expectsOutputToContain('データベース復元完了')
            ->expectsOutputToContain('=== 復元完了 ===')
            ->assertSuccessful();

        // 小さいダンプなので直接コピーされている
        $this->assertFileExists($dbPath);
        $this->assertStringContainsString('dummy_test', file_get_contents($dbPath));
    }

    public function test_restore_missing_dump_file_fails(): void
    {
        File::delete($this->backupDir.'/database_20260101_000000.sql');

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', database_path('testing_restore.sqlite'));

        $this->artisan('backup:restore', [
            'backup_file' => 'backup_20260101_000000',
            '--type' => 'database',
            '--force' => true,
        ])
            ->expectsOutputToContain('復元失敗')
            ->assertExitCode(1);
    }

    public function test_restore_storage_type_missing_storage_dir_warns(): void
    {
        File::deleteDirectory($this->backupDir.'/storage');

        config()->set('database.default', 'sqlite');

        $this->artisan('backup:restore', [
            'backup_file' => 'backup_20260101_000000',
            '--type' => 'storage',
            '--force' => true,
        ])
            ->expectsOutputToContain('ストレージバックアップが見つかりません')
            ->assertSuccessful();
    }

    public function test_restore_invalid_type_fails(): void
    {
        config()->set('database.default', 'sqlite');

        $this->artisan('backup:restore', [
            'backup_file' => 'backup_20260101_000000',
            '--type' => 'invalid',
            '--force' => true,
        ])
            ->expectsOutputToContain('不明な復元タイプ: invalid')
            ->assertExitCode(1);
    }

    public function test_restore_from_zip_archive(): void
    {
        // ZIPバックアップを作成（backup:database --compress 相当）
        $zipPath = storage_path('app/backups/backup_20260201_000000.zip');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('database_20260201_000000.sql', "-- zip dump\nCREATE TABLE zip_test (id INTEGER);\n");
        $zip->addFromString('storage/from_zip.txt', 'zip storage file');
        $zip->close();

        config()->set('database.default', 'sqlite');
        $dbPath = database_path('testing_restore.sqlite');
        config()->set('database.connections.sqlite.database', $dbPath);

        $this->artisan('backup:restore', [
            'backup_file' => 'backup_20260201_000000.zip',
            '--type' => 'full',
            '--force' => true,
        ])
            ->expectsOutputToContain('ZIPファイルを展開中')
            ->expectsOutputToContain('=== 復元完了 ===')
            ->assertSuccessful();

        $this->assertStringContainsString('zip_test', file_get_contents($dbPath));

        // 一時ディレクトリが削除されていること
        $tempDirs = glob(storage_path('app/restore_temp_*'));
        $this->assertEmpty($tempDirs);
    }

    public function test_resolve_backup_path_partial_match(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', database_path('testing_restore.sqlite'));

        // 部分一致（20260101）でバックアップが解決されること
        $this->artisan('backup:restore', [
            'backup_file' => '20260101',
            '--type' => 'database',
            '--dry-run' => true,
        ])
            ->assertSuccessful();
    }
}
