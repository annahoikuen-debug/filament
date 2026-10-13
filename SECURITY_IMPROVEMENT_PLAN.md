# セキュリティ改善 実装計画書

**作成日**: 2026-10-13  
**対象**: 高齢者施設請求管理システム  
**ステータス**: 計画フェーズ

---

## 1. 概要

本計画書は、精査で判明した3つのセキュリティ課題に対する詳細な実装手順と、リグレッション防止のためのテスト戦略を定義する。

| ID | 課題 | 深刻度 | 目標完了 |
|----|------|--------|----------|
| SEC-01 | ExternalInvoiceController 認証欠落 | Critical | Phase 1 |
| SEC-02 | セキュリティヘッダー未設定 | High | Phase 1 |
| SEC-03 | セッション/Cookie 設定の脆弱性 | Medium | Phase 2 |

---

## 2. Phase 1: Critical/High 対応（即時実装）

### 2.1 SEC-01: ExternalInvoiceController に認証・認可追加

#### 2.1.1 実装方針
- **認証方式**: Laravel Sanctum の API トークン認証（Bearer Token）
- **認可**: トークンに紐づく `facility_id` で施設スコープを強制
- **レート制限**: 既存 `throttle:60,1` を維持

#### 2.1.2 実装手順

**Step 1: Sanctum インストール・設定（未導入の場合）**
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

**Step 2: ApiToken モデル・マイグレーション作成**
```php
// database/migrations/xxxx_xx_xx_create_api_tokens_table.php
Schema::create('api_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
    $table->string('name')->comment('トークン名（外部システム名等）');
    $table->string('token', 80)->unique()->comment('ハッシュ化済みトークン');
    $table->text('abilities')->nullable()->comment('権限: invoices.read, invoices.write');
    $table->timestamp('last_used_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
    
    $table->index(['facility_id', 'token']);
});
```

**Step 3: ExternalInvoiceController 修正**
```php
// app/Http/Controllers/Api/ExternalInvoiceController.php
use Laravel\Sanctum\PersonalAccessToken;

class ExternalInvoiceController extends Controller
{
    // トークン検証ミドルウェア相当の処理を共通化
    private function authenticateRequest(Request $request): ?PersonalAccessToken
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }
        
        $accessToken = PersonalAccessToken::findToken($token);
        
        if (!$accessToken || !$accessToken->can('invoices.write')) {
            return null;
        }
        
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return null;
        }
        
        // 最終使用時刻更新
        $accessToken->update(['last_used_at' => now()]);
        
        return $accessToken;
    }
    
    private function authorizeFacility(PersonalAccessToken $token, int $facilityId): bool
    {
        return $token->tokenable_id === $facilityId;
    }
    
    public function store(Request $request): JsonResponse
    {
        $token = $this->authenticateRequest($request);
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid or missing API token'
            ], 401);
        }
        
        $facilityId = $request->integer('facility_id');
        if (!$this->authorizeFacility($token, $facilityId)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Token not authorized for this facility'
            ], 403);
        }
        // ... 既存処理
    }
    
    // storeSingle, index も同様に修正
}
```

**Step 4: ルート定義更新**
```php
// routes/api.php
Route::prefix('external-invoices')->middleware(['throttle:60,1'])->group(function () {
    Route::post('/', [ExternalInvoiceController::class, 'store']);
    Route::post('/single', [ExternalInvoiceController::class, 'storeSingle']);
    Route::get('/', [ExternalInvoiceController::class, 'index']);
});
// ※ 認証はコントローラー内で実施（トークンから facility_id を取得し照合）
```

**Step 5: 管理画面からトークン発行機能追加（Filament Resource）**
```php
// app/Filament/Resources/ApiTokenResource.php
// - 施設管理者は自施設のトークンのみ作成・管理可能
// - 権限: invoices.read, invoices.write のチェックボックス
// - 有効期限設定可能
```

#### 2.1.3 テスト計画

| テスト種別 | テストケース | 期待結果 |
|------------|--------------|----------|
| **Unit** | `authenticateRequest()` 有効トークン | PersonalAccessToken インスタンス返却 |
| **Unit** | `authenticateRequest()` 無効トークン | `null` 返却 |
| **Unit** | `authenticateRequest()` 期限切れトークン | `null` 返却 |
| **Unit** | `authenticateRequest()` 権限不足トークン | `null` 返却 |
| **Unit** | `authorizeFacility()` 同一施設 | `true` |
| **Unit** | `authorizeFacility()` 異なる施設 | `false` |
| **Feature** | Bearer Token なしで POST | 401 Unauthorized |
| **Feature** | 無効トークンで POST | 401 Unauthorized |
| **Feature** | 期限切れトークンで POST | 401 Unauthorized |
| **Feature** | 他施設IDで POST（自施設トークン使用） | 403 Forbidden |
| **Feature** | 正しいトークン・施設IDで POST | 200 OK・データ作成 |
| **Feature** | 正しいトークンで GET（一覧取得） | 200 OK・自施設データのみ返却 |
| **Feature** | レート制限超過 | 429 Too Many Requests |
| **Browser** | Filament: トークン作成・編集・削除 | 権限に応じた操作可否 |

