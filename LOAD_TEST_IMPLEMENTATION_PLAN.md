# 負荷テスト実装計画書（全機能対象）

**作成日**: 2026-10-10
**対象**: 高齢者施設請求管理システム あんしん ver1.6（Laravel 12 + Filament v3）
**目的**: 全機能に対する負荷テストの実施計画・手順・判定基準を定義する
**現状の課題**: 既存の `scripts/load_test.php` はサービス層直接呼び出し（SQLite・HTTP非経由）のみで、HTTPエンドポイント・認証・レート制限・ジョブキュー・バッチ処理を経由した負荷検証が未実施

---

## 1. 計画書概要

### 1.1 背景

| 項目 | 現状 |
|------|------|
| 本番構成 | FrankenPHP(Docker) + PostgreSQL + Redis（`Dockerfile` / `config/database.php`） |
| 開発構成 | SQLite + file cache + sync queue（`.env`） |
| 既存負荷テスト | `scripts/load_test.php`（サービス層直接呼び出し、10施設×100名、SQLite） |
| 未検証領域 | HTTPエンドポイント全体、セッション認証、レート制限、キュージョブ、月次バッチの本番規模性能 |

### 1.2 負荷テストの目的

1. **性能基準値の確立**: 全機能のレスポンスタイム・スループットのベースラインを計測
2. **SLA達成確認**: 想定負荷下での目標値（p95等）の達成検証
3. **ボトルネック特定**: DB接続プール（`config/database.php` の `pool.max_connections=3`）、FrankenPHPワーカー数（`FRANKENPHP_PHP_SERVER_WORKERS`）、PDF生成（dompdf+Noto Sans JP）等の律速要因の特定
4. **非機能要件の検証**: レート制限（429応答）、認可（403）、施設スコープ分離の負荷下での動作確認
5. **容量計画の根拠取得**: 同時接続数増加時のスケーリング要件の定量化

### 1.3 スコープ

- **対象**: 本計画書 §2 に列挙する全機能（管理画面・Web API・公開API・バッチ・ジョブ）
- **対象外**: 静的ウェブサイト（`website/`、純粋なHTMLのためHTTPサーバの性能のみ計測）、外部メールSMTP実送信（`MAIL_MAILER=log` で代替）

### 1.4 用語

| 用語 | 定義 |
|------|------|
| SLA | サービスレベル目標（p95レスポンスタイム等） |
| RPS | Requests Per Second |
| VU | Virtual User（k6の仮想ユーザー） |
| ソークテスト | 一定負荷を長時間継続する耐久テスト |

---

## 2. 対象機能一覧（全機能マトリクス）

### 2.1 Filament管理画面（`/admin`、セッション認証）

| # | 機能 | エンドポイント | 負荷特性 |
|---|------|----------------|----------|
| A1 | ログイン/ログアウト | `GET /admin/login`, `POST /admin/login` | セッション生成、bcrypt認証 |
| A2 | ダッシュボード | `GET /admin` | 集計クエリ多数（`Dashboard::mount`） |
| A3 | 統合請求管理ダッシュボード | `GET /admin/integrated-billing` | 住居費+介護サービスの総合計 |
| A4 | チャットボットアシスタント | `GET /admin/chatbot-assistant` | 静的ページ |
| A5 | オンボーディングウィザード | `GET /admin/onboarding` | 5ステップ |
| A6 | 入居者管理 CRUD | `/admin/residents` | 一覧（ページネーション）/作成/編集 |
| A7 | 月次請求管理 CRUD | `/admin/monthly-invoices` | 一覧（集計カラムあり）/作成/編集 |
| A8 | 日々課金 CRUD | `/admin/daily-charges` | 一覧/作成/編集 |
| A9 | 品目マスタ CRUD | `/admin/charge-items`, `/admin/item-masters` | 一覧/作成/編集 |
| A10 | 施設管理 CRUD | `/admin/facilities` | 一覧/作成/編集 |
| A11 | 税率設定 CRUD | `/admin/tax-settings` | 一覧/作成/編集 |
| A12 | PDFテンプレート設定 CRUD | `/admin/pdf-template-settings` | 一覧/作成/編集 |
| A13 | 介護サービス請求 CRUD | `/admin/service-invoices` | 一覧/作成/編集 |
| A14 | チャットボットFAQ CRUD | `/admin/chatbot-faqs` | 一覧/作成/編集 |
| A15 | フォーム送信履歴 | `/admin/form-submissions` | 一覧 |
| A16 | 監査ログ | `/admin/activity-logs` | 一覧（件数が多い） |
| A17 | 勘定科目マスタ | `/admin/chart-of-accounts` | 一覧/作成/編集 |
| A18 | 会計連携プロファイル | `/admin/accounting-export-profiles` | 一覧/作成/編集 |
| A19 | ZIP生成進捗 | `/admin/monthly-invoices/zip-progress/{jobId}` | ポーリング |

