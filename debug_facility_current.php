<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$configService = app(App\Services\FacilityConfigService::class);
$facility = $configService->getFacility();
if ($facility) {
    echo 'Found facility: ' . $facility->id . PHP_EOL;
    echo 'Bank raw: ' . var_dump($facility->getAttribute('bank')) . PHP_EOL;
    echo 'Bank attribute: ' . var_dump($facility->bank) . PHP_EOL;
    
    // Let's check the validation logic manually
    $facilityConfig = $configService->getConfig();
    if (!empty($facilityConfig['bank'])) {
        $bank = $facilityConfig['bank'];
        echo 'Config bank: ' . var_dump($bank) . PHP_EOL;
        if (empty($bank['name']) || empty($bank['account_number']) || empty($bank['account_holder'])) {
            echo 'Validation would fail: missing required bank fields' . PHP_EOL;
            echo 'Name: ' . var_dump($bank['name'] ?? 'NULL') . PHP_EOL;
            echo 'Account number: ' . var_dump($bank['account_number'] ?? 'NULL') . PHP_EOL;
            echo 'Account holder: ' . var_dump($bank['account_holder'] ?? 'NULL') . PHP_EOL;
        } else {
            echo 'Validation would pass' . PHP_EOL;
        }
    } else {
        echo 'Config bank is empty' . PHP_EOL;
    }
} else {
    echo 'No facility found' . PHP_EOL;
}
?>