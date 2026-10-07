<?php
require __DIR__ . '/../vendor/autoload.php';
$dompdf = new \Dompdf\Dompdf();
$fm = $dompdf->getFontMetrics();
print_r(array_keys($fm->getFontFamilies()));