### 2.2 Web API（`routes/web.php`）

| # | 機能 | エンドポイント | 負荷特性 |
|---|------|----------------|----------|
| B1 | 請求書PDFダウンロード | `GET /invoices/{invoice}/pdf` | **CPU集約**（dompdf描画+QRコード+パスワード保護） |
| B2 | 請求書PDFストリーム | `GET /invoices/{invoice}/stream` | 同上 |
| B3 | ZIP進捗確認 | `GET /invoices/zip-progress/{jobId}` | キャッシュ参照（軽量） |
| B4 | デモ面談予約一覧 | `GET /bookings` | JSON一覧 |
| B5 | 請求書HTMLプレビュー | `GET /invoices/{invoice}/preview/{type}` | 署名付きURL、HTML生成 |
| B6 | チャットボットAPI | `POST /api/chatbot/message` | throttle:30,1、入居者検索（ファジー一致時は全件スキャン） |
| B7 | 資料ダウンロード | `GET /forms/{submission}/download/{document}` | 署名付きURL |

### 2.3 公開API（`routes/api.php`）

| # | 機能 | エンドポイント | レート制限 | 負荷特性 |
|---|------|----------------|------------|----------|
| C1 | トライアル申込 | `POST /api/trials` | 5回/分/IP | **重い**: リードスコアリング+プロビジョニング（施設+管理者ユーザー+サンプルデータ作成）+メールキュー投入 |
| C2 | トライアル本契約移行 | `POST /api/trials/{trial}/convert` | 10回/分/IP | サブスクリプション作成 |
| C3 | 見積算出 | `GET /api/trials/{trial}/quote` | なし | 軽量 |
| C4 | 見積送信 | `POST /api/trials/{trial}/quote/send` | 3回/分/IP | メールキュー投入 |
| C5 | デモ面談予約 | `POST /api/bookings` | 5回/分/IP | 二重予約チェック+メール |
| C6 | サイトフォーム送信 | `POST /api/site-forms/{type}` | 10回/分 | ハニーポット+最短送信時間チェック（5種別） |
| C7 | 外部請求一括受信 | `POST /api/external-invoices` | なし | **重い**: トランザクション内ループ+base64 PDF保存 |
| C8 | 外部請求単件受信 | `POST /api/external-invoices/single` | なし | updateOrCreate |
| C9 | 外部請求履歴 | `GET /api/external-invoices` | なし | ページネーション（最大200件/ページ） |

### 2.4 バッチ/コンソールコマンド

| # | 機能 | コマンド | スケジュール | 負荷特性 |
|---|------|----------|--------------|----------|
| D1 | 月次請求一括生成 | `billing:generate-monthly` | 毎月1日 02:00 | **最重量**: 入居者全件の請求計算（日割り/定期課金/税計算） |
| D2 | データベースバックアップ | `backup:database` | 毎日 03:00 | DB+ストレージのZIP圧縮 |
| D3 | バックアップリストア | `backup:restore` | 手動 | リストア性能 |
| D4 | FAQ未回答分析 | `chatbot:analyze-faq-misses` | 毎日 05:00 | チャットログ集計 |
| D5 | PDFフォントインストール | `pdf:install-fonts` | 手動 | ネットワークダウンロード |
| D6 | 古いPDFキャッシュ削除 | `invoices:cleanup-pdfs` | 毎月1日 02:00 | ファイル走査 |

### 2.5 キュージョブ

| # | 機能 | ジョブ | timeout/tries | 負荷特性 |
|---|------|--------|---------------|----------|
| E1 | 請求書PDF生成 | `GenerateInvoicePdfJob` | 120s/3回 | CPU集約（dompdf） |
| E2 | 月次ZIP一括生成 | `GenerateMonthlyZipJob` | 600s/2回 | **最重量**: PDF全件生成+ZIP圧縮 |
| E3 | トライアル期限チェック | `CheckAndNotifyTrialExpiries` | - | 毎日09:00 |
| E4 | 期限通知メール | `SendTrialExpiryNotification` | - | メール送信 |
| E5 | ナーチャリングメール | `SendTrialNurtureEmails` | - | 毎日10:00 |

### 2.6 ヘルスチェック

| # | 機能 | エンドポイント | 備考 |
|---|------|----------------|------|
| F1 | ヘルスチェック | `GET /up` | Caddyfileで即応答 |

---

## 3. 負荷テスト環境構築計画

### 3.1 環境構成

