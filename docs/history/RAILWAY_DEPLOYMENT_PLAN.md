# 施設請求管理システム - Railway 公開 詳細実装計画書

**バージョン**: 1.0  
**作成日**: 2026-10-08  
**対象プロジェクト**: `E:\seikyu` (Laravel 11/12 + Filament 3.2)  
**デプロイ対象**: Railway (Docker + FrankenPHP)

---

## 1. プロジェクト概要

### 1.1 アプリケーション構成
| 項目 | 内容 |
|------|------|
| フレームワーク | Laravel 11.x / 12.x |
| 管理パネル | Filament 3.2 |
| PHP バージョン | 8.2+ (Docker では 8.3 使用) |
| データベース | SQLite (開発) / PostgreSQL (本番) |
| PDF 生成 | barryvdh/laravel-dompdf (Dompdf) |
| QRコード | bacon/bacon-qr-code |
| 日本語フォント | Noto Sans JP (Google Fonts) |
| スケジューラー | Laravel Scheduler (日次ジョブ) |
| キュージョブ | 同期 (本番では database/redis 推奨) |

### 1.2 主要機能
- **入居者管理**: 入居/退去、部屋割り当て、契約情報
- **請求管理**: 月次請求書生成、家賃/管理費/自費サービス計算
- **PDF生成**: 請求書・領収書 (日本語対応、QRコード付き)
- **CSV出力**: 請求一覧、会計仕訳連携 (freee/MF/弥生/勘定奉行)
- **施設管理**: 複数施設対応、施設ごとの設定・銀行情報
- **トライアル管理**: 無料トライアル申込み、期限通知、ナーチャリングメール

### 1.3 ストレージ要件
| 用途 | パス | 永続化要否 |
|------|------|------------|
| PDFキャッシュ | `storage/app/invoices/{YYYY-MM}/` | **必須** (再生成コスト大) |
| ZIP一時ファイル | `storage/app/temp/` | 推奨 |
| 公開ファイル | `public/storage/` → `storage/app/public/` | **必須** (印影画像等) |
| フォントキャッシュ | `storage/fonts/` | 推奨 |
| ログ | `storage/logs/` | 推奨 |

---

## 2. 事前準備 (ローカル環境)

### 2.1 動作確認
```bash
cd E:\seikyu

# 依存関係確認
composer check-platform-reqs

# テスト実行
vendor/bin/pest --parallel

# 静的解析 (設定されている場合)
# vendor/bin/phpstan analyse

# ローカルビルド確認
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
```

### 2.2 Git リポジトリ準備
```bash
# デプロイ用ブランチ作成
git checkout -b deploy/railway-production

# 不要ファイルの除外確認 (.gitignore)
cat .gitignore

# コミット・プッシュ
git add .
git commit -m "chore: Add Railway deployment configuration"
git push origin deploy/railway-production
```

---

## 3. 作成済みデプロイ設定ファイル

以下のファイルをプロジェクトルート (`E:\seikyu`) に作成済み:

| ファイル | 説明 |
|---------|------|
| `Dockerfile` | PHP 8.3 + FrankenPHP + 必要拡張 + Notoフォント自動インストール |
| `Caddyfile` | FrankenPHP用Webサーバー設定 (セキュリティヘッダー含む) |
| `railway.json` | Railway デプロイ設定 (Dockerfileビルド、ヘルスチェック) |
| `.dockerignore` | ビルド除外パターン (vendor, storage, logs, docs等) |
| `.env.production.example` | 本番環境変数テンプレート (Railway固有設定含む) |

---

## 4. Railway プロジェクト作成手順

### 4.1 プロジェクト作成
1. https://railway.app にログイン
2. "New Project" → "Deploy from GitHub repo"
3. リポジトリ選択
4. **重要**: Root Directory を `/` (プロジェクトルート) に設定
5. "Deploy" クリック

