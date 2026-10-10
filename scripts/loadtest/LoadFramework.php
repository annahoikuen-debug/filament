<?php
/**
 * 負荷テストフレームワーク（k6代替）
 *
 * curl_multi ベースの並行HTTPクライアント + メトリクス収集
 * 計画書 §4 のツール選定（k6）が利用できない環境での代替実装
 *
 * 注意: php -S（PHP組み込みサーバ）はシングルスレッドのため、
 * 並行リクエストはサーバ側で直列キューイングされる。
 * 計測値はキューイング下のレイテンシを反映する。
 */

namespace LoadTest;

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * 認証済みセッションクッキーを生成する
 * （Filament v3 は Livewire ベースのログインフォームのため、
 *  通常のPOST認証が使えない。セッションファイルを直接生成する）
 */
class SessionFactory
{
    private $app;
    private string $baseUrl;

    public function __construct($app, string $baseUrl)
    {
        $this->app = $app;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * 認証済みセッションのクッキー文字列とCSRFトークンを生成する
     *
     * @return array{cookie: string, token: string, session_id: string}|null
     */
    public function createAuthCookie(string $email): ?array
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            return null;
        }

        $sessionId = Str::random(40);
        $csrfToken = Str::random(40);

        // ガードキー（login_web_{hash}）
        $guardKey = Auth::guard('web')->getName();

        // セッションデータ（Laravelのセッション形式）
        $data = serialize([
            '_token' => $csrfToken,
            $guardKey => $user->id,
            '_flash' => ['old' => [], 'new' => []],
        ]);

        // セッションファイルに直接書き込み
        $sessionPath = storage_path('framework/sessions/' . $sessionId);
        file_put_contents($sessionPath, $data);

        // 暗号化クッキー値を生成（EncryptCookies ミドルウェアと同形式）
        $cookieName = config('session.cookie');
        $encrypter = $this->app->make(Encrypter::class);
        $key = $encrypter->getKey();
        $prefix = CookieValuePrefix::create($cookieName, $key);
        $cookieValue = $encrypter->encrypt($prefix . $sessionId, false);

        return [
            'session_id' => $sessionId,
            'cookie' => $cookieName . '=' . $cookieValue,
            'token' => $csrfToken,
        ];
    }

    /**
     * 複数ユーザーの認証クッキーを生成する
     *
     * @return array<int, array{cookie: string, token: string, session_id: string}>
     */
    public function createAuthCookies(array $emails): array
    {
        $cookies = [];
        foreach ($emails as $email) {
            $result = $this->createAuthCookie($email);
            if ($result) {
                $cookies[] = $result;
            }
        }
        return $cookies;
    }
}

class CurlClient
{
    private string $baseUrl;
    private ?string $cookie;
    private ?string $lastCsrfToken = null;
    private int $lastStatus = 0;
    private float $lastTime = 0.0;
    private string $lastBody = '';

    public function __construct(string $baseUrl, ?string $cookie = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookie = $cookie;
    }

    public function setCookie(?string $cookie): self
    {
        $this->cookie = $cookie;
        return $this;
    }

    public function getCookie(): ?string
    {
        return $this->cookie;
    }

    public function getLastStatus(): int
    {
        return $this->lastStatus;
    }

    public function getLastTime(): float
    {
        return $this->lastTime;
    }

    public function getLastBody(): string
    {
        return $this->lastBody;
    }

    public function getLastCsrfToken(): ?string
    {
        return $this->lastCsrfToken;
    }

    public function get(string $path, array $headers = []): self
    {
        return $this->request('GET', $path, null, [], $headers);
    }

    public function post(string $path, $data, array $headers = []): self
    {
        return $this->request('POST', $path, $data, [], $headers);
    }

    public function request(string $method, string $path, $data = null, array $query = [], array $headers = []): self
    {
        $url = $this->baseUrl . $path;
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $ch = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HEADER => true,
            CURLOPT_USERAGENT => 'LoadTestRunner/1.0',
        ];

        if ($this->cookie) {
            $options[CURLOPT_COOKIE] = $this->cookie;
        }

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if (is_array($data) && isset($data['_json'])) {
                $options[CURLOPT_POSTFIELDS] = $data['_json'];
                $headers[] = 'Content-Type: application/json';
            } elseif (is_array($data)) {
                $options[CURLOPT_POSTFIELDS] = http_build_query($data);
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            } elseif ($data !== null) {
                $options[CURLOPT_POSTFIELDS] = $data;
            }
        }

        if ($headers) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $options);

        $start = microtime(true);
        $response = curl_exec($ch);
        $this->lastTime = microtime(true) - $start;

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->lastStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->lastBody = substr((string) $response, $headerSize);

        if (preg_match('/name="_token"[^>]*value="([^"]+)"/', $this->lastBody, $matches)) {
            $this->lastCsrfToken = $matches[1];
        }

        curl_close($ch);

        return $this;
    }
}

class Metrics
{
    /** @var float[] レスポンスタイム（秒） */
    public array $times = [];
    /** @var array<int, int> ステータスコード => 件数 */
    public array $statuses = [];
    /** @var string[] エラーメッセージ */
    public array $errors = [];
    public int $total = 0;
    public float $wallTime = 0.0;

    public function add(float $time, int $status, ?string $error = null): void
    {
        $this->total++;
        $this->times[] = $time;
        $this->statuses[$status] = ($this->statuses[$status] ?? 0) + 1;
        if ($error !== null) {
            $this->errors[] = $error;
        }
    }

