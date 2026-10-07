<?php
require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

$dompdf = new Dompdf();
$canvas = $dompdf->getCanvas();
$fontMetrics = $dompdf->getFontMetrics();

// Try registering Windows MS Gothic or Meiryo
// Or check font registration via FontMetrics
echo "Testing font registration...\n";