---

### 2.2 SEC-02: セキュリティヘッダー実装

#### 2.2.1 実装方針
- ミドルウェアとして実装し、全 HTTP レスポンスに適用
- 環境別設定（local では CSP を緩和、本番では厳格）
- Filament 管理画面・公開 API 両対応

#### 2.2.2 実装手順

**Step 1: ミドルウェア作成**
```php
// app/Http/Middleware/SecurityHeaders.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var SymfonyResponse $response */
        $response = $next($request);
        
        $isLocal = app()->environment('local');
        $isProduction = app()->environment('production');
        
        // 共通ヘッダー
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        
        // CSP: 環境別設定
        if ($isProduction) {
            $csp = $this->buildProductionCsp();
        } else {
            $csp = $this->buildLocalCsp();
        }
        $response->headers->set('Content-Security-Policy', $csp);
        
        // HSTS（本番のみ・HTTPS 前提）
        if ($isProduction && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        
        return $response;
    }
    
    private function buildProductionCsp(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // Filament/Livewire 用
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];
        
        return implode('; ', $directives);
    }
    
    private function buildLocalCsp(): string
    {
        // 開発用: より緩やか（Vite HMR 等対応）
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:* ws://localhost:*",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https: http:",
            "connect-src 'self' http://localhost:* ws://localhost:*",
            "frame-src 'self'",
        ];
        
        return implode('; ', $directives);
    }
}
```

**Step 2: ミドルウェア登録**
```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\SecurityHeaders::class,
    ]);
    
    $middleware->api(append: [
        \App\Http\Middleware\SecurityHeaders::class,
    ]);
})
```

**Step 3: 例外パス設定（必要に応じて）**
```php
// app/Http/Middleware/SecurityHeaders.php の handle() 先頭に追加
$exceptPaths = [
    '/chatbot/widget.js',  // 静的JS配信
    '/up',                 // ヘルスチェック
];

if (in_array($request->path(), $exceptPaths)) {
    return $next($request);
}
```

#### 2.2.3 テスト計画

| テスト種別 | テストケース | 期待結果 |
|------------|--------------|----------|
| **Unit** | `buildProductionCsp()` 文字列検証 | 必要ディレクティブ含む |
| **Unit** | `buildLocalCsp()` 文字列検証 | 開発用緩和設定含む |
| **Feature** | 管理画面アクセス（本番モード） | 全セキュリティヘッダー存在 |
| **Feature** | API エンドポイントアクセス | 全セキュリティヘッダー存在 |
| **Feature** | `/chatbot/widget.js` アクセス | CSP 適用除外または適切なヘッダー |
| **Feature** | HTTPS 環境で HSTS ヘッダー | `Strict-Transport-Security` 存在 |
| **Browser** | 管理画面主要ページ表示 | CSP 違反エラーなし（Console 確認） |
| **Browser** | 公開チャットボットウィジェット読み込み | CSP 違反エラーなし |

---

## 3. Phase 2: Medium 対応（短期実装）

### 3.1 SEC-03: セッション/Cookie 設定強化

#### 3.1.1 実装方針
- `.env.example` と本番環境で安全なデフォルト値を設定
- 環境別設定ファイルで管理

#### 3.1.2 実装手順

**Step 1: .env.example 更新**
```env
# Session Security
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=null

# Application Security
APP_DEBUG=false
LOG_LEVEL=error
```

**Step 2: config/session.php 確認・調整**
```php
// config/session.php（Laravel 標準・既存確認）
'secure' => env('SESSION_SECURE_COOKIE', false),
'http_only' => env('SESSION_HTTP_ONLY', true),
'same_site' => env('SESSION_SESSION_SAME_SITE', 'lax'),
'encrypt' => env('SESSION_ENCRYPT', false),
```

**Step 3: 本番環境設定適用**
- `.env.production` またはデプロイパイプラインで上記値を注入

**Step 4: セッション ドライバを database に変更（推奨）**
```bash
php artisan session:table
php artisan migrate
```
`.env`: `SESSION_DRIVER=database`