    public function percentile(float $p): float
    {
        if (empty($this->times)) {
            return 0.0;
        }
        $sorted = $this->times;
        sort($sorted);
        $index = (int) ceil(($p / 100) * count($sorted)) - 1;
        $index = max(0, min($index, count($sorted) - 1));
        return $sorted[$index];
    }

    public function rps(): float
    {
        return $this->wallTime > 0 ? $this->total / $this->wallTime : 0.0;
    }

    public function errorRate(): float
    {
        return $this->total > 0 ? count($this->errors) / $this->total : 0.0;
    }

    public function summary(): array
    {
        return [
            'total' => $this->total,
            'rps' => round($this->rps(), 2),
            'p50_ms' => round($this->percentile(50) * 1000, 1),
            'p95_ms' => round($this->percentile(95) * 1000, 1),
            'p99_ms' => round($this->percentile(99) * 1000, 1),
            'max_ms' => empty($this->times) ? 0 : round(max($this->times) * 1000, 1),
            'statuses' => $this->statuses,
            'errors' => count($this->errors),
            'error_rate_pct' => round($this->errorRate() * 100, 2),
            'wall_time_s' => round($this->wallTime, 1),
        ];
    }
}

/**
 * 単一HTTPリクエスト仕様（並行実行用）
 */
class RequestSpec
{
    public function __construct(
        public string $method,
        public string $path,
        public $body = null,
        public array $headers = [],
        public ?string $cookie = null,
        public array $query = [],
    ) {}
}

class LoadRunner
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * 逐次実行（コールバックベース・多段リクエストシナリオ用）
     *
     * @param callable(CurlClient): void $scenario
     */
    public function runSequential(callable $scenario, int $count): Metrics
    {
        $metrics = new Metrics();
        $start = microtime(true);

        for ($i = 0; $i < $count; $i++) {
            $client = new CurlClient($this->baseUrl);
            $error = null;
            $time = 0.0;
            $status = 0;

            try {
                $scenarioStart = microtime(true);
                $scenario($client);
                $time = microtime(true) - $scenarioStart;
                $status = $client->getLastStatus();
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }

            $metrics->add($time, $status, $error);
        }

        $metrics->wallTime = microtime(true) - $start;

        return $metrics;
    }

    /**
     * 並行実行（curl_multi・単一リクエスト仕様用）
     *
     * @param RequestSpec[] $specs リクエスト仕様の配列
     * @param int $concurrency 同時実行数
     */
    public function runConcurrent(array $specs, int $concurrency): Metrics
    {
        $metrics = new Metrics();
        $start = microtime(true);

        $total = count($specs);
        $index = 0;
        $handles = [];
        $multi = curl_multi_init();

        // 初期バッチ投入
        for ($i = 0; $i < min($concurrency, $total); $i++) {
            $ch = $this->buildHandle($specs[$index]);
            $handles[(int) $ch] = $index;
            curl_multi_add_handle($multi, $ch);
            $index++;
        }

        $active = null;
        do {
            $status = curl_multi_exec($multi, $active);
            if ($status !== CURLM_OK) {
                break;
            }

            while ($done = curl_multi_info_read($multi)) {
                $key = (int) $done['handle'];
                if (isset($handles[$key])) {
                    $specIndex = $handles[$key];

                    $time = curl_getinfo($done['handle'], CURLINFO_TOTAL_TIME);
                    $httpCode = (int) curl_getinfo($done['handle'], CURLINFO_HTTP_CODE);

                    $metrics->add($time, $httpCode);

                    curl_multi_remove_handle($multi, $done['handle']);
                    curl_close($done['handle']);
                    unset($handles[$key]);

                    if ($index < $total) {
                        $ch = $this->buildHandle($specs[$index]);
                        $handles[(int) $ch] = $index;
                        curl_multi_add_handle($multi, $ch);
                        $index++;
                    }
                }
            }

            if ($active > 0) {
                curl_multi_select($multi, 0.1);
            }
        } while (($active > 0 || count($handles) > 0));

        curl_multi_close($multi);

        $metrics->wallTime = microtime(true) - $start;

        return $metrics;
    }

    private function buildHandle(RequestSpec $spec)
    {
        $url = $this->baseUrl . $spec->path;
        if ($spec->query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($spec->query);
        }

        $ch = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_USERAGENT => 'LoadTestRunner/1.0',
        ];

        if ($spec->cookie) {
            $options[CURLOPT_COOKIE] = $spec->cookie;
        }

        if ($spec->method === 'POST') {
            $options[CURLOPT_POST] = true;
            if (is_array($spec->body) && isset($spec->body['_json'])) {
                $options[CURLOPT_POSTFIELDS] = $spec->body['_json'];
                $spec->headers[] = 'Content-Type: application/json';
            } elseif (is_array($spec->body)) {
                $options[CURLOPT_POSTFIELDS] = http_build_query($spec->body);
                $spec->headers[] = 'Content-Type: application/x-www-form-urlencoded';
            } elseif ($spec->body !== null) {
                $options[CURLOPT_POSTFIELDS] = $spec->body;
            }
        }

        if ($spec->headers) {
            $options[CURLOPT_HTTPHEADER] = $spec->headers;
        }

        curl_setopt_array($ch, $options);

        return $ch;
    }
}
