# 高齢者施設請求管理システム **あんしん ver1.2**

高齢者施設の月次請求・領収書発行、入居者管理、日々の利用料管理を一元化するシステムです。Laravel 12 + Filament v3 で構築されています。

## バージョン情報

**現在のバージョン: v1.2 "あんしん" (2026-10-08)**

- 全 109+ テスト通過（862+ アサーション）
- データ整合性・リソース管理の重大バグ修正済み
- 本番デプロイ可能な安定版
- 施設管理・税率設定・PDFテンプレート設定機能を追加
- 複数施設対応の基盤整備
- **セルフサーブ・トライアル機能（体験版・導線）を追加**
- **商用ウェブサイト（フェーズ1）完成**
- **PDFデザインシステム刷新・印影対応**

---

## 機能概要

- **入居者管理** — 部屋番号、基本家賃・管理費、入居/退居日、ステータス管理
- **請求項目管理** — 家賃・管理費以外の請求項目（立替金・日用品など）の既定金額管理
- **日次利用料管理** — 日ごとの利用料記録（請求項目 × 数量 × 金額）
- **月次請求書生成** — 月次請求額の自動集計・請求書発行（`InvoiceCalculationService`）
- **PDF発行** — 請求書・領収書のPDF生成、月次ZIP一括ダウンロード（`InvoicePdfService`）
- **CSVエクスポート** — 月別リストCSV / 会計仕訳CSV（`InvoiceCsvExportService`、未請求は会計仕訳から除外）
- **入金管理** — 支払方法の記録、請求書ステータス遷移（Unbilled → Billed → Paid）

---

## 管理画面デモ / スナップショット

> **注意**: 以下の画像はプレースホルダです。実際のスクリーンショットに差し替えてご利用ください。
> 撮影手順: `php artisan serve` 後、`http://localhost:8000/admin` にアクセスし、各リソース画面でスクリーンショットを撮影して `docs/screenshots/` 以下に保存してください。

### 1. 入居者管理 (`ResidentResource`)

| 画面 | 説明 |
|------|------|
| ![入居者一覧](docs/screenshots/residents-index.png) | **一覧画面** — 部屋番号・氏名・ステータス・家賃・管理費をテーブル表示。ステータスバッジ（入居中/退去済/予約中）で即座に把握。フィルタで「入居中のみ」「退去済みのみ」切替可能。 |
| ![入居者作成](docs/screenshots/residents-create.png) | **作成/編集モーダル** — 基本情報（氏名・部屋番号・家賃・管理費）、入居日/退去日、ステータスを一括入力。退去日は入居日以降のみ入力可（バリデーション内蔵）。 |
| ![入居者詳細](docs/screenshots/residents-view.png) | **詳細画面** — 入居者の基本情報、関連する日次利用料、月次請求書の履歴をタブ切替で確認。 |

**主な Filament 機能**: Table Bulk Actions（一括ステータス変更）、インライン編集、リレーションマネージャ（日次利用料・月次請求書）

---

### 2. 請求項目管理 (`ChargeItemResource`)

| 画面 | 説明 |
|------|------|
| ![請求項目一覧](docs/screenshots/charge-items-index.png) | **一覧画面** — 項目名・単価・税区分（課税/非課税）・施設紐付けを表示。施設ごとの既定単価を管理。 |
| ![請求項目作成](docs/screenshots/charge-items-create.png) | **作成/編集フォーム** — 名称、単価、税区分、施設選択。施設を選ぶとその施設専用の項目として登録。 |

**用途**: 立替金、日用品、レクリエーション費、医療費等の定型外請求項目をマスタ管理

---

### 3. 日次利用料管理 (`DailyChargeResource`)

| 画面 | 説明 |
|------|------|
| ![日次利用料一覧](docs/screenshots/daily-charges-index.png) | **一覧画面** — 日付・入居者・請求項目・数量・金額・税額を表示。月別フィルタ・入居者フィルタで絞込み。 |
| ![日次利用料作成](docs/screenshots/daily-charges-create.png) | **作成フォーム** — 入居者選択 → 請求項目選択（単価自動反映）→ 数量入力 → 金額自動計算。複数行一括登録対応。 |

**特徴**: 請求項目の単価を参照し、数量変更時に金額・税額をリアルタイム再計算

---

### 4. 月次請求書生成 (`MonthlyInvoiceResource`)

