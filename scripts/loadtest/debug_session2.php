<?php
// セッション認証のデバッグ（詳細）
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

echo "Session config:\n";
echo "  driver: " . config('session.driver') . "\n";
echo "  cookie: " . config('session.cookie') . "\n";
echo "  encrypt: " . (config('session.encrypt') ? 'true' : 'false') . "\n";
echo "  lifetime: " . config('session.lifetime') . "\n";

$session = $app->make('session');
$session->start();

$user = User::where('email', 'loadtest_admin0@example.jp')->first();
Auth::login($user);
$session->save();

$sessionId = $session->getId();
$cookieName = config('session.cookie');

echo "\nSession ID: {$sessionId}\n";
echo "Cookie name: {$cookieName}\n";

// セッションファイル内容確認
$sessionFile = storage_path('framework/sessions/' . $sessionId);
$content = file_get_contents($sessionFile);
echo "Session file content (first 200): " . substr($content, 0, 200) . "\n";

// HTTPリクエスト（Locationヘッダー確認）
$ch = curl_init('http://127.0.0.1:8000/admin');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER => true,
    CURLOPT_COOKIE => "{$cookieName}={$sessionId}",
    CURLOPT_USERAGENT => 'LoadTestRunner/1.0',
]);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
curl_close($ch);

echo "\nGET /admin status: {$status}\n";
echo "Response headers:\n{$headers}\n";
