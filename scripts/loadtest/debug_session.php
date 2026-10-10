<?php
// セッションベース認証のテスト
// LaravelのセッションをCLIで生成し、HTTPリクエストで再利用する

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

// セッションを開始
$session = $app->make('session');
$session->start();

// ユーザーをログイン
$user = User::where('email', 'loadtest_admin0@example.jp')->first();
if (! $user) {
    echo "User not found\n";
    exit(1);
}

Auth::login($user);

// セッションを保存
$session->save();

$sessionId = $session->getId();
echo "Session ID: {$sessionId}\n";
echo "Session file: " . storage_path('framework/sessions/' . $sessionId) . "\n";
echo "Session file exists: " . (file_exists(storage_path('framework/sessions/' . $sessionId)) ? 'YES' : 'NO') . "\n";

// セッション内容確認
$data = $session->all();
echo "Session keys: " . implode(', ', array_keys($data)) . "\n";

// HTTPリクエストでこのセッションIDを使って認証済みページにアクセス
$ch = curl_init('http://127.0.0.1:8000/admin');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_COOKIE => "laravel_session={$sessionId}",
    CURLOPT_USERAGENT => 'LoadTestRunner/1.0',
]);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($response, $headerSize);
curl_close($ch);

echo "\nGET /admin with session cookie:\n";
echo "Status: {$status}\n";
echo "Body length: " . strlen($body) . "\n";

if (str_contains($body, 'ダッシュボード') || str_contains($body, '請求管理') || $status === 200) {
    echo "AUTH SUCCESS: 認証済みページにアクセスできました\n";
} else {
    echo "AUTH FAILED: ログインページにリダイレクトされた可能性\n";
    echo "Body (first 300): " . substr($body, 0, 300) . "\n";
}
