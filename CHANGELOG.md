# 変更履歴

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
