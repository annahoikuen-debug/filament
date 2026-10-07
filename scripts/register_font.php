<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\InvoicePdfService;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;

echo "フォント登録状況を確認しています...\n";

$pdfService = app(InvoicePdfService::class);
$dompdf = new Dompdf();
$fontMetrics = $dompdf->getFontMetrics();

// 現在登録されているフォントファミリーを表示
$fontFamilies = $fontMetrics->getFontFamilies();
echo "現在登録されているフォントファミリー:\n";
foreach ($fontFamilies as $family => $styles) {
    echo "  {$family}: " . implode(', ', array_keys($styles)) . "\n";
}

echo "\nNoto Sans JPフォントの確認:\n";
$notoFamilies = array_filter($fontFamilies, fn($family) => strpos($family, 'NotoSansJP') === 0, ARRAY_FILTER_USE_KEY);
if (!empty($notoFamilies)) {
    foreach ($notoFamilies as $family => $styles) {
        echo "  {$family}: " . implode(', ', array_keys($styles)) . "\n";
    }
} else {
    echo "  Noto Sans JPフォントはまだ登録されていません。\n";
    echo "  setup_font.php を実行してフォントをインストールしてください。\n";
}

// IPAフォントの確認（従来のフォント）
echo "\nIPAフォントの確認:\n";
$ipaFamilies = array_filter($fontFamilies, fn($family) => strpos($family, 'ipaexg') === 0 || strpos($family, 'ipag') === 0, ARRAY_FILTER_USE_KEY);
if (!empty($ipaFamilies)) {
    foreach ($ipaFamilies as $family => $styles) {
        echo "  {$family}: " . implode(', ', array_keys($styles)) . "\n";
    }
} else {
    echo "  IPAフォントは登録されていません。\n";
}

// Windows系フォントの確認
echo "\nWindows系フォントの確認:\n";
$winFamilies = array_filter($fontFamilies, fn($family) => 
    strpos($family, 'YuGothic') === 0 || 
    strpos($family, 'YuMincho') === 0 || 
    strpos($family, 'Meiryo') === 0 ||
    strpos($family, 'MS Gothic') === 0 ||
    strpos($family, 'MS Mincho') === 0, 
ARRAY_FILTER_USE_KEY);
if (!empty($winFamilies)) {
    foreach ($winFamilies as $family => $styles) {
        echo "  {$family}: " . implode(', ', array_keys($styles)) . "\n";
    }
} else {
    echo "  Windows系フォントは登録されていません（環境依存）。\n";
}