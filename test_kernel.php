<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Use the test client to simulate the request
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

$request = \Illuminate\Http\Request::create('/invoices/13000/preview/invoice', 'GET');
$request->headers->set('Accept', 'text/html');

// We need to simulate authentication
$user = \App\Models\User::first();
if ($user) {
    $request->setUserResolver(function () use ($user) {
        return $user;
    });
}

$response = $kernel->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Content-Type: " . $response->headers->get('Content-Type') . "\n";
echo "Body length: " . strlen($response->getContent()) . "\n";

$body = $response->getContent();
if (str_contains($body, 'highlight.js') || str_contains($body, 'hljs')) {
    echo "⚠️ Contains highlight.js\n";
}

// Check for key strings
$checks = [
    '口座振替' => '口座振替',
    '支払期限' => '支払期限',
    '振込先' => '振込先',
    '口座名義' => '口座名義',
    '御 請 求 書' => '御 請 求 書',
    'ご請求先' => 'ご請求先',
    '発行者情報' => '発行者情報',
];

echo "\n";
foreach ($checks as $label => $text) {
    $found = str_contains($body, $text);
    echo ($found ? '✓' : '✗') . " $label ($text)\n";
}