| 画面 | 説明 |
|------|------|
| ![月次請求書一覧](docs/screenshots/monthly-invoices-index.png) | **一覧画面** — 請求月・入居者・請求額・税額・ステータス（未請求/請求済/入金済）を表示。ステータス別集計サマリー付き。 |
| ![請求書生成アクション](docs/screenshots/monthly-invoices-generate.png) | **生成アクション** — 「請求書生成」ボタンで対象月・施設を指定し一括生成。生成ログ（件数・合計額）をトースト通知。 |
| ![請求書詳細](docs/screenshots/monthly-invoices-view.png) | **詳細画面** — 明細内訳（家賃・管理費・日次利用料・税額）、PDFダウンロード、ステータス遷移ボタン（請求確定/入金記録）。 |

**バックエンド**: `InvoiceCalculationService` が月途中入居/退去を日割り計算、税率は `TaxSetting` を参照

---

### 5. PDF発行・ZIP一括ダウンロード (`InvoicePdfService`)

| 画面 | 説明 |
|------|------|
| ![PDFプレビュー](docs/screenshots/pdf-preview.png) | **請求書PDFプレビュー** — 施設名・登録番号・請求先・明細・合計・振込先銀行情報をレイアウト。インボイス制度対応（登録番号・税率別内訳表示）。 |
| ![領収書PDF](docs/screenshots/receipt-preview.png) | **領収書PDF** — 領収書番号（`%04d` 連番）、但書（家賃等）、収入印紙欄（5万円以上対応）。 |
| ![ZIPダウンロード](docs/screenshots/pdf-zip-download.png) | **月次ZIP一括ダウンロード** — 選択月の全入居者分を1つのZIPにまとめてダウンロード。ファイル名は `請求書_施設名_YYYYMM_入居者名.pdf` 形式。 |

**技術詳細**: `dompdf` + 日本語フォント（Noto Sans JP）、一時ディレクトリは `try/finally` で確実クリーンアップ

---

### 6. CSVエクスポート (`InvoiceCsvExportService`)

| 画面 | 説明 |
|------|------|
| ![月別リストCSV](docs/screenshots/csv-monthly-list.png) | **月別リストCSV** — 入居者ごとの請求額内訳を横持ち出力。会計ソフト取込用フォーマット。 |
| ![会計仕訳CSV](docs/screenshots/csv-accounting.png) | **会計仕訳CSV** — 複式簿記対応の仕訳行（借方/貸方・勘定科目・金額・税区分）を出力。**Billed/Paid のみ対象**（未請求は除外）。 |

**出力例**: `docs/samples/monthly_list_2026-10.csv`, `docs/samples/accounting_2026-10.csv`

---

### 7. 入金管理（ステータス遷移）

| 画面 | 説明 |
|------|------|
| ![ステータス遷移](docs/screenshots/payment-status-flow.png) | **ステータス遷移フロー** — `Unbilled` → `Billed`（請求確定） → `Paid`（入金確認）。各遷移で確認モーダル表示。 |
| ![入金記録モーダル](docs/screenshots/payment-record.png) | **入金記録モーダル** — 支払日・支払方法（現金/振込/口座振替/その他）・備考を入力。`Paid` 遷移時に自動記録。 |

---

### 8. 施設管理 (`FacilityResource`) — **v1.1 新機能**

| 画面 | 説明 |
|------|------|
| ![施設一覧](docs/screenshots/facilities-index.png) | **一覧画面** — 施設名・運営者・所在地・電話・インボイス登録番号・銀行口座を一覧。 |
| ![施設作成](docs/screenshots/facilities-create.png) | **作成/編集フォーム** — 基本情報、インボイス登録番号（T+13桁バリデーション）、銀行口座（銀行名・支店・種別・番号・名義）。 |

**役割**: 複数施設運営の基盤。請求書PDFのヘッダー情報・振込先として使用。

---

### 9. 税率設定 (`TaxSettingResource`) — **v1.1 新機能**

| 画面 | 説明 |
|------|------|
| ![税率一覧](docs/screenshots/tax-settings-index.png) | **一覧画面** — 税率・適用開始日・適用終了日・施設を表示。期間重複チェック機能付き。 |
| ![税率作成](docs/screenshots/tax-settings-create.png) | **作成フォーム** — 税率（%）、適用期間、施設選択。終了日未入力で「現在適用中」扱い。 |

**連携**: `InvoiceCalculationService` が請求月時点での有効税率を自動選択

---

### 10. PDFテンプレート設定 (`PdfTemplateSettingResource`) — **v1.1 新機能**