本番環境と同一構成を `docker-compose.loadtest.yml` で構築する。

```
┌─────────────────────────────────────────────────────┐
│  k6 (grafana/k6)          ← 負荷発生                │
│  InfluxDB + Grafana       ← メトリクス可視化（任意） │
├─────────────────────────────────────────────────────┤
│  app (FrankenPHP)         ← 対象アプリ（workers=4）  │
│  queue-worker × 2         ← php artisan queue:work  │
├─────────────────────────────────────────────────────┤
│  PostgreSQL 16            ← 本番と同一DB             │
│  Redis 7                  ← セッション/キャッシュ/キュー│
└─────────────────────────────────────────────────────┘
```

### 3.2 環境変数（`.env.loadtest`）

| 項目 | 値 | 理由 |
|------|----|------|
| `DB_CONNECTION` | `pgsql` | **SQLiteは書き込みロックの影響で同時接続負荷テストに不適** |
| `QUEUE_CONNECTION` | `redis` | ジョブ負荷検証のため |
| `CACHE_STORE` | `redis` | 進捗キャッシュ等の実態検証 |
| `SESSION_DRIVER` | `redis` | 同時セッションの実態検証 |
| `MAIL_MAILER` | `log` | 実メール送信の回避 |
| `FRANKENPHP_PHP_SERVER_WORKERS` | `4` | 並列処理能力の検証（本番値に合わせる） |
| `APP_DEBUG` | `false` | 本番相当の挙動 |

### 3.3 環境構築手順

1. `docker-compose.loadtest.yml` 新規作成（app/pgsql/redis/queue-worker/k6）
2. `.env.loadtest` を §3.2 の内容で作成
3. `php artisan migrate --seed` ＋ §9 のデータ生成スクリプト実行
4. `php artisan config:cache && php artisan route:cache && php artisan view:cache`（本番相当）
5. 疎通確認: `curl http://localhost:8000/up`

### 3.4 検証必須の構成ボトルネック（事前調査で判明）

| 項目 | 現状値 | 懸念 |
|------|--------|------|
| DB接続プール | `config/database.php`: `pool.min_connections=1`, `pool.max_connections=3` | **同時接続数3以上で直ちにボトルネック化の可能性**。負荷テストで接続待ちの発生を計測し、プールサイズ見直しの根拠とする |
| FrankenPHPワーカー | `Caddyfile`: コメントに `FRANKENPHP_PHP_SERVER_WORKERS=1` の記載 | ワーカー1だと同時処理が逐次化される。workers=4での比較計測を実施 |
| チャットボットファジー検索 | `ResidentQueryService::findCandidates` | 完全一致/部分一致に落ちない場合、施設スコープ全件スキャン＋PHP側ループで類似度計算。入居者数増大時にO(n)悪化 |

---

## 4. ツール選定

| ツール | 用途 | 選定理由 |
|--------|------|----------|
| **k6**（一次選定） | HTTP負荷テスト | JSスクリプト、CLI/CI統合容易、メトリクス（p95/p99/RPS/エラー率）標準出力、InfluxDB+Grafana連携 |
| JMeter | 代替 | GUIベースで保守性が低いため一次選定としない |
| Artillery | 代替 | YAMLベースだがカスタム性がk6に劣る |
| 既存 `scripts/load_test.php` | サービス層直接計測 | HTTPオーバーヘッドを除いた純粋なビジネスロジック性能の計測に流用 |
| `pg_stat_statements` | DB計測 | クエリ単位の実行時間・回数の特定 |
| `docker stats` / cAdvisor | インフラ計測 | CPU/メモリ/ネットワーク/ディスクIO |

---

## 5. テストシナリオ設計（機能別）

### 5.1 シナリオ一覧