### 4.2 PostgreSQL データベース追加
1. プロジェクトダッシュボードで "+" → "Database" → "PostgreSQL"
2. 自動的に以下の環境変数が注入される:
   - `DATABASE_URL` (推奨: これを使用)
   - または `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`

### 4.3 Redis 追加 (推奨: キャッシュ・キュー・セッション用)
1. "+" → "Database" → "Redis"
2. 自動的に `REDIS_URL` 等が注入される

### 4.4 永続ボリューム追加 (必須: PDFキャッシュ・印影画像用)
1. "+" → "Storage" → "Volume"
2. 設定:
   - Name: `storage`
   - Size: 1 GB (無料枠内)
   - Mount Path: `/app/storage`
3. これにより以下が永続化される:
   - `storage/app/invoices/` (PDFキャッシュ)
   - `storage/app/public/` (印影画像等)
   - `storage/logs/`
   - `storage/framework/`

---

## 5. 環境変数設定 (Railway ダッシュボード)

### 5.1 必須変数 (手動設定)
| 変数名 | 値 | 備考 |
|--------|----|------|
| `APP_KEY` | `base64:xxx...` | `php artisan key:generate --show` で生成 |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | |
| `APP_URL` | `https://xxx.up.railway.app` | デプロイ後に割り当てられるURL |
| `LOG_CHANNEL` | `stderr` | Railwayログ収集用 |

### 5.2 施設情報 (必須: 実際の値に書き換え)
| 変数名 | 例 | 備考 |
|--------|-----|------|
| `FACILITY_NAME` | "ケアレジデンス ひまわり" | |
| `FACILITY_OPERATOR` | "株式会社ひまわりケア" | |
| `FACILITY_POSTAL_CODE` | "123-4567" | |
| `FACILITY_ADDRESS` | "東京都新宿区西新宿 1-2-3" | |
| `FACILITY_PHONE` | "03-1234-5678" | |
| `FACILITY_FAX` | "03-1234-5679" | |
| `FACILITY_EMAIL` | "info@care-himawari.jp" | |
| `FACILITY_INVOICE_NUMBER` | "T1234567890123" | **T + 13桁数字** (バリデーションあり) |
| `FACILITY_BANK_NAME` | "三菱UFJ銀行" | |
| `FACILITY_BANK_BRANCH` | "新宿支店" | |
| `FACILITY_BANK_TYPE` | "普通" | |
| `FACILITY_BANK_NUMBER` | "1234567" | |
| `FACILITY_BANK_HOLDER` | "カ）ヒマワリケア" | |
| `FACILITY_DEBIT_DAY` | "27" | 1-31 |
| `FACILITY_TRANSFER_DUE_DAYS` | "30" | |

### 5.3 自動設定される変数 (確認のみ)
- `DATABASE_URL` / `PG*` 変数: PostgreSQL追加で自動
- `REDIS_URL`: Redis追加で自動
- `PORT=8000`: Railwayが自動設定
- `RAILWAY_STATIC_URL`: デプロイ後に自動設定

### 5.4 推奨設定 (パフォーマンス向上)
| 変数名 | 値 | 条件 |
|--------|----|------|
| `CACHE_STORE` | `redis` | Redis追加時 |
| `QUEUE_CONNECTION` | `redis` | Redis追加時、非同期ジョブ使用時 |
| `SESSION_DRIVER` | `redis` | Redis追加時、複数レプリカ時 |
| `FILESYSTEM_DISK` | `local` | ボリュームマウント時はこのまま |

---

## 6. デプロイ実行と初期化

### 6.1 初回デプロイ監視
1. Railway "Deployments" タブでビルドログ監視
2. ビルドステップ:
   - Docker イメージビルド (~3-5分)
   - Composer install
   - Noto Sans JP フォントダウンロード
   - Laravel 最適化コマンド実行
   - 起動

