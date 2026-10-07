<?php
require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;

$pdfPath = __DIR__ . '/../storage/app/sample_invoice.pdf';
if (!file_exists($pdfPath)) {
    echo "PDF not found: $pdfPath\n";
    exit(1);
}

$dompdf = new Dompdf();
$dompdf->loadFile($pdfPath);

// PDFのテキストを抽出して確認
$canvas = $dompdf->getCanvas();
$text = $canvas->get_text();

echo "=== PDFから抽出されたテキスト（最初の500文字）===\n";
echo substr($text, 0, 500) . "\n\n";

echo "=== 日本語チェック ===\n";
echo "請求書: " . (strpos($text, '請求書') !== false ? 'YES' : 'NO') . "\n";
echo "ご請求金額: " . (strpos($text, 'ご請求金額') !== false ? 'YES' : 'NO') . "\n";
echo "佐藤: " . (strpos($text, '佐藤') !== false ? 'YES' : 'NO') . "\n";
echo "一郎: " . (strpos($text, '一郎') !== false ? 'YES' : 'NO') . "\n";
echo "号室: " . (strpos($text, '号室') !== false ? 'YES' : 'NO') . "\n";