<?php
/**
 * 負荷テスト メインランナー
 * 計画書 §5.1 のシナリオを実行し、結果を JSON で出力する
 *
 * 実行: php scripts/loadtest/run-loadtest.php [--phase=all|smoke|baseline|load]
 */

require __DIR__ . '/LoadFramework.php';

use LoadTest\CurlClient;
use LoadTest\LoadRunner;
use LoadTest\Metrics;
use LoadTest\RequestSpec;
use LoadTest\SessionFactory;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MonthlyInvoice;
use App\Models\Trial;
use Illuminate\Support\Facades\DB;

// ===== 設定 =====
$baseUrl = getenv('LOADTEST_BASE_URL') ?: 'http://127.0.0.1:8000';
$phase = 'all';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--phase=')) {
        $phase = substr($arg, 8);
    }
}

$adminEmail = getenv('LOADTEST_ADMIN_EMAIL') ?: 'loadtest_admin0@example.jp';
$adminPassword = getenv('LOADTEST_ADMIN_PASSWORD') ?: 'loadtest-password';

$results = [
    'environment' => [
        'base_url' => $baseUrl,
        'php_version' => PHP_VERSION,
        'db_connection' => config('database.default'),
        'queue_connection' => config('queue.default'),
        'cache_store' => config('cache.default'),
        'session_driver' => config('session.driver'),
        'server' => 'php artisan serve (single-threaded)',
        'executed_at' => now()->toISOString(),
    ],
    'data_counts' => [],
    'scenarios' => [],
];

function logInfo(string $message): void
{
    echo '[' . date('H:i:s') . "] {$message}\n";
}

function recordScenario(array &$results, string $id, string $name, Metrics $metrics, array $sla = []): void
{
    $summary = $metrics->summary();
    $summary['scenario_id'] = $id;
    $summary['scenario_name'] = $name;
    $summary['sla'] = $sla;
    $results['scenarios'][] = $summary;

    logInfo(sprintf(
        '%s | %s | 総数:%d RPS:%.2f p50:%.1fms p95:%.1fms p99:%.1fms エラー率:%.2f%%',
        $id,
        $name,
        $summary['total'],
        $summary['rps'],
        $summary['p50_ms'],
        $summary['p95_ms'],
        $summary['p99_ms'],
        $summary['error_rate_pct']
    ));
}

// ===== データ件数 =====
$results['data_counts'] = [
    'facilities' => DB::table('facilities')->where('name', 'like', '負荷テスト施設%')->count(),
    'residents' => DB::table('residents')->where('name', 'like', '負荷テスト入居者%')->count(),
    'daily_charges' => DB::table('daily_charges')->count(),
    'monthly_invoices' => DB::table('monthly_invoices')->count(),
    'chatbot_faqs' => DB::table('chatbot_faqs')->count(),
    'chat_logs' => DB::table('chat_logs')->count(),
];

// ===== セットアップ =====
logInfo('=== セットアップ ===');

// トライアル関連データのクリーンアップ（プロビジョニング施設のユニーク制約回避）
DB::table('trials')->delete();
DB::table('facilities')->where('name', 'like', 'トライアル%')->delete();
DB::table('users')->where('email', 'like', 'smoke_%')->delete();
DB::table('users')->where('email', 'like', 'form_%')->delete();
DB::table('form_submissions')->delete();
DB::table('bookings')->delete();
logInfo('トライアル/フォーム/予約データをクリーンアップしました');

// 認証ユーザーのセッションクッキー生成（Filament v3 は Livewire ログインのため）
$sessionFactory = new SessionFactory($app, $baseUrl);
$authEmails = [];
for ($i = 0; $i < 5; $i++) {
    $authEmails[] = "loadtest_admin{$i}@example.jp";
}
$authSessions = $sessionFactory->createAuthCookies($authEmails);
$authCookies = array_column($authSessions, 'cookie');
$authTokens = array_column($authSessions, 'token');
logInfo('認証セッション 生成完了: ' . count($authCookies) . ' 件');

