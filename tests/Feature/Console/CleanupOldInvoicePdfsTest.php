<?php

use App\Console\Commands\CleanupOldInvoicePdfs;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // テスト用ディレクトリ構造を作成
    $this->invoicesDir = storage_path('app/invoices');
    File::ensureDirectoryExists($this->invoicesDir.'/2099-01');
    File::put($this->invoicesDir.'/2099-01/invoice_1.pdf', str_repeat('x', 100));
});

afterEach(function () {
    File::deleteDirectory($this->invoicesDir.'/2099-01');
    File::deleteDirectory($this->invoicesDir.'/2020-01');
});

test('古いPDFディレクトリを削除すること', function () {
    // 過去のディレクトリを作成（2020-01は5年以上前）
    File::ensureDirectoryExists($this->invoicesDir.'/2020-01');
    File::put($this->invoicesDir.'/2020-01/invoice_old.pdf', str_repeat('x', 2048));

    $this->artisan(CleanupOldInvoicePdfs::class, ['--years' => 5])
        ->expectsOutputToContain('クリーンアップ完了')
        ->assertSuccessful();

    expect(File::exists($this->invoicesDir.'/2020-01'))->toBeFalse()
        ->and(File::exists($this->invoicesDir.'/2099-01'))->toBeTrue();
});

test('dry-runモードでは削除しないこと', function () {
    File::ensureDirectoryExists($this->invoicesDir.'/2020-01');
    File::put($this->invoicesDir.'/2020-01/invoice_old.pdf', str_repeat('x', 2048));

    $this->artisan(CleanupOldInvoicePdfs::class, ['--years' => 5, '--dry-run' => true])
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();

    expect(File::exists($this->invoicesDir.'/2020-01'))->toBeTrue();
});

test('ディレクトリが存在しない場合は成功すること', function () {
    File::deleteDirectory($this->invoicesDir);

    $this->artisan(CleanupOldInvoicePdfs::class)
        ->expectsOutputToContain('請求書ディレクトリが存在しません')
        ->assertSuccessful();

    // 後続テストのため再作成
    File::ensureDirectoryExists($this->invoicesDir.'/2099-01');
    File::put($this->invoicesDir.'/2099-01/invoice_1.pdf', str_repeat('x', 100));
});

test('不正な形式のディレクトリはスキップすること', function () {
    File::ensureDirectoryExists($this->invoicesDir.'/invalid-dir');

    $this->artisan(CleanupOldInvoicePdfs::class, ['--years' => 5, '--dry-run' => true])
        ->expectsOutputToContain('スキップ (不正な形式): invalid-dir')
        ->assertSuccessful();

    expect(File::exists($this->invoicesDir.'/invalid-dir'))->toBeTrue();
    File::deleteDirectory($this->invoicesDir.'/invalid-dir');
});
