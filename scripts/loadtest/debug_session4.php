<?php
// 正しいセッションクッキー形式での認証テスト
// 形式: encrypter->encrypt(CookieValuePrefix::create(name, key) . sessionId, false)
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;

$session = $app->make('session');
$session->start();

$user = User::where('email', 'loadtest_admin0@example.jp')->first();
Auth::login($user);
$session->save();

$sessionId = $session->getId();
$cookieName = config('session.cookie');

// 正しい形式でクッキー値を生成
$encrypter = $app->make(Illuminate\Contracts\Encryption\Encrypter::class);
$key = $encrypter->getKey();
$prefix = CookieValuePrefix::create($cookieName, $key);
$cookieValue = $encrypter->encrypt($prefix . $sessionId, false);

echo "Session ID: {$sessionId}\n";
echo "Cookie name: {$cookieName}\n";
echo "Prefix (first 20): " . substr($prefix, 0, 20) . "...\n";
echo "Cookie value (first 50): " . substr($cookieValue, 0, 50) . "...\n";

// HTTPリクエスト
$ch = curl_init('http://127.0.0.1:8000/admin');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER => true,
    CURLOPT_COOKIE => "{$cookieName}={$cookieValue}",
    CURLOPT_USERAGENT => 'LoadTestRunner/1.0',
]);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
curl_close($ch);

echo "\nGET /admin status: {$status}\n";

if ($status === 200) {
    echo "AUTH SUCCESS!\n";
    if (preg_match('/<title>([^<]+)<\/title>/', $body, $m)) {
        echo "Page title: " . trim($m[1]) . "\n";
    }
    echo "Body length: " . strlen($body) . "\n";
} else {
    echo "AUTH FAILED\n";
    echo "Headers:\n{$headers}\n";
}
