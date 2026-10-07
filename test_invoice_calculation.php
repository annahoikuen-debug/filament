<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$facility = \App\Models\Facility::first();
echo 'Facility ID: ' . $facility->id . PHP_EOL;
$service = new \App\Services\InvoiceCalculationService;
$result = $service->generateForMonth('2026-10', false, $facility->id);
print_r($result);
?>