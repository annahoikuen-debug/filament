<?php
// 複数ユーザーのセッション生成テスト
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;

function createAuthCookie($app, string $email): ?array
{
    $session = $app->make('session');
    $session->start();

    $user = User::where('email', $email)->first();
    if (! $user) {
        return null;
    }

    Auth::login($user);
    $session->save();

    $sessionId = $session->getId();
    $cookieName = config('session.cookie');
    $encrypter = $app->make(Illuminate\Contracts\Encryption\Encrypter::class);
    $key = $encrypter->getKey();
    $prefix = CookieValuePrefix::create($cookieName, $key);
    $cookieValue = $encrypter->encrypt($prefix . $sessionId, false);

    return [
        'session_id' => $sessionId,
        'cookie' => $cookieName . '=' . $cookieValue,
        'email' => $email,
    ];
}

function verifyAuth(string $baseUrl, string $cookie): array
{
    $ch = curl_init($baseUrl . '/admin');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIE => $cookie,
        CURLOPT_USERAGENT => 'LoadTestRunner/1.0',
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status];
}

$baseUrl = 'http://127.0.0.1:8000';

// 5ユーザー分のセッションを生成
$cookies = [];
for ($i = 0; $i < 5; $i++) {
    $email = "loadtest_admin{$i}@example.jp";
    $result = createAuthCookie($app, $email);
    if ($result) {
        $cookies[] = $result;
        echo "Created session for {$email}: {$result['session_id']}\n";
    } else {
        echo "FAILED to create session for {$email}\n";
    }
}

// 各セッションで認証検証
echo "\n=== Verification ===\n";
foreach ($cookies as $c) {
    $result = verifyAuth($baseUrl, $c['cookie']);
    echo "{$c['email']}: status={$result['status']} " . ($result['status'] === 200 ? 'OK' : 'FAIL') . "\n";
}
