<?php
// ログインフローのデバッグ
require __DIR__ . '/LoadFramework.php';

use LoadTest\CurlClient;

$baseUrl = 'http://127.0.0.1:8000';

$client = new CurlClient($baseUrl);

// 1. ログインページ取得
$client->get('/admin/login');
echo "GET /admin/login status: " . $client->getLastStatus() . "\n";
$body = $client->getLastBody();
echo "Body length: " . strlen($body) . "\n";

// _token 検索
if (preg_match('/name="_token"[^>]*value="([^"]+)"/', $body, $m)) {
    echo "CSRF token (name=_token): " . substr($m[1], 0, 20) . "...\n";
} else {
    echo "CSRF token (name=_token): NOT FOUND\n";
}

// filament の csrf フィールド検索
if (preg_match('/csrf[^>]*value="([^"]+)"/i', $body, $m)) {
    echo "CSRF (csrf): " . substr($m[1], 0, 20) . "...\n";
}

// フォームの全 hidden フィールド
preg_match_all('/<input[^>]*type="hidden"[^>]*>/i', $body, $hidden);
echo "Hidden fields: " . count($hidden[0]) . "\n";
foreach ($hidden[0] as $h) {
    echo "  " . substr($h, 0, 150) . "\n";
}

// form action 検索
if (preg_match('/<form[^>]*action="([^"]*)"[^>]*>/i', $body, $m)) {
    echo "Form action: " . $m[1] . "\n";
}

// 2. ログイン実行
$token = $client->getLastCsrfToken();
echo "\nAttempting login with token: " . ($token ? substr($token, 0, 20) . '...' : 'NULL') . "\n";

$client->post('/admin/login', [
    '_token' => $token,
    'email' => 'loadtest_admin0@example.jp',
    'password' => 'loadtest-password',
]);

echo "POST /admin/login status: " . $client->getLastStatus() . "\n";
echo "Response headers:\n";
foreach ($client->getLastBody() ? [] : [] as $h) {}

// リダイレクト先確認
$loginBody = $client->getLastBody();
echo "Response body (first 500): " . substr($loginBody, 0, 500) . "\n";
