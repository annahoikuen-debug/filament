<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\File;

echo "Noto Sans JPフォントのインストールを開始します...\n";

$pdfService = app(InvoicePdfService::class);
$results = $pdfService->installNotoSansJpFonts();

foreach ($results as $weight => $result) {
    echo "{$weight}: {$result}\n";
}

// フォントキャッシュをクリアするための公開メソッドを呼び出す
// ただし、clearFontCacheはプライベートメソッドなので、代わりに
// インストール結果を返すだけにするか、公開ラッパーメソッドを使う
// ここでは、インストールメソッド内でキャッシュクリアが行われるので
// 特に何もする必要はない
echo "フォントキャッシュはインストールプロセス内でクリアされました。\n";

// インストール結果のサマリー
$installedCount = 0;
$alreadyExistsCount = 0;
$failedCount = 0;

foreach ($results as $result) {
    if ($result === 'installed') {
        $installedCount++;
    } elseif ($result === 'already_exists') {
        $alreadyExistsCount++;
    } else {
        $failedCount++;
    }
}

echo "\nインストール完了:\n";
echo "- 新規インストール: {$installedCount} ファイル\n";
echo "- 既存ファイル: {$alreadyExistsCount} ファイル\n";
echo "- ダウンロード失敗: {$failedCount} ファイル\n";

if ($failedCount === 0 && $installedCount > 0) {
    echo "\nすべてのフォントが正常にインストールされました。\n";
} elseif ($failedCount > 0 && $installedCount === 0 && $alreadyExistsCount > 0) {
    echo "\nすべてのフォントは既にインストールされています。\n";
} elseif ($failedCount > 0) {
    echo "\n一部またはすべてのフォントのダウンロードに失敗しました。\n";
    echo "ネットワーク接続を確認してください。\n";
} else {
     echo "\nフォントインストールプロセスが完了しました。\n";
}