if (empty($authCookies)) {
    logInfo('ERROR: セッション生成に失敗しました。テストデータを確認してください。');
    exit(1);
}

// 請求書ID取得（PDFテスト用）
$invoiceIds = MonthlyInvoice::whereHas('resident', fn ($q) => $q->where('name', 'like', '負荷テスト入居者%'))
    ->orderBy('id')
    ->limit(50)
    ->pluck('id')
    ->all();
logInfo('対象請求書: ' . count($invoiceIds) . ' 件');

// トライアルID取得（見積テスト用）
$trialId = Trial::value('id');
if (! $trialId) {
    // トライアルを1件作成
    $trial = Trial::create([
        'company_name' => '負荷テスト企業',
        'contact_name' => '負荷テスト担当者',
        'email' => 'loadtest_trial_' . time() . '@example.jp',
        'facility_type' => 'special_nursing',
        'resident_capacity' => 'under_30',
        'score' => 50,
        'trial_config' => ['seed_sample_data' => false, 'lead_tier' => 'B'],
    ]);
    $trialId = $trial->id;
}
logInfo('トライアルID: ' . $trialId);

$runner = new LoadRunner($baseUrl);

// ===== Phase 0: スモークテスト =====
if (in_array($phase, ['all', 'smoke'])) {
    logInfo('');
    logInfo('=== Phase 0: スモークテスト（各シナリオ1リクエスト） ===');

    $smokeScenarios = [
        'S27' => ['ヘルスチェック', fn (CurlClient $c) => $c->get('/up')],
        'S01' => ['認証セッション生成', function (CurlClient $c) use ($sessionFactory) {
            // Filament v3 は Livewire ログインのため、セッション生成で代替
            $sessionFactory->createAuthCookie('loadtest_admin0@example.jp');
        }],
        'S02' => ['ダッシュボード（認証済）', function (CurlClient $c) use ($authCookies) {
            $c->setCookie($authCookies[0]);
            $c->get('/admin');
        }],
        'S04' => ['入居者一覧（認証済）', function (CurlClient $c) use ($authCookies) {
            $c->setCookie($authCookies[0]);
            $c->get('/admin/residents');
        }],
        'S11' => ['チャットボットFAQ', function (CurlClient $c) use ($authCookies, $authTokens) {
            $c->setCookie($authCookies[0]);
            $c->post('/api/chatbot/message', ['_json' => json_encode(['message' => '請求額の確認方法を教えてください'])], ['X-CSRF-TOKEN: ' . $authTokens[0]]);
        }],
        'S15' => ['トライアル申込', function (CurlClient $c) {
            $c->post('/api/trials', ['_json' => json_encode([
                'company_name' => '負荷テスト企業',
                'contact_name' => '担当者',
                'email' => 'smoke_' . uniqid() . '@example.jp',
                'facility_type' => 'special_nursing',
                'resident_capacity' => 'under_30',
            ])]);
        }],
        'S17' => ['見積算出', fn (CurlClient $c) => $c->get("/api/trials/{$GLOBALS['trialId']}/quote")],
        'S19' => ['サイトフォーム', function (CurlClient $c) {
            $c->post('/api/site-forms/catalog', ['_json' => json_encode([
                'company' => '負荷テスト企業',
                'name' => '担当者',
                'email' => 'form_' . uniqid() . '@example.jp',
            ])]);
        }],
        'S21' => ['外部請求単件', function (CurlClient $c) {
            $c->post('/api/external-invoices/single', ['_json' => json_encode([
                'facility_id' => 1,
                'resident_id' => 1,
                'billing_year_month' => '2026-10',
                'service_type' => 'visiting_care',
                'amount' => 45000,
                'tax_amount' => 4500,
                'tax_rate' => 10.0,
            ])]);
        }],
        'S22' => ['外部請求履歴', fn (CurlClient $c) => $c->get('/api/external-invoices')],
    ];

    foreach ($smokeScenarios as $id => [$name, $scenario]) {
        $metrics = $runner->runSequential($scenario, 1);
        recordScenario($results, $id, $name . '（スモーク）', $metrics);
    }
}

