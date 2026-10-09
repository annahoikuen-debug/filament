<?php

/**
 * コーポレートサイトの資料PDF（カタログ・診断書フォーム）を生成する
 * 使い方: php scripts/generate_documents.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Barryvdh\DomPDF\Facade\Pdf;

$dir = storage_path('app/documents');
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$documents = [
    'catalog.pdf' => 'documents.catalog',
    'diagnosis-form.pdf' => 'documents.diagnosis-form',
];

foreach ($documents as $filename => $view) {
    $pdf = Pdf::loadView($view, []);
    file_put_contents($dir . DIRECTORY_SEPARATOR . $filename, $pdf->output());
    echo "Generated: {$filename} (" . filesize($dir . DIRECTORY_SEPARATOR . $filename) . " bytes)" . PHP_EOL;
}

echo 'DONE' . PHP_EOL;
