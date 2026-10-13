# 変更履歴

## [1.7.1] - 2026-10-13

### ドキュメント修正
- バージョン表記を v1.7.1 に統一
- README.md、操作マニュアルのバージョン表記を v1.7.1 に更新

## [1.7.0] - 2026-10-13

### 新機能
- **会計ソフトAPI連携 (freee / MFクラウド会計)** (`App\Filament\Resources\FacilityResource`, `App\Services\Accounting\AccountingSyncService`)
  - 施設情報にOAuth2.0認証情報（Client ID/Secret、Access/Refresh Token、事業所ID）を管理
  - 連携対象会計ソフト選択（freee / MFクラウド会計）、自動同期ON/OFF、CSVエクスポート時同期オプション
  - 管理画面から「接続テスト」ボタンで認証情報の有効性を即時確認可能
  - 請求確定・入金時に `AccountingSyncService` 経由で仕訳を自動同期（本番/ステージング環境のみ）
- **全銀口座振替依頼出力 (Zengin形式)** (`App\Services\Banking\ZenginFormatService`, `MonthlyInvoiceResource` 一括アクション)
  - 一括選択した請求データから全銀フォーマット（固定長レコード）の振替依頼ファイルを生成
  - 施設銀行情報（委託者コード・銀行コード・支店コード・口座種別・番号）をヘッダーに、入居者口座情報をデータレコードに出力
  - 引落日指定、UTF-8/Shift_JIS対応、`.dat` 拡張子で出力
- **全銀結果取込（自動消込）** (`App\Services\Banking\ZenginReconcileService`, `MonthlyInvoiceResource` 一括アクション)
  - 金融機関からの全銀結果ファイル（固定長）を解析し、成功/不能を自動判定
  - 成功レコードは該当請求を `Paid` ステータスに自動遷移、領収書番号発行、領収書日付モード対応
  - 不能レコードは詳細理由（残高不足・口座解約・名義不一致等）付きで通知、手動対応へエスカレーション
  - 取込結果サマリー（成功件数・金額、不能件数・金額）を通知表示
- **一括請求書メール送信** (`MonthlyInvoiceResource` 一括アクション)
  - 複数の請求書を選択し、共通メールアドレス・添え書きで一括送信
  - 入居者個別のメールアドレスが設定されている場合はそちらを優先
  - 介護サービス請求書PDF（確定済みのみ）も同封可能
  - 送信成功/失敗件数を通知
- **銀行口座情報管理** (`App\Models\ResidentBankAccount`, `App\Models\DirectDebitResult`)
  - 入居者ごとの銀行口座（銀行・支店・種別・番号・名義・カナ名・有効フラグ）を登録・管理
  - 口座振替結果（成功/不能・エラーコード・理由・金額・処理日）を記録
- **日次利用料プリセット** (`App\Models\DailyChargePreset`, `App\Models\DailyChargePresetItem`, `App\Filament\Resources\DailyChargePresetResource`)
  - よく使う自費記録パターン（品目×数量×単価のセット）をプリセットとして保存
  - 一括入力画面からプリセットを適用し、複数入居者へ一括展開可能
- **月次締めウィザード** (`App\Filament\Pages\MonthlyClosingWizard`)
  - 月末締め作業（自費記録確認→請求生成→金額確認→PDF出力→請求済変更→入金消込→会計CSV出力）を7ステップでガイド
  - 各ステップで進捗・未完了項目を可視化、前ステップへの戻り可能
- **一括日次利用料入力** (`App\Filament\Pages\BulkDailyChargeEntry`)
  - 入居者×品目のマトリクス形式で、同一日付の自費記録を一括入力
  - プリセット適用、品目単価自動反映、入力済みセルのハイライト表示
- **APIトークン管理** (`App\Models\ApiToken`, `App\Filament\Resources\ApiTokenResource`, `App\Http\Middleware\ApiTokenAuth`)
  - 外部システム連携用のBearerトークン発行・管理・失効・有効期限設定
  - 権限スコープ（read/write/admin）によるアクセス制御、最終使用日時記録
  - `routes/api.php` に API トークン認証ミドルウェア適用
