<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$configService = app(App\Services\FacilityConfigService::class);
$facility = $configService->getFacility();
if ($facility) {
    echo 'Facility ID: ' . $facility->id . PHP_EOL;
    echo 'Bank raw: ' . var_dump($facility->getAttribute('bank')) . PHP_EOL;
    echo 'Bank attribute: ' . var_dump($facility->bank) . PHP_EOL;
    echo 'Billing raw: ' . var_dump($facility->getAttribute('billing')) . PHP_EOL;
    echo 'Billing attribute: ' . var_dump($facility->billing) . PHP_EOL;
}

// Let's also check what FacilityConfigService returns
echo 'Config facility: ' . var_dump($configService->getConfig()) . PHP_EOL;
?>