| シナリオID | 対象機能 | 内容 | 想定負荷 | SLA（p95） |
|------------|----------|------|----------|------------|
| S01 | A1 ログイン | ログイン→ダッシュボード遷移→ログアウト | 10 VU | 1.0s |
| S02 | A2 ダッシュボード | ダッシュボード表示（年月切替×3） | 20 VU | 1.5s |
| S03 | A3 統合請求管理 | 統合ダッシュボード表示+フィルタ変更 | 10 VU | 2.0s |
| S04 | A6-A18 CRUD | 各リソース一覧表示（ページネーション） | 20 VU | 1.0s |
| S05 | A6-A18 CRUD | 作成/編集フォーム表示+保存 | 10 VU | 1.5s |
| S06 | B1 PDFダウンロード | 請求書PDF単体ダウンロード（初回生成） | 5 VU | 3.0s |
| S07 | B1 PDFダウンロード | 請求書PDF（キャッシュ済み） | 10 VU | 1.0s |
| S08 | B2 PDFストリーム | ストリームプレビュー | 5 VU | 3.0s |
| S09 | B3 ZIP進捗 | 進捗ポーリング（1秒間隔×60回） | 10 VU | 0.3s |
| S10 | B5 HTMLプレビュー | 署名付きURLでプレビュー | 5 VU | 2.0s |
| S11 | B6 チャットボット | FAQ質問（軽量） | 10 VU | 0.5s |
| S12 | B6 チャットボット | 入居者照会（完全一致） | 10 VU | 0.5s |
| S13 | B6 チャットボット | 入居者照会（ファジー一致・全件スキャン） | 5 VU | 1.0s |
| S14 | B6 チャットボット | レート制限確認（31回/分で429） | 1 VU | 429確認 |
| S15 | C1 トライアル申込 | トライアル申込一式 | 5 VU | 3.0s |
| C16 | C2 本契約移行 | 変換トークン付き移行 | 5 VU | 2.0s |
| S17 | C3 見積算出 | 見積表示 | 10 VU | 0.5s |
| S18 | C5 予約作成 | デモ面談予約 | 5 VU | 1.0s |
| S19 | C6 サイトフォーム | 5種別のフォーム送信 | 10 VU | 1.0s |
| S20 | C7 外部請求一括 | 100件一括受信 | 3 VU | 10.0s |
| S21 | C8 外部請求単件 | 単件受信 | 10 VU | 1.0s |
| S22 | C9 外部請求履歴 | 履歴一覧（ページネーション） | 10 VU | 1.0s |
| S23 | D1 月次一括生成 | `billing:generate-monthly`（1,000名） | 単独実行 | 10分 |
| S24 | E2 ZIP一括生成 | `GenerateMonthlyZipJob`（1,000件） | 単独実行 | 10分 |
| S25 | E1 PDF生成ジョブ | `GenerateInvoicePdfJob` 100件 | キュー投入 | 120s/件 |
| S26 | D2 バックアップ | `backup:database --type=full --compress` | 単独実行 | 30分 |
| S27 | F1 ヘルスチェック | `GET /up` | 50 VU | 0.1s |
| S28 | 混合負荷 | S01-S22 を本番比率で混合 | 50 VU | 各SLA |

### 5.2 レート制限の取扱い

公開APIのレート制限はIP単位（`AppServiceProvider::registerRateLimiters`）。負荷テストでは以下のいずれかで対応する。

1. **429を計測対象に含める**: 制限到達時の429応答率を計測し、制限動作を検証
2. **X-Forwarded-For によるIP分散**: `TrustProxies` 設定を有効化し、k6側で送信元IPを分散（`http.get(url, { headers: { 'X-Forwarded-For': ... } })`）
3. テスト環境のみ制限を緩和（`config/app.php` 等で上書き）— **本番挙動検証の観点から非推奨**

推奨: 方式2（IP分散）＋方式1（429検証を別シナリオで実施）

---

## 6. 負荷プロファイル（段階的実施）

| フェーズ | 名称 | 内容 | 判定基準 |
|----------|------|------|----------|
| Phase 0 | スモークテスト | 1 VUで全シナリオの疎通確認 | 全シナリオがエラーなく完走 |
| Phase 1 | ベースライン | 1-5 VUで各機能の性能基準値を計測 | 基準値を記録（改善の比較対象） |
| Phase 2 | 負荷テスト | 想定負荷（§5.1の想定負荷）で30分間実施 | 全SLA達成、エラー率<0.1% |
| Phase 3 | ストレステスト | 想定負荷の2倍→4倍→8倍と段階的に増加 | 劣化点・限界値の特定 |
| Phase 4 | 耐久（ソーク）テスト | 想定負荷で4時間継続 | メモリリークなし、レスポンスタイム劣化<20% |
| Phase 5 | バッチ/ジョブ負荷 | D1-D6、E1-E5を単独/同時実行 | 時間内完了、失敗なし |
| Phase 6 | 混合負荷 | Phase 2+5を同時実施（月次1日朝の実態再現） | 全SLA達成 |

---

## 7. 計測指標・SLA目標値

### 7.1 アプリケーション指標

| 指標 | 目標 |
|------|------|
| レスポンスタイム p95 | §5.1 のSLA表参照 |
| レスポンスタイム p99 | p95の2倍以内 |
| エラー率（5xx） | < 0.1% |
| タイムアウト率 | 0% |
| スループット | 想定RPS以上を維持 |

### 7.2 システム指標

| 指標 | 目標 |
|------|------|
| CPU使用率 | < 70%（常時） |
| メモリ使用率 | < 80%（常時） |
| DB接続待ち時間 | < 50ms |
| DBアクティブ接続数 | `max_connections` の80%未満 |
| キュー滞留数 | 0（処理能力内） |
| ディスクIO待ち | < 10ms |

