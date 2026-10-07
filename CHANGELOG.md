# 変更履歴

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