- **会計同期サービス** (`App\Services\Accounting\AccountingSyncService`)
  - freee / MFクラウド会計の OAuth2.0 トークン自動リフレッシュ
  - 仕訳作成・更新・削除 API 対応、同期失敗時のリトライ・ログ記録
- **請求異常検知** (`App\Services\BillingAnomalyDetector`)
  - 前月比金額急変（±30%以上）、未入金長期化（60日超）、請求額0円継続などを自動検知
  - 検知結果をアクティビティログ記録、ダッシュボードウィジェットで表示

### アーキテクチャ改善
- **MonthlyInvoiceResource アクション大幅整理**
  - PDFプレビューアクションを削除し、PDFダウンロードアクションに統合（`generatePdfFromInvoice` 経由）
  - 請求書・領収書・ZIPダウンロードを「帳票」ドロップダウンにグループ化
  - 一括アクションにスキップ件数表示・フィルタリング機能を追加（確定済み自動除外）
  - CSV出力に施設管理者権限対応（自施設のみ出力）
- **Dashboard 改善** (`App\Filament\Pages\Dashboard`)
  - 請求アクション（一括生成・ZIP出力・CSV出力）をダッシュボードから直接実行可能に
  - 未入金アラートの詳細表示・絞り込み機能強化
- **InvoicePdfService 改善** (`App\Services\InvoicePdfService`)
  - サンプルPDFを最新テンプレートで再生成（`storage/app/sample_invoice.pdf`, `sample_receipt.pdf`）
  - ZIP生成時のファイル名に施設名・年月を含めるよう統一

### データベース
- `facilities` に会計API認証情報カラム追加（`accounting_software`, `accounting_api_settings` JSON, `freee_*`, `mf_*`）
- `notifications` テーブル新規作成（システム通知・アラート用）
- `api_tokens` テーブル新規作成（APIトークン管理用）
- `resident_bank_accounts` テーブル新規作成（入居者銀行口座管理用）
- `direct_debit_results` テーブル新規作成（口座振替結果記録用）
- `daily_charge_presets` / `daily_charge_preset_items` テーブル新規作成（プリセット管理用）
- `monthly_closing_states` テーブル新規作成（月次締め進捗管理用）

### テスト拡充
- `AccountingSyncServiceTest`（freee/MF同期ロジック）
- `ZenginFormatServiceTest` / `ZenginReconcileServiceTest`（全銀フォーマット生成・解析）
- `BulkDailyChargeEntryTest` / `BulkDailyChargeEntryPresetTest`（一括入力・プリセット）
- `MonthlyClosingWizardTest`（月次締めウィザード）
- `DatabaseHealthCheckTest`（DBヘルスチェックコマンド）
- `DashboardBillingActionTest`（ダッシュボードからの請求アクション）
- `InvoiceCsvExportServiceTaxBreakdownTest`（税額内訳CSV出力）
- `InvoicePdfFileNameTest`（PDFファイル名生成）
- `ZipCompletionNotificationTest`（ZIP生成完了通知）
- 各種リグレッションテスト追加

### 備考
v1.6 からの破壊的変更なし。既存データ・テストは全て互換性維持。

## [1.6.0] - 2026-10-10

### バグ修正
- **チャットボットアシスタント**: Bladeテンプレートで未定義の`heading()`関数参照エラーを修正（`resources/views/filament/pages/chatbot-assistant.blade.php`）
- **統合請求管理ダッシュボード**: 不正なアクションクラスインポートによるHeader Actionsエラーを修正（`app/Filament/Pages/IntegratedBillingDashboard.php`）
- **監査ログ**: 存在しない`facility`リレーション参照によるTypeErrorを修正し、施設フィルターを動的オプションリストに変更（`app/Filament/Resources/ActivityLogResource.php`）
- **チャットボットアシスタント**: BladeエンジンがPHP定数と解釈するAlpine.jsバインディング構文を`x-bind:`プレフィックスに変更（`resources/views/filament/pages/chatbot-assistant.blade.php`）

## [1.5.0] - 2026-10-09

