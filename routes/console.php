<?php

use App\Services\InvoicePdfService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pdf:install-fonts {--force : 既存フォントを上書きする}', function () {
    $pdfService = app(InvoicePdfService::class);

    $this->info('Noto Sans JP フォントのインストールを開始します...');

    $results = $pdfService->installNotoSansJpFonts();

    $this->newLine();
    $this->table(['ウェイト', '結果'], array_map(fn ($weight, $result) => [$weight, $result], array_keys($results), $results));

    $this->newLine();

    $installed = collect($results)->filter(fn ($r) => $r === 'installed')->count();
    $alreadyExists = collect($results)->filter(fn ($r) => $r === 'already_exists')->count();
    $failed = collect($results)->filter(fn ($r) => str_starts_with($r, 'error') || $r === 'download_failed')->count();

    if ($installed > 0) {
        $this->info("{$installed} 個のフォントを新規インストールしました。");
    }
    if ($alreadyExists > 0) {
        $this->info("{$alreadyExists} 個のフォントは既に存在します。");
    }
    if ($failed > 0) {
        $this->error("{$failed} 個のフォントのインストールに失敗しました。");

        return 1;
    }

    $this->info('フォントキャッシュをクリアしました。');
    $this->info('インストール完了！');

    return 0;
})->purpose('Noto Sans JP フォントをダウンロードしてPDF生成用にインストールします');