### 7.3 バッチ指標

| 指標 | 目標 |
|------|------|
| 月次一括生成（1,000名） | 10分以内 |
| ZIP一括生成（1,000件） | 10分以内 |
| メモリ使用量（バッチ） | 512MB以内（`phpunit.xml` の memory_limit 準拠） |

---

## 8. 実装計画

### 8.1 ディレクトリ構成

```
tests/load/                     # k6 シナリオ群
├── options/
│   └── common.js               # 共通オプション（閾値・期間）
├── setup/
│   └── seed-data.js            # テストデータ生成用k6 setup（API経由）
├── scenarios/
│   ├── auth.js                 # S01
│   ├── dashboard.js            # S02, S03
│   ├── filament-crud.js        # S04, S05
│   ├── pdf-download.js         # S06, S07, S08
│   ├── zip-progress.js         # S09
│   ├── chatbot.js              # S11-S14
│   ├── public-api.js           # S15-S19
│   ├── external-invoices.js    # S20-S22
│   ├── health.js               # S27
│   └── mixed.js                # S28
└── run-all.sh                  # 全シナリオ順次実行スクリプト

scripts/loadtest/               # PHP補助スクリプト
├── generate-data.php           # テストデータ生成（§9）
├── check-environment.php       # 環境チェック（DB/Redis/ワーカー数）
├── run-batch-tests.sh          # バッチ負荷テスト実行（D1-D6, E1-E5）
└── report-summary.php          # 結果集計・レポート生成

docker-compose.loadtest.yml     # 負荷テスト環境構成
.env.loadtest                   # 負荷テスト用環境変数
```

### 8.2 k6 シナリオ実装例

#### 8.2.1 認証フロー（`scenarios/auth.js`）

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const loginTrend = new Trend('login_time');

export const options = {
  scenarios: {
    login: {
      executor: 'constant-arrival-rate',
      rate: 10,
      timeUnit: '1s',
      duration: '5m',
      preAllocatedVUs: 10,
      maxVUs: 50,
    },
  },
  thresholds: {
    login_time: ['p(95)<1000'],
    http_req_failed: ['rate<0.001'],
  },
};

export default function () {
  // 1. ログインページ取得（CSRFトークン抽出）
  const res = http.get(`${BASE_URL}/admin/login`);
  check(res, { 'login page: 200': (r) => r.status === 200 });

  const tokenMatch = res.body.match(/name="_token"[^>]*value="([^"]+)"/);
  if (!tokenMatch) return;

  // 2. ログイン実行
  const loginRes = http.post(
    `${BASE_URL}/admin/login`,
    `_token=${tokenMatch[1]}&email=${__ENV.ADMIN_EMAIL}&password=${__ENV.ADMIN_PASSWORD}`,
    { headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, redirects: 0 }
  );
  check(loginRes, { 'login: 302': (r) => r.status === 302 });
  loginTrend.add(loginRes.timings.duration);

  // 3. ダッシュボード遷移
  const dash = http.get(`${BASE_URL}/admin`);
  check(dash, { 'dashboard: 200': (r) => r.status === 200 });

  // 4. ログアウト
  http.post(`${BASE_URL}/admin/logout`, `_token=${tokenMatch[1]}`);
  sleep(1);
}
```

#### 8.2.2 チャットボットAPI（`scenarios/chatbot.js`）

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const MESSAGES = [
  '請求額の確認方法を教えてください',           // FAQ（軽量）
  '佐藤一郎の情報を教えてください',             // 入居者照会（完全一致）
  '101号室の今月の請求額はいくらですか',        // 請求額照会
  '先月の支払い状況を確認したい',               // 支払い状況
];

export const options = {
  scenarios: {
    chatbot: {
      executor: 'constant-arrival-rate',
      rate: 10,
      timeUnit: '1s',
      duration: '5m',
      preAllocatedVUs: 10,
      maxVUs: 30,
    },
  },
  thresholds: {
    'http_req_duration{scenario:chatbot}': ['p(95)<500'],
  },
};

export default function () {
  const message = MESSAGES[Math.floor(Math.random() * MESSAGES.length)];
  const res = http.post(
    `${BASE_URL}/api/chatbot/message`,
    JSON.stringify({ message, session_id: `loadtest_${__VU}` }),
    {
      headers: {
        'Content-Type': 'application/json',
        Cookie: __ENV.SESSION_COOKIE, // 事前ログインで取得したセッション
      },
      tags: { intent: 'chatbot' },
    }
  );

  check(res, {
    'chatbot: 200': (r) => r.status === 200,
    'chatbot: reply included': (r) => r.json('reply') !== null,
  });
  sleep(1);
}
```