### 新機能
- **自動月次請求生成コマンド** (`App\Console\Commands\GenerateMonthlyInvoices`) — 請求締めの自動化
  - `billing:generate-monthly` コマンドで月次請求データを自動生成・更新（毎月1日 02:00 スケジューラ登録済み）
  - オプション: `--year-month=`（対象年月）、`--facility-id=`（対象施設）、`--force`（確定済み強制再計算）、`--dry-run`（シミュレーションのみ）
  - 進捗バー・結果テーブル表示、アクティビティログ記録（`monthly_invoice` ログ名）
  - 対象: アクティブ施設の在籍入居者（月途中入居・退去を含む）、確定済み（Billed/Paid）はスキップ
- **介護サービス請求管理** (`App\Models\ServiceInvoice`, `App\Enums\ServiceType`, `App\Enums\ServiceInvoiceStatus`) — 介護保険サービス請求の統合管理
  - 介護保険サービス8種（訪問介護・通所介護・居宅介護支援・訪問看護・短期入所生活介護・福祉用具貸与・居宅介護住宅改修・その他）に対応
  - ステータス管理（下書き→確定済み→送信済み）、送信経路記録（メール/ZIP配布/ポータル/手渡し）
  - 外部システム連携（外部システム名・外部請求番号）、PDFアップロード（10MB制限）・ダウンロード
  - `ServiceInvoiceResource`（Filament）で管理画面から登録・確定・送信管理
- **統合請求管理ダッシュボード** (`App\Filament\Pages\IntegratedBillingDashboard`) — 住居費＋介護サービスの総合管理
  - 住居費（家賃・管理費・自費）と介護サービス請求（種別ごと）を入居者別に一覧表示
  - 介護サービス種別ごとの金額列を動的生成、住居費計＋介護計＝総合計をリアルタイム集計
  - CSVエクスポート（BOM付きUTF-8、Excel対応）、請求年月・施設フィルタ対応
- **請求書PDF自動結合** (`App\Services\InvoiceMergeService`) — 統合請求書の生成
  - 住居費請求書と介護サービス請求書PDFを1つのPDFに結合（表紙サマリー＋住居費明細＋サービス種別見出しページ）
  - 表紙に住居費・介護サービス種別別金額・総合計を表示、ページ番号フッター自動付与
  - 下書きステータスの介護サービスPDFは結合対象外
- **監査ログ・アクティビティログ** (`spatie/laravel-activitylog`) — 内部統制・改ざん検知
  - 主要モデル（入居者・請求品目・日々の自費・月次請求・施設・税率・ユーザー・会計プロファイル等）の作成・更新・削除・ステータス変更を自動記録
  - `ActivityLogResource`（Filament）で閲覧・フィルタ（ログ名・イベント・期間・施設）可能
  - 閲覧権限: 法人管理者は全件、施設管理者は自施設のみ

### アーキテクチャ改善
- **スケジューラ登録** (`routes/console.php`) — `GenerateMonthlyInvoices` を毎月1日 02:00 自動実行に登録（production/staging 環境のみ、成功/失敗時のログ出力）
- **Activitylog マイグレーション** — `activity_log` テーブルに `event` / `batch_uuid` カラム追加、インデックス最適化

### テスト拡充
- `GenerateMonthlyInvoicesTest`（新規作成・更新・スキップ・ドライラン・強制更新）
- `ServiceInvoiceModelTest`（モデル・ステータス遷移・スコープ）
- `InvoiceMergeServiceTest`（PDF結合ロジック）
- `ExternalInvoiceApiTest`（API エンドポイント）
- `InvoicePdfServiceZipByCategoryTest`（種別別ZIP出力）
- `IntegratedBillingDashboard` の集計・CSV出力テスト

### データベース
- `service_invoices` テーブル新規作成（施設・入居者・請求年月・サービス種別・外部連携情報・金額・税額・税率・PDFパス・ステータス・送信情報）
- `activity_log` テーブルに `event` / `batch_uuid` カラム追加・インデックス

### 備考
v1.3 からの破壊的変更なし。既存データ・テストは全て互換性維持。

## [1.3.0] - 2026-10-09

