<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$facility = App\Models\Facility::find(2);
if ($facility) {
    echo 'Facility ID: ' . $facility->id . PHP_EOL;
    echo 'Bank raw (getAttribute): ' . var_dump($facility->getAttribute('bank')) . PHP_EOL;
    echo 'Bank attribute: ' . var_dump($facility->bank) . PHP_EOL;
    echo 'Is bank an array? ' . var_dump(is_array($facility->bank)) . PHP_EOL;
    
    echo 'Billing raw (getAttribute): ' . var_dump($facility->getAttribute('billing')) . PHP_EOL;
    echo 'Billing attribute: ' . var_dump($facility->billing) . PHP_EOL;
    echo 'Is billing an array? ' . var_dump(is_array($facility->billing)) . PHP_EOL;
    
    // Test accessing nested values
    if (is_array($facility->bank)) {
        echo 'Bank name: ' . $facility->bank['name'] ?? 'NULL' . PHP_EOL;
        echo 'Bank account_number: ' . $facility->bank['account_number'] ?? 'NULL' . PHP_EOL;
    }
} else {
    echo 'No facility found' . PHP_EOL;
}
?>