| 画面 | 説明 |
|------|------|
| ![テンプレート一覧](docs/screenshots/pdf-templates-index.png) | **一覧画面** — テンプレート名・種別（請求書/領収書）・施設・デフォルトフラグを表示。 |
| ![テンプレート編集](docs/screenshots/pdf-templates-edit.png) | **編集画面** — Blade テンプレートコードをエディタで編集（シンタックスハイライト付き）。プレビューボタンで即時確認。 |

**カスタマイズ項目**: ヘッダーロゴ位置、明細テーブル列幅、フッター備考欄、フォントサイズ等

---

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


## 商用ウェブサイト (フェーズ1完成)

商用展開に向けた公式ウェブサイトを website/ ディレクトリに構築しました。フェーズ1では以下のページを実装しています：

### 主なページ
- **トップページ** (website/index.html) - ヒーローセクション、課題提起、コア機能紹介、信頼性証明、導入事例、料金サマリー
- **製品情報** (website/product/index.html) - 機能詳細、対応施設種別
- **料金プラン** (website/product/pricing.html) - 3つのプラン（スターター・スタンダード・エンタープライズ）詳細比較
- **導入事例** (website/case-studies.html) - 施設種別・課題別フィルタ機能付き事例一覧
- **会社情報** (website/company/index.html) - 会社概要、代表メッセージ、アクセス、採用情報
- **資料請求フォーム** (website/request/catalog.html) - L1コンバージョンポイント（3項目フォーム）
- **お問い合わせフォーム** (website/request/inquiry.html) - L4コンバージョンポイント（多段階フォーム）
- **プライバシーポリシー** (website/privacy.html)
- **利用規約** (website/terms.html)

### 特徴
- 完全レスポンシブデザイン（モバイルファースト）
- アクセシビリティ対応（セマンティックHTML、ARIAラベル）
- SEO最適化（適切なメタタグ、見出し構造）
- コンバージョン最適化（段階的フォームによる離脱率低減）
- 信頼性証明（導入施設数・処理件数・連携実績の具体的数値表示）
- カスタムプロパティベースのCSSによる保守性の高いスタイリング
- バニラJavaScriptによる軽量なインタラクション

### 技術スタック
- HTML5
- CSS3（CSSカスタムプロパティ使用）
- バニラJavaScript（ES2022）

### デプロイ方法
静的サイトホスティングサービス（Netlify, Vercel, GitHub Pages等）または従来のWebサーバーにアップロードするだけで公開可能です。

次なるフェーズでは、機能詳細ページの実装、デモ環境・体験版ページ、コンテンツマーケ用コラム・ニュースセクションの追加を予定しています。

## 変更履歴 v1.1 (2026-10-07)

### 新機能追加
- **施設管理** (`FacilityResource`) — 複数施設の基本情報・運営者情報管理
- **税率設定** (`TaxSettingResource`) — 消費税率の履歴管理・期間指定
- **PDFテンプレート設定** (`PdfTemplateSettingResource`) — 請求書・領収書のレイアウトカスタマイズ

### データベース拡張
- `facilities` テーブル追加（施設マスタ）
- `tax_settings` テーブル追加（税率履歴）
- `pdf_template_settings` テーブル追加（テンプレート設定）
- 対応マイグレーション・シーダー整備

### アーキテクチャ改善
- 複数施設対応の基盤整備（テナント分離への第一歩）
- 設定値の集中管理化（.env 依存から DB 管理へ移行準備）

### 備考
v1.0 からの破壊的変更なし。既存データ・テストは全て互換性維持。

---

## 変更履歴 v1.2 (2026-10-08)

### 会計連携CSVエクスポート機能の大幅拡張
- **勘定科目マスタ (`ChartOfAccount`)** — 施設別・品目別（家賃/管理費/自費/立替）の借方・貸方勘定科目コード管理、補助科目・部門・タグ・税区分コード対応
- **会計エクスポートプロファイル (`AccountingExportProfile`)** — 会計ソフト別（freee/MF/弥生/勘定奉行）のCSV出力フォーマット定義（ヘッダー・フィールドマッピング・税区分コード変換・エンコーディング・改行コード・BOM有無）
- **InvoiceCsvExportService 拡張** — プロファイル指定による柔軟な出力、仕訳プレビュー機能（内容確認→ダウンロード）、施設別・会計ソフト別対応
- **Filamentリソース追加** — `ChartOfAccountResource`, `AccountingExportProfileResource` で管理画面からマスタ・プロファイルを設定可能
- **MonthlyInvoiceResource 強化** — 会計仕訳CSV出力時に会計ソフト・プロファイル選択可能、プレビューアクション追加
- **シーダー整備** — `AccountingExportSeeder` で各施設のデフォルト勘定科目・プロファイルを自動作成
- **テスト拡充** — 6種類の出力形式（freee/MF/弥生/勘定奉行/カスタム/プレビュー）を網羅するテスト追加

