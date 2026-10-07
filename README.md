# 高齢者施設請求管理システム **あんしん ver1.0**

高齢者施設の月次請求・領収書発行、入居者管理、日々の利用料管理を一元化するシステムです。Laravel 12 + Filament v3 で構築されています。

## バージョン情報

**現在のバージョン: v1.0 "あんしん" (2026-10-07)**

- 全 109 テスト通過（862 アサーション）
- データ整合性・リソース管理の重大バグ修正済み
- 本番デプロイ可能な安定版

---

## 機能概要

- **入居者管理** — 部屋番号、基本家賃・管理費、入居/退居日、ステータス管理
- **請求項目管理** — 家賃・管理費以外の請求項目（立替金・日用品など）の既定金額管理
- **日次利用料管理** — 日ごとの利用料記録（請求項目 × 数量 × 金額）
- **月次請求書生成** — 月次請求額の自動集計・請求書発行（`InvoiceCalculationService`）
- **PDF発行** — 請求書・領収書のPDF生成、月次ZIP一括ダウンロード（`InvoicePdfService`）
- **CSVエクスポート** — 月別リストCSV / 会計仕訳CSV（`InvoiceCsvExportService`、未請求は会計仕訳から除外）
- **入金管理** — 支払方法の記録、請求書ステータス遷移（Unbilled → Billed → Paid）

## 技術スタック

| 項目 | 内容 |
| --- | --- |
| PHP | 8.3+ |
| Laravel | 12.x |
| Filament | v3（管理パネル） |
| PDF | dompdf/dompdf |
| テスト | Pest（SQLite `:memory:`） |
| DB | 本番: MySQL/SQLite / テスト: SQLite インメモリ |

## セットアップ手順

```bash
# 1. 依存関係のインストール
composer install

# 2. 環境変数の設定
copy .env.example .env
php artisan key:generate

# 3. マイグレーション & シード
php artisan migrate --seed

# 4. 開発サーバー起動
php artisan serve
```

Windows 環境では `start-project.bat` を使用することで PHP/Composer のパス設定込みで起動できます。

管理画面: `http://localhost:8000/admin`（`is_admin = true` のユーザーのみアクセス可能）

### 施設情報の設定

`.env` で以下を設定してください。起動時（`AppServiceProvider::validateFacilityConfig`）に妥当性検証されます。

- `FACILITY_NAME`, `FACILITY_OPERATOR`, `FACILITY_POSTAL_CODE`, `FACILITY_ADDRESS`, `FACILITY_PHONE`, `FACILITY_FAX`, `FACILITY_EMAIL`
- `FACILITY_INVOICE_NUMBER` — 登録番号（`T` + 13桁、例: `T1234567890123`）
- `FACILITY_BANK_*` — 銀行口座情報（振込先として請求書PDFに表示）
- `FACILITY_DEBIT_DAY`, `FACILITY_TRANSFER_DUE_DAYS`

## テスト

```bash
# 全テスト実行（SQLite :memory:）
php artisan test
# または
vendor\bin\pest
```

テストスイート構成:

- `tests/Unit` — モデル単体テスト（DailyCharge, MonthlyInvoice, Resident 等）
- `tests/Feature` — サービス・Filament・ルートの統合テスト
- `tests/Feature/Regression` — リグレッションテスト（税計算・按分・ロック・境界値）

**v1.0 時点で 109 テスト / 862 アサーションが全て通過しています。**

## ディレクトリ構成（主要ファイル）

```
app/
├── Enums/            InvoiceStatus, ResidentStatus, PaymentMethod
├── Filament/Resources/  Resident / ChargeItem / MonthlyInvoice
├── Models/           Resident, ChargeItem, DailyCharge, MonthlyInvoice, User
├── Providers/        AppServiceProvider（施設設定検証）
└── Services/
    ├── InvoiceCalculationService.php   月次請求集計
    ├── InvoicePdfService.php           PDF生成・ダウンロード・ZIP
    └── InvoiceCsvExportService.php     CSVエクスポート
database/migrations/   入居者・請求項目・日次利用料・月次請求書テーブル
```

## 主な実装済み機能（v1.0 完了範囲）

### データ整合性・リソース管理
- 月次請求生成の対象住民フィルタ修正（月途中入居・退去も正しく対象に含める）
- PDF ZIP生成時の一時ディレクトリ確実なクリーンアップ（`try/finally`）
- MonthlyInvoice Observer 削除による二重計算防止、モデル `booted` への集約
- 管理画面アクセス制御を `is_admin === true` のみに限定

### 請求計算・PDF・CSV
- 月次請求集計ログ出力（開始・完了）
- PDF生成ロジック統合（`generatePdfFromInvoice`）、専用コントローラ削除
- 領収書番号フォーマット `%04d`（9999件まで対応）
- 会計仕訳CSVは Billed/Paid ステータスのみ対象

### バリデーション・UX
- 退去日バリデーション（入居日以降必須）
- 施設設定の起動時検証（インボイス登録番号・銀行情報）
- Resident モデル `$fillable` 明示化

### データベース
- `residents.move_in_date` / `move_out_date` インデックス追加
- `users.is_admin` nullable 対応
- 日付比較を 'Y-m-d H:i:s' 形式に統一（境界値取りこぼし防止）
- `monthly_invoices` に `version` / 税関連カラム追加

## デプロイ

デプロイ手順・チェックリスト・ロールバック手順は [DEPLOYMENT.md](DEPLOYMENT.md) を参照してください。

変更履歴は [CHANGELOG.md](CHANGELOG.md) を参照してください。

---

## ライセンス

MIT License