#### 3.1.3 テスト計画

| テスト種別 | テストケース | 期待結果 |
|------------|--------------|----------|
| **Unit** | `SESSION_ENCRYPT=true` 時、セッション暗号化 | ペイロードが暗号化されて保存 |
| **Feature** | HTTPS 環境でログイン | `Secure` フラグ付き Cookie 発行 |
| **Feature** | HTTP 環境でログイン（local） | `Secure` フラグなし Cookie 発行 |
| **Feature** | 全レスポンスで `HttpOnly` | `HttpOnly` フラグ付き |
| **Feature** | 全レスポンスで `SameSite=Lax` | `SameSite=Lax` 設定 |
| **Feature** | `APP_DEBUG=false` でエラー発生 | スタックトレース非表示・汎用エラー画面 |

---

## 4. 共通テスト基盤・運用

### 4.1 テスト実行コマンド
```bash
# 全セキュリティ関連テスト実行
php artisan test --filter=Security

# 特定テストクラス
php artisan test tests/Feature/Security/ExternalInvoiceControllerAuthTest.php
php artisan test tests/Feature/Security/SecurityHeadersTest.php
php artisan test tests/Feature/Security/SessionSecurityTest.php
```

### 4.2 CI/CD 統合（GitHub Actions 例）
```yaml
# .github/workflows/security-tests.yml
name: Security Tests

on: [push, pull_request]

jobs:
  security-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install --prefer-dist --no-progress
      - run: cp .env.example .env && php artisan key:generate
      - run: php artisan migrate --force
      - run: php artisan test --filter=Security --parallel
      - run: php artisan test --filter=ExternalInvoiceControllerAuthTest --parallel
```

### 4.3 手動検証チェックリスト（リリース前）

| 項目 | 確認方法 | 担当 |
|------|----------|------|
| External API トークン認証 | Postman/curl で 401/403/200 確認 | Backend |
| セキュリティヘッダー全ページ | DevTools Network タブで全レスポンス確認 | QA |
| CSP 違反なし | Console で CSP エラーなし確認 | QA |
| セッション Cookie 属性 | DevTools Application > Cookies 確認 | QA |
| 本番環境変数適用 | `.env` 値と `php artisan config:show` 確認 | DevOps |

---

## 5. リスク・ロールバック計画

| リスク | 影響 | 対策 | ロールバック |
|--------|------|------|-------------|
| External API 既存連携先がトークン未対応 | 連携停止 | 移行期間設け並行運用・事前通知 | ミドルウェア無効化で旧仕様復帰 |
| CSP 違反で管理画面機能不全 | 操作不能 | local 環境で事前検証・report-only モード活用 | ミドルウェア順序変更で無効化 |
| セッション暗号化で既存セッション無効 | 全ユーザー再ログイン | メンテナンスウィンドウで実施 | `SESSION_ENCRYPT=false` 戻し |

---

## 6. スケジュール目安

| フェーズ | 作業 | 工数目安 |
|----------|------|----------|
| Phase 1-1 | Sanctum 導入・ApiToken マイグレーション | 0.5日 |
| Phase 1-2 | ExternalInvoiceController 認証実装 | 1日 |
| Phase 1-3 | Filament トークン管理 Resource | 0.5日 |
| Phase 1-4 | SEC-01 テスト作成・実行 | 1日 |
| Phase 1-5 | SecurityHeaders ミドルウェア実装 | 0.5日 |
| Phase 1-6 | SEC-02 テスト作成・実行 | 0.5日 |
| Phase 2-1 | 環境変数・設定ファイル更新 | 0.5日 |
| Phase 2-2 | セッション DB 化・テスト | 0.5日 |
| **合計** | | **約 5日** |

---

## 7. 完了基準

- [ ] SEC-01: 全 External API エンドポイントで 401/403/200 適切返却
- [ ] SEC-01: 既存連携先への移行ガイド・トークン発行完了
- [ ] SEC-02: 全レスポンスで 7 つのセキュリティヘッダー出力確認
- [ ] SEC-02: CSP 違反ゼロ（本番同等環境で検証）
- [ ] SEC-03: 本番環境で安全な Cookie 属性・セッション暗号化有効
- [ ] 全自動テストパス（CI 含む）
- [ ] 手動検証チェックリスト全項目クリア

---

## 8. 参考資料

- [Laravel Sanctum Documentation](https://laravel.com/docs/sanctum)
- [OWASP Secure Headers Project](https://owasp.org/www-project-secure-headers/)
- [Laravel Session Configuration](https://laravel.com/docs/session)
- [Content Security Policy Reference](https://content-security-policy.com/)