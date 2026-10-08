<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Dompdf\Dompdf;
use Dompdf\Options;

echo "IPAフォントをDomPDFに登録します...\n";

$fontDir = storage_path('fonts');
if (!is_dir($fontDir)) {
    echo "フォントディレクトリが存在しません: {$fontDir}\n";
    exit(1);
}

$options = new Options();
$options->set('font_dir', $fontDir);
$options->set('font_cache', $fontDir);

$dompdf = new Dompdf($options);
$fontMetrics = $dompdf->getFontMetrics();

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
        $style = [
            'family' => $font['family'],
            'weight' => $font['weight'],
            'style' => $font['style'],
        ];
        
        $url = 'file:///' . str_replace('\\', '/', $fontPath);
        $fontMetrics->registerFont($style, $url);
        
        echo "登録完了: {$font['family']} {$font['weight']} ({$font['src']})\n";
        $registered++;
    } catch (\Throwable $e) {
        echo "登録失敗: {$font['family']} {$font['weight']} - " . $e->getMessage() . "\n";
    }
}

// 登録確認
echo "\n=== 登録後の確認 ===\n";
$fontFamilies = $fontMetrics->getFontFamilies();
echo "登録されたフォントファミリー:\n";
foreach ($fontFamilies as $family => $styles) {
    echo "  {$family}: " . implode(', ', array_keys($styles)) . "\n";
}

if ($registered > 0) {
    echo "\nフォント登録完了！\n";
} else {
    echo "\nフォント登録に失敗しました。\n";
}