#### 8.2.3 外部請求一括受信（`scenarios/external-invoices.js`）

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export const options = {
  scenarios: {
    bulk_import: {
      executor: 'constant-arrival-rate',
      rate: 0.05, // 3 VU相当（重い処理のため低レート）
      timeUnit: '1s',
      duration: '10m',
      preAllocatedVUs: 3,
      maxVUs: 10,
    },
  },
  thresholds: {
    'http_req_duration{scenario:bulk_import}': ['p(95)<10000'],
  },
};

export default function () {
  const invoices = [];
  for (let i = 0; i < 100; i++) {
    invoices.push({
      resident_id: __ENV.TEST_RESIDENT_ID,
      service_type: 'visiting_care',
      external_invoice_number: `VC-${__VU}-${__ITER}-${i}`,
      amount: 45000,
      tax_amount: 4500,
      tax_rate: 10.0,
      status: 'confirmed',
    });
  }

  const res = http.post(
    `${BASE_URL}/api/external-invoices`,
    JSON.stringify({
      facility_id: __ENV.TEST_FACILITY_ID,
      billing_year_month: '2026-10',
      external_system_name: 'loadtest',
      invoices,
    }),
    { headers: { 'Content-Type': 'application/json' } }
  );

  check(res, {
    'bulk import: 200': (r) => r.status === 200,
    'bulk import: success': (r) => r.json('success') === true,
  });
  sleep(5);
}
```

#### 8.2.4 PDFダウンロード（`scenarios/pdf-download.js`）

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export const options = {
  scenarios: {
    pdf_download: {
      executor: 'constant-arrival-rate',
      rate: 5,
      timeUnit: '1s',
      duration: '5m',
      preAllocatedVUs: 5,
      maxVUs: 20,
    },
  },
  thresholds: {
    'http_req_duration{scenario:pdf_download}': ['p(95)<3000'],
  },
};

export default function () {
  // invoice IDs を setup データからロード
  const invoiceId = __ENV.INVOICE_IDS.split(',')[Math.floor(Math.random() * __ENV.INVOICE_IDS.split(',').length)];
  const res = http.get(`${BASE_URL}/invoices/${invoiceId}/pdf`, {
    headers: { Cookie: __ENV.SESSION_COOKIE },
    responseType: 'binary',
  });

  check(res, {
    'pdf download: 200': (r) => r.status === 200),
    'pdf download: content-type': (r) => r.headers['Content-Type'].includes('application/pdf'),
  });
  sleep(2);
}
```

### 8.3 バッチ負荷テスト実装（`scripts/loadtest/run-batch-tests.sh`）

```bash
#!/bin/bash
# バッチ/ジョブ負荷テスト（D1-D6, E1-E5）
set -euo pipefail

APP_CONTAINER="${APP_CONTAINER:-loadtest-app-1}"
RESULTS_DIR="tests/load/results/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$RESULTS_DIR"

# D1: 月次請求一括生成（最重量）
echo "=== D1: billing:generate-monthly ===" | tee "$RESULTS_DIR/d1.log"
docker exec "$APP_CONTAINER" php artisan billing:generate-monthly \
  --year-month="$(date +%Y-%m)" \
  2>&1 | tee -a "$RESULTS_DIR/d1.log"

# D2: バックアップ
echo "=== D2: backup:database ===" | tee "$RESULTS_DIR/d2.log"
docker exec "$APP_CONTAINER" php artisan backup:database \
  --type=full --compress --retention=30 \
  2>&1 | tee -a "$RESULTS_DIR/d2.log"

# E2: ZIP一括生成（キュー経由）
echo "=== E2: GenerateMonthlyZipJob ===" | tee "$RESULTS_DIR/e2.log"
JOB_ID="loadtest_zip_$(date +%Y%m)"
docker exec "$APP_CONTAINER" php artisan tinker --execute="
    \App\Jobs\GenerateMonthlyZipJob::dispatch('$(date +%Y-%m)', null, '$JOB_ID');
"
# 進捗ポーリング（最大10分）
for i in $(seq 1 120); do
  STATUS=$(docker exec "$APP_CONTAINER" php artisan tinker --execute="
      echo json_encode(\App\Jobs\GenerateMonthlyZipJob::getProgress('$JOB_ID'));
  ")
  echo "$STATUS" | tee -a "$RESULTS_DIR/e2.log"
  echo "$STATUS" | grep -q '"status":"completed"' && break
  echo "$STATUS" | grep -q '"status":"failed"' && exit 1
  sleep 5
done

# 結果サマリ出力
php scripts/loadtest/report-summary.php "$RESULTS_DIR"
```

