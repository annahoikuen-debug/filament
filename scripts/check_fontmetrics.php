<?php
require __DIR__ . '/../vendor/autoload.php';
$dompdf = new \Dompdf\Dompdf();
$fontMetrics = $dompdf->getFontMetrics();

// Check registerFont method signature
$reflection = new ReflectionMethod($fontMetrics, 'registerFont');
echo "registerFont signature:\n";
echo $reflection . "\n\n";

// Check getFontFamilies
$reflection2 = new ReflectionMethod($fontMetrics, 'getFontFamilies');
echo "getFontFamilies signature:\n";
echo $reflection2 . "\n";