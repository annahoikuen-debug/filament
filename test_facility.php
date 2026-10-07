<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$facility = new App\Models\Facility;
$facility->setRawAttributes(['id' => 1, 'bank' => '', 'billing' => ''], true);
echo 'Bank: '; 
var_dump($facility->bank);
echo 'Billing: '; 
var_dump($facility->billing);

// Let's also check what the raw attribute is
echo 'Raw bank: ' . var_dump($facility->getAttribute('bank')) . PHP_EOL;
echo 'Raw billing: ' . var_dump($facility->getAttribute('billing')) . PHP_EOL;
?>