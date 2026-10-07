<?php
require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\FontMetrics;

echo "Noto Sans JPフォントをDomPDFに登録します...\n";

$fontDir = __DIR__ . '/../resources/fonts/noto-sans-jp';
if (!is_dir($fontDir)) {
    echo "フォントディレクトリが存在しません: {$fontDir}\n";
    echo "先に setup_font.php を実行してフォントをダウンロードしてください。\n";
    exit(1);
}

$dompdf = new Dompdf();
$fontMetrics = $dompdf->getFontMetrics();

$fonts = [
    'Regular' => 'NotoSansJP-Regular.ttf',
    'Bold' => 'NotoSansJP-Bold.ttf',
    'Medium' => 'NotoSansJP-Medium.ttf',
];

$fontFamily = 'Noto Sans JP';
$registered = 0;

foreach ($fonts as $weight => $fileName) {
    $fontPath = $fontDir . DIRECTORY_SEPARATOR . $fileName;
    
    if (!file_exists($fontPath)) {
        echo "ファイルが見つかりません: {$fontPath}\n";
        continue;
    }
    
    try {
        // フォントを登録
        $fontMetrics->registerFont(
            $fontFamily,
            $weight === 'Bold' ? 'bold' : 'normal',
            'normal',
            $fontPath
        );
        
        echo "登録完了: {$fontFamily} {$weight} ({$fileName})\n";
        $registered++;
    } catch (\Throwable $e) {
        echo "登録失敗: {$fontFamily} {$weight} - " . $e->getMessage() . "\n";
    }
}

// 登録確認
echo "\n=== 登録後の確認 ===\n";
$fontFamilies = $fontMetrics->getFontFamilies();
$notoFamilies = array_filter($fontFamilies, fn($family) => strpos($family, 'NotoSansJP') === 0, ARRAY_FILTER_USE_KEY);
if (!empty($notoFamilies)) {
    foreach ($notoFamilies as $family => $styles) {
        echo "  {$family}: " . implode(', ', array_keys($styles)) . "\n";
    }
} else {
    echo "  Noto Sans JPフォントは登録されていません。\n";
}

if ($registered > 0) {
    echo "\nフォント登録完了！PDFを再生成してください。\n";
} else {
    echo "\nフォント登録に失敗しました。\n";
}