### 8.4 環境チェックスクリプト（`scripts/loadtest/check-environment.php`）

```php
<?php
// 負荷テスト前の環境チェック
$checks = [
    'DB_CONNECTION' => fn () => env('DB_CONNECTION') === 'pgsql',
    'QUEUE_CONNECTION' => fn () => env('QUEUE_CONNECTION') === 'redis',
    'SESSION_DRIVER' => fn () => env('SESSION_DRIVER') === 'redis',
    'CACHE_STORE' => fn () => env('CACHE_STORE') === 'redis',
    'MAIL_MAILER' => fn () => env('MAIL_MAILER') === 'log',
    'APP_DEBUG' => fn () => env('APP_DEBUG') === false || env('APP_DEBUG') === 'false',
];

$failed = [];
foreach ($checks as $name => $check) {
    if (! $check()) {
        $failed[] = $name;
    }
}

if ($failed) {
    fwrite(STDERR, "環境チェック失敗: " . implode(', ', $failed) . PHP_EOL);
    fwrite(STDERR, ".env.loadtest の設定を確認してください" . PHP_EOL);
    exit(1);
}

echo "環境チェックOK" . PHP_EOL;
```

---

## 9. テストデータ設計

### 9.1 データ規模（本番相当）

| データ | 規模 | 算出根拠 |
|--------|------|----------|
| 施設 | 10 | マルチテナント想定 |
| 入居者 | 1,000（100/施設） | 施設定員の上限（`resident_capacity: 100_200` 帯） |
| 日々課金 | 約18万件（6ヶ月×1,000名×3-5件） | 実運用1ヶ月分の6倍 |
| 月次請求 | 6,000件（6ヶ月×1,000名） | 過去6ヶ月分 |
| 介護サービス請求 | 6,000件 | 8サービス種別からランダム |
| チャットログ | 10万件 | 90日保持の実態 |
| チャットボットFAQ | 100件 | 運用実態 |
| トライアル | 1,000件 | リード蓄積 |
| アクティビティログ | 10万件 | 監査ログ蓄積 |

### 9.2 データ生成スクリプト（`scripts/loadtest/generate-data.php`）

既存 `scripts/load_test.php` のデータ生成部（施設/入居者/日々課金の一括 insert）を流用し、以下を追加する。

1. **PostgreSQL対応**: SQLite固有の記述（`SET FOREIGN_KEY_CHECKS` 等）を除去
2. **チャットログ生成**: `ChatLog` モデルで10万件をチャンク insert
3. **介護サービス請求生成**: `ServiceInvoice` で8種別をランダム割当
4. **トライアル/予約データ**: `Trial`/`Booking`/`FormSubmission` を生成
5. **PDFキャッシュ事前生成**: `storage/app/invoices/{yearMonth}/` にキャッシュPDFを配置（S07のキャッシュ済みシナリオ用）

```php
// チャンク処理の例（既存スクリプトのパターン踏襲）
foreach (array_chunk($chatLogs, 1000) as $chunk) {
    ChatLog::insert($chunk);
}
```

### 9.3 データ生成の実行順序

1. 施設 → 2. 管理者ユーザー → 3. 品目マスタ → 4. 入居者 → 5. 日々課金 → 6. 月次請求（`InvoiceCalculationService` 経由）→ 7. 介護サービス請求 → 8. チャットログ/FAQ → 9. トライアル/予約 → 10. PDFキャッシュ生成

---

## 10. 実施手順・スケジュール

### 10.1 実施ステップ

| # | ステップ | 内容 | 工数 |
|---|----------|------|------|
| 1 | 環境構築 | `docker-compose.loadtest.yml` 構築、`.env.loadtest` 設定 | 0.5日 |
| 2 | データ生成 | `generate-data.php` 実行、件数確認 | 0.5日 |
| 3 | スクリプト実装 | k6シナリオ全28種の実装 | 2日 |
| 4 | Phase 0-1 | スモーク+ベースライン | 0.5日 |
| 5 | Phase 2 | 負荷テスト（想定負荷30分） | 0.5日 |
| 6 | Phase 3 | ストレステスト | 0.5日 |
| 7 | Phase 4 | 耐久テスト（4時間） | 0.5日 |
| 8 | Phase 5-6 | バッチ/混合負荷 | 1日 |
| 9 | レポート作成 | 結果分析・ボトルネック特定・改善提案 | 1日 |

**総工数: 約7.5日**

### 10.2 実施上の注意事項

