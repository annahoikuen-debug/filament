<?php
// セッションファイル直接生成方式のテスト
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

function createAuthCookie($app, string $email): ?array
{
    $user = User::where('email', $email)->first();
    if (! $user) {
        return null;
    }

    // セッションID生成
    $sessionId = Str::random(40);

    // ガードキー取得（login_web_{hash}）
    $guard = Auth::guard('web');
    $guardKey = $guard->getName();

    // セッションデータ構築（Laravelのセッション形式）
    $data = serialize([
        '_token' => Str::random(40),
        $guardKey => $user->id,
        '_flash' => ['old' => [], 'new' => []],
    ]);

    // セッションファイルに直接書き込み
    $sessionPath = storage_path('framework/sessions/' . $sessionId);
    file_put_contents($sessionPath, $data);

    // 暗号化クッキー値を生成
    $cookieName = config('session.cookie');
    $encrypter = $app->make(Illuminate\Contracts\Encryption\Encrypter::class);
    $key = $encrypter->getKey();
    $prefix = CookieValuePrefix::create($cookieName, $key);
    $cookieValue = $encrypter->encrypt($prefix . $sessionId, false);

    return [
        'session_id' => $sessionId,
        'cookie' => $cookieName . '=' . $cookieValue,
        'email' => $email,
        'guard_key' => $guardKey,
    ];
}

function verifyAuth(string $baseUrl, string $cookie): int
{
    $ch = curl_init($baseUrl . '/admin');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIE => $cookie,
        CURLOPT_USERAGENT => 'LoadTestRunner/1.0',
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status;
}

$baseUrl = 'http://127.0.0.1:8000';

echo "=== セッションファイル直接生成テスト ===\n";

$cookies = [];
for ($i = 0; $i < 5; $i++) {
    $email = "loadtest_admin{$i}@example.jp";
    $result = createAuthCookie($app, $email);
    if ($result) {
        $cookies[] = $result;
        echo "Created: {$email} (session: {$result['session_id']})\n";
        echo "  guard_key: {$result['guard_key']}\n";
    } else {
        echo "FAILED: {$email}\n";
    }
}

echo "\n=== Verification ===\n";
$ok = 0;
foreach ($cookies as $c) {
    $status = verifyAuth($baseUrl, $c['cookie']);
    $result = $status === 200 ? 'OK' : 'FAIL';
    if ($status === 200) $ok++;
    echo "{$c['email']}: status={$status} {$result}\n";
}

echo "\n成功: {$ok}/" . count($cookies) . "\n";
