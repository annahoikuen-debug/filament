<?php

namespace Tests\Feature\Regression;

use App\Models\Facility;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class DatabaseBackupRestoreTest extends TestCase
{
    protected string $backupBaseDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupBaseDir = storage_path('app/backups');

        // テスト用にバックアップディレクトリをクリーンアップ
        if (is_dir($this->backupBaseDir)) {
            File::deleteDirectory($this->backupBaseDir);
        }
        mkdir($this->backupBaseDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // テストで作成したバックアップ・一時ファイルを削除
        if (is_dir($this->backupBaseDir)) {
            File::deleteDirectory($this->backupBaseDir);
        }

        foreach (glob(storage_path('app/restore_temp_*')) as $dir) {
            File::deleteDirectory($dir);
        }

        parent::tearDown();
    }

    public function test_backup_databaseコマンドが正常に実行される(): void
    {
        $exitCode = Artisan::call('backup:database', [
            '--type' => 'database',
            '--destination' => 'local',
        ]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('バックアップ完了', $output);

        // ダンプファイルが作成されていること
        $dumpFiles = glob($this->backupBaseDir.'/backup_*/database_*.sql');
        $this->assertNotEmpty($dumpFiles, 'データベースダンプファイルが作成されているべき');
        $this->assertGreaterThan(0, filesize($dumpFiles[0]));
    }

    public function test_storageバックアップが作成される(): void
    {
        // テスト用ファイルを作成
        $testFile = storage_path('app/backup_test_marker.txt');
        file_put_contents($testFile, 'backup-test');

        $exitCode = Artisan::call('backup:database', [
            '--type' => 'storage',
        ]);

        $this->assertSame(0, $exitCode);

        $storageDirs = glob($this->backupBaseDir.'/backup_*/storage');
        $this->assertNotEmpty($storageDirs, 'ストレージバックアップディレクトリが作成されているべき');

        // マーカーファイルがバックアップに含まれていること
        $backedUp = $storageDirs[0].'/backup_test_marker.txt';
        $this->assertFileExists($backedUp);
        $this->assertSame('backup-test', file_get_contents($backedUp));

        unlink($testFile);
    }

    public function test_compressオプションで_zi_pファイルが作成される(): void
    {
        $exitCode = Artisan::call('backup:database', [
            '--type' => 'database',
            '--compress' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $zips = glob($this->backupBaseDir.'/backup_*.zip');
        $this->assertNotEmpty($zips, '圧縮済みZIPファイルが作成されているべき');

        // ZIPが有効であること
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zips[0]) === true);
        $this->assertGreaterThan(0, $zip->numFiles);
        $zip->close();

        // 元のディレクトリは削除されていること
        $dirs = glob($this->backupBaseDir.'/backup_*');
        $dirs = array_filter($dirs, 'is_dir');
        $this->assertEmpty($dirs, '圧縮後は元のバックアップディレクトリが削除されているべき');
    }

    public function test_retention日数経過したバックアップが削除される(): void
    {
        // 古いバックアップ（保持日数より前）を手動作成
        $oldDate = Carbon::now()->subDays(40)->format('Ymd_His');
        $oldDir = $this->backupBaseDir."/backup_{$oldDate}";
        mkdir($oldDir, 0755, true);
        file_put_contents($oldDir.'/database_dummy.sql', '-- old backup');

        // 新しいバックアップ（保持日数以内）を手動作成
        $newDate = Carbon::now()->subDays(1)->format('Ymd_His');
        $newDir = $this->backupBaseDir."/backup_{$newDate}";
        mkdir($newDir, 0755, true);
        file_put_contents($newDir.'/database_dummy.sql', '-- new backup');

        // 古いZIPも作成
        $oldZipDate = Carbon::now()->subDays(35)->format('Ymd_His');
        $oldZip = $this->backupBaseDir."/backup_{$oldZipDate}.zip";
        file_put_contents($oldZip, 'dummy zip');

        $exitCode = Artisan::call('backup:database', [
            '--type' => 'database',
            '--retention' => '30',
        ]);

        $this->assertSame(0, $exitCode);

        // 古いバックアップは削除されていること
        $this->assertDirectoryDoesNotExist($oldDir);
        $this->assertFileDoesNotExist($oldZip);

        // 新しいバックアップは保持されていること
        $this->assertDirectoryExists($newDir);
    }

    public function test_restoreコマンドのdry_runで実際の復元が行われない(): void
    {
        // 復元元のバックアップを作成
        $backupDate = Carbon::now()->subMinutes(5)->format('Ymd_His');
        $backupDir = $this->backupBaseDir."/backup_{$backupDate}";
        mkdir($backupDir, 0755, true);
        file_put_contents($backupDir.'/database_'.$backupDate.'.sql', "-- dry run test dump\n");
        mkdir($backupDir.'/storage', 0755, true);
        file_put_contents($backupDir.'/storage/dry_run_marker.txt', 'dry-run');

        $exitCode = Artisan::call('backup:restore', [
            'backup_file' => $backupDir,
            '--type' => 'full',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('ドライラン', $output);

        // 復元されていないこと（事前バックアップも作成されていない）
        $preRestore = glob(storage_path('app_pre_restore_*'));
        $this->assertEmpty($preRestore, 'dry-runモードでは事前バックアップが作成されないべき');
    }

    public function test_restoreコマンドで不正なパスはエラーになる(): void
    {
        $exitCode = Artisan::call('backup:restore', [
            'backup_file' => 'nonexistent_backup_12345',
            '--force' => true,
        ]);

        $this->assertSame(1, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('見つかりません', $output);
    }

    public function test_restoreコマンドでsqliteデータベースが復元される(): void
    {
        $dbPath = config('database.connections.sqlite.database');

        // SQLite環境でのみテスト実行
        if (! is_string($dbPath) || ! file_exists($dbPath)) {
            $this->markTestSkipped('SQLiteのファイルDBが存在しないためスキップ');
        }

        // 復元元のバックアップを作成（databaseタイプのみ）
        $exitBackup = Artisan::call('backup:database', [
            '--type' => 'database',
        ]);
        $this->assertSame(0, $exitBackup);

        $backupDirs = glob($this->backupBaseDir.'/backup_*');
        $backupDirs = array_values(array_filter($backupDirs, 'is_dir'));
        $this->assertNotEmpty($backupDirs);

        $exitCode = Artisan::call('backup:restore', [
            'backup_file' => $backupDirs[0],
            '--type' => 'database',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('復元完了', $output);

        // 事前バックアップ（pre_restore）が作成されていること
        $preRestore = glob($dbPath.'.pre_restore_*');
        $this->assertNotEmpty($preRestore, '復元前に既存DBのバックアップが作成されているべき');

        // 復元後もDBが正常に動作すること
        $this->assertDatabaseCount('facilities', Facility::count());
    }
}
