<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\InvoicePdfService;
use ReflectionMethod;
$service = new InvoicePdfService();
$reflection = new ReflectionMethod($service, 'generatePaymentQrCode');
$reflection->setAccessible(true);
$result = $reflection->invoke($service, 'test');
var_dump($result);
echo "Length: " . strlen($result) . "\n";