// ===== Phase 1: ベースライン（逐次・低負荷） =====
if (in_array($phase, ['all', 'baseline'])) {
    logInfo('');
    logInfo('=== Phase 1: ベースライン（逐次実行） ===');

    // S01 認証セッション生成（Filament v3 は Livewire ログインのため）
    $metrics = $runner->runSequential(function (CurlClient $c) use ($sessionFactory) {
        $sessionFactory->createAuthCookie('loadtest_admin0@example.jp');
    }, 10);
    recordScenario($results, 'S01', '認証セッション生成（ベースライン）', $metrics, ['p95_ms' => 1000]);

    // S27 ヘルスチェック（逐次）
    $metrics = $runner->runSequential(fn (CurlClient $c) => $c->get('/up'), 50);
    recordScenario($results, 'S27', 'ヘルスチェック（ベースライン）', $metrics, ['p95_ms' => 100]);
}

// ===== Phase 2: 負荷テスト（並行） =====
if (in_array($phase, ['all', 'load'])) {
    logInfo('');
    logInfo('=== Phase 2: 負荷テスト（並行実行） ===');

    // S27 ヘルスチェック（並行）
    $specs = [];
    for ($i = 0; $i < 100; $i++) {
        $specs[] = new RequestSpec('GET', '/up');
    }
    $metrics = $runner->runConcurrent($specs, 10);
    recordScenario($results, 'S27', 'ヘルスチェック（並行100req/10VU）', $metrics, ['p95_ms' => 100]);

    // S02 ダッシュボード（認証済み・並行）
    $specs = [];
    for ($i = 0; $i < 30; $i++) {
        $cookie = $authCookies[$i % count($authCookies)];
        $specs[] = new RequestSpec('GET', '/admin', null, [], $cookie);
    }
    $metrics = $runner->runConcurrent($specs, 5);
    recordScenario($results, 'S02', 'ダッシュボード（並行30req/5VU）', $metrics, ['p95_ms' => 1500]);

    // S04 Filament一覧（認証済み・並行）
    $listPages = ['/admin/residents', '/admin/monthly-invoices', '/admin/daily-charges', '/admin/charge-items'];
    $specs = [];
    for ($i = 0; $i < 40; $i++) {
        $cookie = $authCookies[$i % count($authCookies)];
        $page = $listPages[$i % count($listPages)];
        $specs[] = new RequestSpec('GET', $page, null, [], $cookie);
    }
    $metrics = $runner->runConcurrent($specs, 5);
    recordScenario($results, 'S04', 'Filament一覧（並行40req/5VU）', $metrics, ['p95_ms' => 1000]);

    // S06 PDFダウンロード（認証済み・並行・CPU集約）
    $specs = [];
    for ($i = 0; $i < 10; $i++) {
        $cookie = $authCookies[$i % count($authCookies)];
        $invoiceId = $invoiceIds[$i % count($invoiceIds)];
        $specs[] = new RequestSpec('GET', "/invoices/{$invoiceId}/pdf", null, [], $cookie);
    }
    $metrics = $runner->runConcurrent($specs, 3);
    recordScenario($results, 'S06', 'PDFダウンロード（並行10req/3VU）', $metrics, ['p95_ms' => 3000]);

    // S11-S13 チャットボット（認証済み・並行）
    $chatMessages = [
        '請求額の確認方法を教えてください',           // FAQ
        '負荷テスト入居者 1001の情報を教えてください', // 入居者照会（完全一致）
        '1001号室の今月の請求額はいくらですか',        // 請求額照会
    ];
    $specs = [];
    for ($i = 0; $i < 25; $i++) {
        $userIndex = $i % count($authCookies);
        $cookie = $authCookies[$userIndex];
        $token = $authTokens[$userIndex];
        $message = $chatMessages[$i % count($chatMessages)];
        $specs[] = new RequestSpec(
            'POST',
            '/api/chatbot/message',
            ['_json' => json_encode(['message' => $message, 'session_id' => "loadtest_{$i}"])],
            ['X-CSRF-TOKEN: ' . $token],
            $cookie
        );
    }
    $metrics = $runner->runConcurrent($specs, 5);
    recordScenario($results, 'S11', 'チャットボット（並行25req/5VU）', $metrics, ['p95_ms' => 500]);

    // S14 レート制限確認（逐次バースト・429検証）
    $metrics = $runner->runSequential(function (CurlClient $c) use ($authCookies, $authTokens) {
        $c->setCookie($authCookies[0]);
        $c->post('/api/chatbot/message', ['_json' => json_encode(['message' => 'テスト'])], ['X-CSRF-TOKEN: ' . $authTokens[0]]);
    }, 35);
    recordScenario($results, 'S14', 'チャットボット レート制限（35req逐次・429検証）', $metrics, ['expect_429' => true]);

    // S17 見積算出（並行）
    $specs = [];
    for ($i = 0; $i < 30; $i++) {
        $specs[] = new RequestSpec('GET', "/api/trials/{$trialId}/quote");
    }
    $metrics = $runner->runConcurrent($specs, 10);
    recordScenario($results, 'S17', '見積算出（並行30req/10VU）', $metrics, ['p95_ms' => 500]);

    // S19 サイトフォーム（並行・レート制限10/min）
    $specs = [];
    for ($i = 0; $i < 12; $i++) {
        $specs[] = new RequestSpec('POST', '/api/site-forms/catalog', [
            '_json' => json_encode([
                'company' => '負荷テスト企業',
                'name' => '担当者',
                'email' => "form_lt_{$i}_" . uniqid() . '@example.jp',
            ]),
        ]);
    }
    $metrics = $runner->runConcurrent($specs, 5);
    recordScenario($results, 'S19', 'サイトフォーム（並行12req/5VU・レート制限検証）', $metrics);

    // S21 外部請求単件（並行）
    $specs = [];
    for ($i = 0; $i < 20; $i++) {
        $specs[] = new RequestSpec('POST', '/api/external-invoices/single', [
            '_json' => json_encode([
                'facility_id' => 1,
                'resident_id' => 1,
                'billing_year_month' => '2026-10',
                'service_type' => 'visiting_care',
                'external_invoice_number' => "LT-SINGLE-{$i}-" . uniqid(),
                'amount' => 45000,
                'tax_amount' => 4500,
                'tax_rate' => 10.0,
            ]),
        ]);
    }
    $metrics = $runner->runConcurrent($specs, 5);
    recordScenario($results, 'S21', '外部請求単件（並行20req/5VU）', $metrics, ['p95_ms' => 1000]);

    // S22 外部請求履歴（並行）
    $specs = [];
    for ($i = 0; $i < 40; $i++) {
        $specs[] = new RequestSpec('GET', '/api/external-invoices', null, [], null, ['per_page' => 50]);
    }
    $metrics = $runner->runConcurrent($specs, 10);
    recordScenario($results, 'S22', '外部請求履歴（並行40req/10VU）', $metrics, ['p95_ms' => 1000]);
}

// ===== 結果出力 =====
$outputFile = __DIR__ . '/results/loadtest_results.json';
@mkdir(__DIR__ . '/results', 0777, true);
file_put_contents($outputFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

logInfo('');
logInfo('=== 負荷テスト完了 ===');
logInfo('結果保存先: ' . $outputFile);

// サマリ出力
logInfo('');
logInfo('=== サマリ ===');
foreach ($results['scenarios'] as $scenario) {
    logInfo(sprintf(
        '%s | %s | p95:%.1fms RPS:%.2f エラー率:%.2f%%',
        $scenario['scenario_id'],
        $scenario['scenario_name'],
        $scenario['p95_ms'],
        $scenario['rps'],
        $scenario['error_rate_pct']
    ));
}