### 6.2 デプロイ後初期化 (Railway Shell で実行)
```bash
# Railway ダッシュボードの "Shell" タブを開く

# 1. マイグレーション実行
php artisan migrate --force

# 2. 初期データ投入 (必要に応じて)
php artisan db:seed --force

# 3. キャッシュクリア・再生成
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. 管理ユーザー作成 (初回のみ)
php artisan tinker
>>> \App\Models\User::factory()->create([
        'name' => 'Administrator',
        'email' => 'admin@your-domain.com',
        'password' => bcrypt('secure-password'),
        'is_admin' => true,
    ]);
```

### 6.3 動作確認
1. `https://xxx.up.railway.app` にアクセス
2. `/admin` にリダイレクトされ、ログイン画面表示されるか確認
3. 管理ユーザーでログイン
4. 以下の機能を確認:
   - 入居者一覧・登録
   - 月次請求一覧・PDF生成・ダウンロード
   - CSV エクスポート (請求一覧・会計仕訳)
   - 施設設定画面
   - PDFテンプレート設定

---

## 7. プロジェクト固有の注意事項

### 7.1 設定バリデーション (AppServiceProvider::validateFacilityConfig)
起動時に以下のバリデーションが実行されるため、環境変数は**必ず正しく設定**すること:
- `FACILITY_INVOICE_NUMBER`: `^T\d{13}$` 形式 (T + 13桁数字)
- 銀行情報: `name`, `account_number`, `account_holder` 必須

### 7.2 PDF生成の日本語フォント
- Dockerfile で Noto Sans JP を自動ダウンロード・配置済み
- `resources/fonts/noto-sans-jp/` に配置
- DomPDF の `font_dir` と `font_cache` は `storage/fonts/` を使用

### 7.3 PDFキャッシュ機能
`InvoicePdfService` に以下のキャッシュ機能あり:
- `getOrGenerateCachedPdf()`: アトミック書き込みで競合回避
- キャッシュパス: `storage/app/invoices/{YYYY-MM}/invoice_{id}.pdf`
- **ボリュームマウント必須** (再生成コストが高いため)

### 7.4 非同期ジョブ (キュー)
以下のジョブが実装済み:
- `GenerateMonthlyZipJob`: 月次請求書一括ZIP生成 (重い処理)
- `GenerateInvoicePdfJob`: 単体PDF生成
- `CheckAndNotifyTrialExpiries`: 日次スケジュール (午前9時)
- `SendTrialNurtureEmails`: 日次スケジュール (午前10時)

**本番では `QUEUE_CONNECTION=redis` または `database` 推奨** (同期だとタイムアウトリスク)

### 7.5 スケジューラー
`bootstrap/app.php` で日次ジョブ登録済み。
Railway では以下のいずれかで実行:
- **方法A**: 別サービスとして `php artisan schedule:work` 実行 (推奨)
- **方法C**: Cronジョブで `php artisan schedule:run` を毎分実行

### 7.6 マルチファシリティ対応
- `facilities` テーブルで複数施設管理
- `Facility::current()` で現在の施設取得 (サブドメイン/パス/セッション等で判定)
- 環境変数 `FACILITY_*` はデフォルト/フォールバック用

### 7.7 印影画像アップロード
- `PdfTemplateSetting` で印影画像アップロード機能あり
- `public/storage` → `storage/app/public` シンボリックリンク作成済み (Dockerfileで実行)
- ボリュームマウントで永続化

---

## 8. トラブルシューティング

### 8.1 よくあるエラーと対処

| エラー | 原因 | 対処 |
|--------|------|------|
| `RuntimeException: 施設登録番号の形式が不正です` | `FACILITY_INVOICE_NUMBER` 形式不正 | `T` + 13桁数字で設定 |
| `RuntimeException: 銀行情報に必須項目が不足しています` | 銀行環境変数不足 | `FACILITY_BANK_*` すべて設定 |
| `SQLSTATE[08006] could not connect to server` | DB接続失敗 | PostgreSQL が Healthy か確認、環境変数確認 |
| `Permission denied: storage/framework/views` | ストレージ権限不足 | Dockerfile で chmod 済み、ボリュームマウント確認 |
| `Manifest file not found` | Viteビルド未実行 | このプロジェクトは Vite 不使用 (Filament標準) |
| `Class "BaconQrCode\..." not found` | QRコード拡張不足 | composer.json に `bacon/bacon-qr-code` 含む |

