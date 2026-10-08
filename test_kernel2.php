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

// Just get first 5000 chars to see the error
$body = $response->getContent();
echo substr($body, 0, 5000) . "\n...\n[TRUNCATED]";