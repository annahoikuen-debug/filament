<?php

use App\Console\Commands\InstallPdfFonts;
use App\Services\InvoicePdfService;

test('フォントインストール結果に応じて出力すること（installed）', function () {
    $this->mock(InvoicePdfService::class, function ($mock) {
        $mock->shouldReceive('installNotoSansJpFonts')->once()->andReturn([
            'Regular' => 'installed',
            'Bold' => 'already_exists',
        ]);
    });

    $this->artisan(InstallPdfFonts::class)
        ->expectsOutputToContain('1 個のフォントを新規インストールしました。')
        ->expectsOutputToContain('1 個のフォントは既に存在します。')
        ->expectsOutputToContain('インストール完了！')
        ->assertSuccessful();
});

test('フォントインストール失敗時はFAILUREを返すこと', function () {
    $this->mock(InvoicePdfService::class, function ($mock) {
        $mock->shouldReceive('installNotoSansJpFonts')->once()->andReturn([
            'Regular' => 'download_failed',
            'Bold' => 'error: timeout',
        ]);
    });

    $this->artisan(InstallPdfFonts::class)
        ->expectsOutputToContain('2 個のフォントのインストールに失敗しました。')
        ->assertExitCode(1);
});