1. **本番データベースへの接続禁止**: テスト用DBを必ず使用（`DB_DATABASE=seikyu_loadtest`）
2. **テストデータの識別**: 全テストデータに `loadtest` プレフィックスを付与（クリーンアップ容易化）
3. **実施時間帯**: 他の検証と重複しない時間帯に実施
4. **モニタリング同時実施**: `docker stats`、`pg_stat_statements`、Laravelログを同時収集
5. **失敗時のロールバック**: 各フェーズ前にDBスナップショット取得

---

## 11. リスクと対策

| # | リスク | 影響 | 対策 |
|---|--------|------|------|
| R1 | SQLiteで実施してしまう | 同時書き込みロックで性能が実態より著しく悪化 | 環境チェックスクリプトで `DB_CONNECTION=pgsql` を強制検証 |
| R2 | DB接続プール（max 3）の枯渇 | 接続待ちによるタイムアウト大量発生 | プールサイズを段階的に増やし比較計測、`pg_stat_activity` で接続数を監視 |
| R3 | FrankenPHPワーカー数の制約 | 同時処理が逐次化されスループットが出ない | workers=1/4/8 で比較計測 |
| R4 | PDF生成のCPU飽和 | p95がSLAを大幅に超過 | キュー投入方式（`GenerateInvoicePdfJob`）への切り替え検討、キャッシュ活用率の計測 |
| R5 | レート制限による429大量発生 | エラー率が目標を超過 | IP分散（X-Forwarded-For）＋429は別指標として計測 |
| R6 | チャットボットファジー検索の全件スキャン | 入居者数増大時にO(n)悪化 | 入居者100/1,000/5,000名でのスケーラビリティ計測 |
| R7 | テストデータ生成の長時間化 | 準備に時間がかかる | チャンクinsert（既存スクリプトのパターン踏襲）、生成済みデータの再利用 |
| R8 | セッションファイルの肥大化 | 耐久テスト中のディスク枯渇 | SESSION_DRIVER=redis を強制 |
| R9 | キューの滞留 | ジョブ処理が追いつかない | queue:work を2プロセス起動、滞留数を計測 |
| R10 | 個人情報の取扱い | テストデータに実データが混入 | 全データを架空データ（`loadtest` プレフィックス）で生成 |

---

## 12. 成果物・判定基準

### 12.1 成果物

| # | 成果物 | 内容 |
|---|--------|------|
| 1 | `tests/load/` | k6シナリオ全28種 |
| 2 | `scripts/loadtest/` | データ生成・環境チェック・バッチ実行・レポート生成スクリプト |
| 3 | `docker-compose.loadtest.yml` | 負荷テスト環境構成 |
| 4 | `tests/load/results/` | 生結果（k6 JSON出力、バッチログ） |
| 5 | `LOAD_TEST_REPORT.md` | 負荷テストレポート（§12.2の形式） |

### 12.2 レポート形式（`LOAD_TEST_REPORT.md`）

```markdown
# 負荷テストレポート
- 実施日: YYYY-MM-DD
- 環境: 構成（workers数、DB接続プールサイズ等）
- データ規模: §9.1 の実測値

## 結果サマリ
| シナリオ | p50 | p95 | p99 | RPS | エラー率 | SLA | 判定 |

## ボトルネック分析
- DB: 上位クエリ（pg_stat_statements）
- CPU/メモリ: 時系列グラフ
- キュー: 滞留数の推移

## 改善提案
- 優先度付きの改善項目リスト
```

### 12.3 判定基準

| 判定 | 条件 |
|------|------|
| **合格** | 全SLA達成、エラー率<0.1%、システム指標が目標内 |
| **条件付き合格** | 一部SLA未達だが原因が特定でき、改善案が提示できた |
| **不合格** | エラー率≥1%、またはシステム障害が発生 |

---

## 13. 既存スクリプトとの統合方針

| 既存資産 | 統合方法 |
|----------|----------|
| `scripts/load_test.php` | サービス層直接計測を `tests/load/service-level/` として残し、HTTP負荷テスト（k6）と併用。PostgreSQL対応に改修 |
| 既存ファクトリ/シーダー（`database/seeders/`） | `generate-data.php` から呼び出し可能であれば流用 |
| 既存テスト（461件） | 負荷テスト実施前に全テスト通過を必須条件とする（回帰防止） |

---

## 14. 完了条件（Definition of Done）

- [ ] 負荷テスト環境（docker-compose）構築完了
- [ ] テストデータ生成スクリプト完了（§9.1の規模）
- [ ] k6シナリオ全28種実装完了
- [ ] Phase 0-6 全フェーズ実施完了
- [ ] レポート（`LOAD_TEST_REPORT.md`）作成完了
- [ ] ボトルネック特定と改善提案の提示
- [ ] 既存461テストの継続通過確認
