<?php
// Disable the AppServiceProvider for this test by not booting the application
require __DIR__.'/vendor/autoload.php';

$database = new Illuminate\Database\Capsule\Manager;
$database->addConnection([
    'driver' => 'sqlite',
    'database' => __DIR__.'/database/database.sqlite',
    'prefix' => '',
]);

$database->setAsGlobal();
$database->bootEloquent();

use App\Models\Facility;

$facility = Facility::first();
if ($facility) {
    echo 'Facility ID: ' . $facility->id . PHP_EOL;
    echo 'Bank raw (getAttribute): ' . var_dump($facility->getAttribute('bank')) . PHP_EOL;
    echo 'Bank attribute: ' . var_dump($facility->bank) . PHP_EOL;
    
    // Let's also check what happens when we convert to array
    echo 'Bank as array: ' . var_dump((array) $facility->bank) . PHP_EOL;
    
    // Test the mutator directly
    echo 'Testing bank mutator get:' . PHP_EOL;
    $bankValue = $facility->getAttribute('bank');
    echo 'Raw bank value: ' . var_dump($bankValue) . PHP_EOL;
    
    // The bank attribute should return the processed value
    $processedBank = $facility->bank;
    echo 'Processed bank: ' . var_dump($processedBank) . PHP_EOL;
} else {
    echo 'No facility found' . PHP_EOL;
}
?>