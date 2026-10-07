<?php
require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\FontMetrics;

echo "IPAフォントをDomPDFに登録します...\n";

$fontDir = __DIR__ . '/../storage/fonts';
if (!is_dir($fontDir)) {
    echo "フォントディレクトリが存在しません: {$fontDir}\n";
    exit(1);
}

$dompdf = new Dompdf();
$fontMetrics = $dompdf->getFontMetrics();

// IPAexゴシックを登録
$fonts = [
    [
        'family' => 'ipaexg',
        'weight' => 'normal',
        'style' => 'normal',
        'src' => 'ipaexg_normal_0ec7c40eaabbd5656858c88c69d1e606.ttf',
    ],
    [
        'family' => 'ipaexg',
        'weight' => 'bold',
        'style' => 'normal',
        'src' => 'ipaexg_bold_0ec7c40eaabbd5656858c88c69d1e606.ttf',
    ],
];

$registered = 0;

foreach ($fonts as $font) {
    $fontPath = $fontDir . DIRECTORY_SEPARATOR . $font['src'];
    
    if (!file_exists($fontPath)) {
        echo "ファイルが見つかりません: {$fontPath}\n";
        continue;
    }
    
    try {
        // フォントを登録（正しいキー名で）
        $style = [
            'family' => $font['family'],
            'weight' => $font['weight'],
            'style' => $font['style'],
        ];
        
        $fontMetrics->registerFont($style, $fontPath);
        
        echo "登録完了: {$font['family']} {$font['weight']} ({$font['src']})\n";
        $registered++;
    } catch (\Throwable $e) {
        echo "登録失敗: {$font['family']} {$font['weight']} - " . $e->getMessage() . "\n";
    }
}

// 登録確認
echo "\n=== 登録後の確認 ===\n";
$fontFamilies = $fontMetrics->getFontFamilies();
echo "全フォントファミリー:\n";
foreach ($fontFamilies as $family => $styles) {
    echo "  {$family}: " . implode(', ', array_keys($styles)) . "\n";
}

if ($registered > 0) {
    echo "\nフォント登録完了！\n";
    echo "注意: テンプレートの font-family に 'ipaexg' を追加してください。\n";
} else {
    echo "\nフォント登録に失敗しました。\n";
}