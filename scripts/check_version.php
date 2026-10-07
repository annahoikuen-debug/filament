<?php
require __DIR__ . '/../vendor/autoload.php';
$dompdf = new \Dompdf\Dompdf();
echo get_class($dompdf) . "\n";
echo "FontMetrics class: " . get_class($dompdf->getFontMetrics()) . "\n";