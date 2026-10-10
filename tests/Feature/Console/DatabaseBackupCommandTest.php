<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // バックアップディレクトリを削除
        File::deleteDirectory(storage_path('app/backups'));
        parent::tearDown();
    }

    public function test_backup_database_type_success(): void
    {
        // sqlite ドライバーはファイルコピーでダンプする
        config()->set('database.default', 'sqlite');

        $this->artisan('backup:database', ['--type' => 'database'])
            ->expectsOutputToContain('バックアップ完了')
            ->assertSuccessful();

        // バックアップディレクトリに .sql ファイルが作成されていること
        $dirs = glob(storage_path('app/backups').'/backup_*');
        $this->assertNotEmpty($dirs);

        $sqlFiles = glob($dirs[0].'/*.sql');
        $this->assertNotEmpty($sqlFiles);
        $this->assertGreaterThan(0, filesize($sqlFiles[0]));

        // ログファイルが出力されていること
        $this->assertFileExists(storage_path('logs/backup-'.now()->format('Y-m-d').'.log'));
    }

    public function test_backup_with_compress_creates_zip(): void
    {
        config()->set('database.default', 'sqlite');

        $this->artisan('backup:database', ['--type' => 'database', '--compress' => true])
            ->assertSuccessful();

        $zips = glob(storage_path('app/backups').'/backup_*.zip');
        $this->assertNotEmpty($zips);
        $this->assertGreaterThan(0, filesize($zips[0]));

        // 圧縮後は元ディレクトリが削除されていること
        $dirs = glob(storage_path('app/backups').'/backup_*');
        $dirs = array_filter($dirs, 'is_dir');
        $this->assertEmpty($dirs);
    }

    public function test_backup_full_type_backs_up_storage_too(): void
    {
        config()->set('database.default', 'sqlite');

        $this->artisan('backup:database', ['--type' => 'full'])
            ->assertSuccessful();

        $dirs = glob(storage_path('app/backups').'/backup_*');
        $this->assertNotEmpty($dirs);
        $this->assertDirectoryExists($dirs[0].'/storage');
    }

    public function test_backup_invalid_type_fails(): void
    {
        $this->artisan('backup:database', ['--type' => 'invalid'])
            ->expectsOutputToContain('不明なバックアップタイプ: invalid')
            ->assertExitCode(1);
    }

    public function test_backup_with_notify_option(): void
    {
        config()->set('database.default', 'sqlite');

        $this->artisan('backup:database', ['--type' => 'database', '--notify' => true])
            ->expectsOutputToContain('通知送信機能は別途実装が必要です')
            ->assertSuccessful();
    }

    public function test_cleanup_old_backups_removes_expired(): void
    {
        config()->set('database.default', 'sqlite');

        // 古いバックアップ（40日前）を作成
        $oldDir = storage_path('app/backups/backup_20200101_000000');
        File::ensureDirectoryExists($oldDir);
        file_put_contents($oldDir.'/dummy.sql', 'dummy');

        // 古いZIPも作成
        file_put_contents(storage_path('app/backups/backup_20190101_000000.zip'), 'dummy zip');

        $this->artisan('backup:database', ['--type' => 'database', '--retention' => '30'])
            ->assertSuccessful();

        $this->assertDirectoryDoesNotExist($oldDir);
        $this->assertFileDoesNotExist(storage_path('app/backups/backup_20190101_000000.zip'));
    }

    public function test_backup_sqlite_memory_falls_back_to_pdo_dump(): void
    {
        // :memory: 設定でも PDO 経由のダンプが動作すること
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        $this->artisan('backup:database', ['--type' => 'database'])
            ->assertSuccessful();

        $dirs = glob(storage_path('app/backups').'/backup_*');
        $this->assertNotEmpty($dirs);

        $sqlFiles = glob($dirs[0].'/*.sql');
        $this->assertNotEmpty($sqlFiles);

        // SQLite のダンプにはテーブル作成文が含まれていること
        $content = file_get_contents($sqlFiles[0]);
        $this->assertStringContainsString('CREATE TABLE', $content);
    }
}
