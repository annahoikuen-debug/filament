<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->setChroot([base_path()]);
$fontDir = storage_path('fonts');
$options->set('fontDir', $fontDir);
$options->set('fontCache', $fontDir);

$dompdf = new Dompdf($options);
$fontMetrics = $dompdf->getFontMetrics();

$fontFile = resource_path('fonts/ipaexg.ttf');
$fontUrl = 'file://' . $fontFile;

echo "Calling registerFont with: $fontUrl\n";
$res1 = $fontMetrics->registerFont([
    'family' => 'ipaexg',
    'weight' => 'normal',
    'style' => 'normal',
], $fontUrl);

$res2 = $fontMetrics->registerFont([
    'family' => 'ipaexg',
    'weight' => 'bold',
    'style' => 'normal',
], $fontUrl);

var_dump($res1, $res2);