### 新機能
- **ダッシュボード** (`App\Filament\Pages\Dashboard`) — 請求業務の司令塔
  - 対象月の請求進捗バー（進捗率）と件数サマリー（対象/請求済/未請求/入金済）
  - 未入金アラート（`UnpaidInvoicesAlert` ウィジェット連携）
  - クイック統計（入居者数・今月の請求総額等）
  - 年月セレクタで過去12ヶ月＋未来3ヶ月を切替可能
  - 施設管理者（`isFacilityAdmin`）は自施設のデータのみ表示
- **セットアップウィザード** (`App\Filament\Pages\OnboardingWizard`) — 初期導入を5ステップでガイド
  - Step1: 施設情報の入力（施設名・運営法人・インボイス登録番号・銀行口座）
  - Step2: 請求品目の選択（プリセット6種：おむつ/理美容/受診付き添い等をワンクリック追加）
  - Step3: 入居者CSVの一括取込（プレビュー付き、`League\Csv` 使用）
  - Step4: 会計ソフト連携設定（freee/MF/弥生/勘定奉行のプロファイル自動作成）
  - Step5: 完了
- **請求書・領収書の日付モード** — `invoice_date_mode` / `receipt_date_mode`（`auto`/`manual`）に対応
  - `auto`: 請求年月・入金日に基づく自動決定（従来通り）
  - `manual`: `custom_invoice_date` / `custom_receipt_date` で任意の日付を指定
- **税額内訳の詳細化** — `monthly_invoices.tax_breakdown`（JSON）で標準税率（10%）・軽減税率（8%）を別々に管理
  - `MonthlyInvoice::getTaxableAmountAttribute()` / `getTaxAmountAttribute()` が `tax_breakdown` を優先して算出