### 8.2 デプロイ失敗時のデバッグ
```bash
# Railway Shell で実行
php artisan --version
php artisan migrate --force
php artisan config:cache
```

### 8.3 ログ確認
- Railway ダッシュボード "Logs" タブ
- `storage/logs/laravel.log` (ボリュームマウント時)

---

## 9. 運用・保守

### 9.1 定期メンテナンス (月1回推奨)
```bash
# ローカルで実行後テスト、問題なければプッシュ
composer update
# テスト実行
vendor/bin/pest --parallel
# プッシュで自動デプロイ (Railway 設定による)
```

### 9.2 バックアップ
| 対象 | 方法 | 頻度 |
|------|------|------|
| PostgreSQL | Railway 自動バックアップ / `pg_dump` | 日次 |
| ストレージボリューム | Railway スナップショット / ファイルコピー | 週次 |
| 環境変数 | Railway ダッシュボードからエクスポート | 変更時 |

### 9.3 スケーリング
- **垂直**: Railway プランアップグレード (メモリ/CPU増強)
- **水平**: `numReplicas` 増加 (有料プラン、Redis/DB共有必須)
- **キュー**: 別ワーカーサービスとして `php artisan queue:work` 実行推奨

### 9.4 モニタリング
- Railway "Metrics" タブ: CPU, Memory, Network, Disk
- アプリケーションログ: `stderr` 出力を Railway が収集
- ヘルスチェック: `/up` エンドポイント (Laravel標準)

---

## 10. 成功基準 (Definition of Done)

### 機能要件
- [ ] `https://xxx.up.railway.app` で HTTPS アクセス可能
- [ ] `/admin` で Filament 管理パネルログイン可能
- [ ] 入居者 CRUD 操作正常
- [ ] 月次請求生成・一覧表示正常
- [ ] 請求書 PDF 生成・ダウンロード・ストリーミング正常
- [ ] 領収書 PDF 生成正常
- [ ] 月次一括 ZIP 生成 (同期・非同期両方) 正常
- [ ] 請求一覧 CSV エクスポート正常
- [ ] 会計仕訳 CSV エクスポート正常 (freee/MF/弥生/勘定奉行)
- [ ] 施設設定・銀行情報編集正常
- [ ] PDFテンプレート設定 (印影アップロード含む) 正常
- [ ] 税率設定正常
- [ ] トライアル申込み・管理正常

### パフォーマンス要件
- [ ] 初期ロード 3秒以内
- [ ] 管理パネルページ遷移 2秒以内
- [ ] PDF生成 (単体) 5秒以内
- [ ] 月次一括 ZIP (50件) 60秒以内

### 運用要件
- [ ] デプロイ手順文書化済み
- [ ] ロールバック手順確立 (Railway UI でワンクリック)
- [ ] バックアップ戦略定義済み
- [ ] 環境変数管理運用確立

---

## 11. 変更履歴

| バージョン | 日付 | 変更内容 |
|------------|------|----------|
| 1.0 | 2026-10-08 | 初版作成 (プロジェクト実態に合わせて更新) |

---

## 付録: 重要コマンドリファレンス

### Railway CLI (オプション)
```bash
# インストール
npm i -g @railway/cli

# ログイン
railway login

# プロジェクトリンク
railway link

# シェル接続
railway shell

# 環境変数一覧
railway variables

# デプロイ履歴
railway deployments

# ログ表示
railway logs
```

### 便利な artisan コマンド
```bash
# キャッシュ全クリア
php artisan optimize:clear

# 全キャッシュ再生成
php artisan optimize

# マイグレーション状態確認
php artisan migrate:status

# キュー監視
php artisan queue:monitor

# スケジューラー手動実行
php artisan schedule:run

# ストレージリンク再作成
php artisan storage:link
```