### 新機能追加
- **セルフサーブ・トライアル機能** — 施設様向け体験版環境の自動プロビジョニング（`TrialProvisioningService`）
  - `Trial` モデル：メール・施設名・プランでトライアル申請、トークンベース認証
  - `Subscription` モデル：プラン別機能制限、Stripe連携準備、自動更新/キャンセル
  - `Booking` モデル：オンライン面談予約、カレンダー連携、リマインダー通知
  - API エンドポイント（`routes/api.php`）— トライアル申請、プロビジョニング状態確認、面談予約
  - メール通知（`MailService`）— 申請受付、環境準備完了、面談確定/リマインダー
  - リードスコアリング（`LeadScoringService`）— 行動ベース評価、営業優先度付け

- **商用ウェブサイト（フェーズ1）** — `website/` ディレクトリに完全静的サイトを構築
  - 9ページ：トップ、製品情報、料金プラン、導入事例、会社情報、資料請求、お問い合わせ、プライバシー、利用規約
  - 完全レスポンシブ、アクセシビリティ対応、SEO最適化、コンバージョン最適化（段階的フォーム）
  - バニラJS + CSSカスタムプロパティ、依存関係ゼロで高速表示

### PDF・印刷機能強化
- **PDFデザインシステム刷新** — `resources/views/invoices/partials/` に共通パーツ化
  - ヘッダー/フッター/明細行/税率内訳/印影欄をコンポーネント化
  - ダークモード対応、印刷時最適化（`@media print`）
- **印影（角印）対応** — `Facility` に `seal_path` カラム追加、PDFに印影画像を埋め込み
- **フォント最適化** — IPAexゴシック/明朝をローカル登録、dompdf フォントメトリクス調整

### アーキテクチャ・インフラ
- `AdminPanelProvider` でナビゲーション・ウィジェット・リソースを動的登録
- `FacilityBillingSeeder` で施設ごとの請求設定をシード可能に
- `Console/Kernel` でスケジュールコマンド（トライアル期限チェック、請求締め自動化準備）を登録
- ジョブキュー対応（`TrialProvisioningJob`, `MailJob`）で非同期処理化

### テスト拡充
- PDFデザインシステム・レイアウト・機能の統合テスト追加
- トライアルAPI・プロビジョニングのフィーチャーテスト追加
- 既存 109 テスト全通過を維持（全 183 テスト / 1104 アサーション）

### データベース
- 8 新規マイグレーション（trials, subscriptions, bookings, facility seal_path 等）
- 2 新規マイグレーション（chart_of_accounts, accounting_export_profiles）
- インデックス最適化、外部キー制約整備

### 備考
v1.1 からの破壊的変更なし。既存データ・テストは全て互換性維持。

---

## 今後の改善方向性

詳細な改善提案・ロードマップは **[IMPROVEMENT_PROPOSALS.md](IMPROVEMENT_PROPOSALS.md)** を参照してください。

### 概要（9 つの主要テーマ）

| フェーズ | テーマ | ねらい |
|---------|--------|--------|
| **即効** (1-2 週間) | 1. 請求締め自動化スケジューラ | 締め作業の自動化・忘れ防止 |
| | 9. 監査ログ・履歴管理強化 | 内部統制・改ざん検知・監査対応 |
| **短期** (1-3 ヶ月) | 3. 滞納管理・督促ワークフロー | 回収率向上・属人化解消 |
| | 7. ダッシュボード・経営分析レポート | 経営判断の高速化・可視化 |
| | 8. API ファースト化・外部連携基盤 | 会計・ケアプラン・銀行との自動連携 |
| **中期** (3-6 ヶ月) | 2. 入金消込自動照合（口座振替取込） | 照合工数 90% 削減 |
| | 4. 電子帳簿保存法対応 | 法令準拠・税務調査対応 |
| **長期** (6 ヶ月〜) | 5. 複数施設・法人対応（テナント分離） | 法人本部の一元管理・スケール |
| | 6. 介護保険請求連携 | 自費・保険の統合請求管理 |

---

## ライセンス

All Rights Reserved.

Copyright (c) 2026. All rights reserved.
Unauthorized copying, modification, distribution, or use of this software, 
via any medium, is strictly prohibited without prior written permission.