### アーキテクチャ改善
- **PDFアーキテクチャ刷新** — 責務分離と拡張性の向上
  - DTO 層（`App\DTOs\Pdf\`）: `InvoicePdfData`, `ReceiptPdfData`, `FacilityPdfData`, `ResidentPdfData`, `DailyChargePdfData`, `TaxInfoPdfData`
  - サービス層（`App\Services\Pdf\`）: `InvoicePdfGenerator`（統合インターフェース）, `TemplateSettingsService`, `InvoiceDataProvider`
  - 契約インターフェース: `RendererInterface`, `TemplateInterface`, `FontRegistryInterface`
  - 実装: `Renderers\DomPdfRenderer`, `Renderers\HtmlRenderer`, `Templates\InvoiceTemplate`, `Templates\ReceiptTemplate`, `Fonts\WindowsFontRegistry`
  - `App\Providers\PdfServiceProvider` でサービスコンテナに登録
- **請求計算サービスのリファクタリング** — `InvoiceCalculationService` をオーケストレーションに特化
  - `App\Services\Invoice\DailyChargeAggregator`: 日次課金の税区分別集計（1クエリ）
  - `App\Services\Invoice\RecurringChargeAggregator`: 定期課金の集計
  - `App\Services\Invoice\ProrationCalculator`: 日割り計算（在籍日数ベース）
  - `App\Services\Invoice\TaxCalculator`: 税率・税額計算（`TaxSetting` 履歴対応）
  - `App\Services\Invoice\InvoicePersister`: 請求データの永続化（チャンク単位トランザクション）
  - `chunkById` によるチャンク処理でメモリ効率化（`CHUNK_SIZE = 100`）
- **FacilityConfigService** — 施設設定の統一アクセス（`facilities` テーブルを優先、`config/facility.php` にフォールバック）
- **PdfTemplateSettingsResource** — `PdfTemplateSettingResource` をリネーム・強化（ロケール・テーマ・日付モード対応、`PdfTemplateSettings` モデル）

### フォント・デザイン
- **Noto Sans JP** フォント対応（`storage/fonts/NotoSansJP-Regular.ttf` / `NotoSansJP-Bold.ttf` を同梱）
- PDF用CSSの整備（`resources/css/pdf-invoice.css` / `pdf-receipt.css`）
- PDFビューのコンポーネント化（`resources/views/pdf/components/`）
- 空状態ビューの追加（`resources/views/filament/resources/*/list-empty.blade.php`）

### デプロイ基盤
- `Dockerfile` / `.dockerignore` / `Caddyfile` / `railway.json` / `.env.production.example`

### テスト拡充
- PDF DTO・データプロバイダ・ジェネレータ・フォントレジストリの単体テスト（`tests/Unit/Pdf/`）
- モデルテストの大幅拡充（Facility, ChargeItem, ChargeItemPrice, ChartOfAccount, AccountingExportProfile, Trial, Subscription, Booking, RecurringCharge, User）
- `InvoicePreviewTest`, `PdfTemplateSettingsResourceTest` の追加
- **全 350 テスト / 1666 アサーション**（v1.2 の 183 テスト / 1104 アサーションから拡充）

### データベース
- `monthly_invoices` に `tax_breakdown`（JSON）、`invoice_date_mode`、`custom_invoice_date`、`receipt_date_mode`、`custom_receipt_date` カラム追加
- `pdf_template_settings` に `locale`、`theme`、`date_mode` カラム追加
- 複合インデックス追加（`monthly_invoices`、`daily_charges`、`residents`）

### 備考
v1.2 からの破壊的変更なし。既存データ・テストは全て互換性維持。

## [1.2.0] - 2026-10-08

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

## [1.1.0] - 2026-10-06

### 重大バグ修正（データ整合性・リソース管理）
- **InvoiceCalculationService**: 月次請求生成の対象住民フィルタを修正。月途中入居・月途中退去の住民も正しく対象に含めるように変更（[`InvoiceCalculationService.php`](app/Services/InvoiceCalculationService.php)）
- **InvoicePdfService**: `generateMonthlyZip` に `try/finally` による一時ディレクトリクリーンアップを追加し、リソースリークを防止（[`InvoicePdfService.php`](app/Services/InvoicePdfService.php)）
- **MonthlyInvoice**: Observer（二重計算の原因）を削除し、モデルの `booted` メソッドのみに total_amount 同期を集約（[`MonthlyInvoice.php`](app/Models/MonthlyInvoice.php)）
- **User**: `canAccessPanel` を `is_admin === true` のユーザーのみ許可するよう修正（[`User.php`](app/Models/User.php)）
- **DailyCharge**: `charge_item_id` の NOT NULL 制約に合わせたバリデーション動作を明確化

### 高優先度修正
- **MonthlyInvoiceResource**: PDFダウンロードアクションに `Content-Type: application/pdf` ヘッダーを追加
- **ResidentResource**: 退去日に入居日以降を必須とするバリデーション（`after_or_equal:move_in_date`）を追加
- **InvoiceCsvExportService**: 会計仕訳CSVを `Billed` / `Paid` ステータスのみ対象に変更
- **ChargeItem**: `getEffectivePrice()` ヘルパー追加、Filamentフォームのヘルパーテキストを品目名に応じて動的表示
- **Resident**: `$guarded` から `$fillable` へ変更し、一括代入可能フィールドを明示
- **AppServiceProvider**: 施設設定の起動時バリデーション（インボイス登録番号・銀行情報）を追加

### 中優先度修正
- **MonthlyInvoice**: 領収書番号フォーマットを `%03d` → `%04d` に変更（9999人まで対応）
- **InvoicePdfService**: PDF生成ロジックを `generatePdfFromInvoice` に統合し、`InvoicePdfController` を削除。ルートはサービス経由に変更
- **InvoiceCalculationService**: 請求生成の開始・完了にログ出力を追加

### データベース
- `residents` テーブルの `move_in_date` / `move_out_date` にインデックスを追加
- `users.is_admin` を nullable に変更
- 日付比較を 'Y-m-d H:i:s' 形式に変更し、タイムスタンプ境界の取りこぼしを防止

### テスト
- テストスイートを17テスト → 77テストに拡充（リグレッション防止）
- 追加テスト: 月次フィルタ・境界日付・ZIP生成・ルート認可・会計CSVフィルタ・エッジケース課金・ログ出力

### ドキュメント
- `DEPLOYMENT.md`（デプロイメントチェックリスト）を追加
- `.env.example` に施設設定環境変数を追記
