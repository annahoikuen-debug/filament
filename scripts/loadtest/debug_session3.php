<?php
// 暗号化セッションクッキーのテスト
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

$session = $app->make('session');
$session->start();

$user = User::where('email', 'loadtest_admin0@example.jp')->first();
Auth::login($user);
$session->save();

$sessionId = $session->getId();
$cookieName = config('session.cookie');

// 暗号化クッキー値を生成（EncryptCookies ミドルウェアと同様）
$encrypter = $app->make(Illuminate\Contracts\Encryption\Encrypter::class);
$encryptedValue = $encrypter->encrypt($sessionId, false);

echo "Session ID: {$sessionId}\n";
echo "Cookie name: {$cookieName}\n";
echo "Encrypted value (first 50): " . substr($encryptedValue, 0, 50) . "...\n";

// HTTPリクエスト
$ch = curl_init('http://127.0.0.1:8000/admin');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER => true,
    CURLOPT_COOKIE => "{$cookieName}={$encryptedValue}",
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
    echo "Body length: " . strlen($body) . "\n";
    if (preg_match('/<title>([^<]+)<\/title>/', $body, $m)) {
        echo "Page title: " . $m[1] . "\n";
    }
} else {
    echo "AUTH FAILED\n";
    echo "Headers:\n{$headers}\n";
}
