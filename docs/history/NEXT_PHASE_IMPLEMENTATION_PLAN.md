# 次フェーズ実装計画書（Critical/High リスク対応）

**作成日**: 2026-10-09
**対象**: CONSOLIDATED_AUDIT_REPORT.md の残存 Critical/High 項目
**期間**: 2026-10-09 〜 2026-11-06（約4週間）
**テスト方針**: 全実装にリグレッションテスト作成、既存386テストの継続通過を必須条件とする

---

## 実装スコープ一覧

| # | 項目 | 優先度 | 工数 | 対応リスク |
|---|------|--------|------|-----------|
| 1 | PDFパスワード保護機能 | Critical (P0-1) | 3-5日 | 個人情報漏洩・法的リスク |
| 2 | 請求書「計算根拠」セクション追加 | High (P1-4) | 1-2日 | 問い合わせ増加・透明性不足 |
| 3 | 品目マスタ表示名・説明フィールド | Medium (P2-4) | 1日 | 明細の分かりにくさ |
| 4 | バックアップ/DRスクリプト・リストアテスト | Critical | 2-3日 | 事業継続リスク |

---

## Item 1: PDFパスワード保護機能 (Critical P0-1)

### 目的
請求書・領収書PDFにパスワード保護（暗号化）を施し、個人情報保護・改ざん防止を実現する。

### 実装内容
1. **パッケージ導入**: `setasign/fpdi-pdf-protector` ではなく、DomPDF生成後に `setasign/fpdf` + protection、または `began/qrcode` プロジェクトに既存の debug ファイルがあることから、**`barryvdh/laravel-dompdf` の出力PDFを `setasign/fpdi` でラップ**する方式を採用
   - 実際の採用: `setasign/fpdf` ベースの PDF encryption（RC4 128bit）
2. **サービスクラス**: `app/Services/Pdf/PdfPasswordProtector.php` 新設
   - `protect(string $pdfBinary, ?string $ownerPassword, string $userPassword): string`
   - パスワード生成ヘルパー: `generatePassword(): string`（生年月日下4桁 + ランダム2文字等のポリシー設定可能）
3. **パスワードポリシー**（config/pdf.php に追加）:
   - `password.enabled` (bool, default: false)
   - `password.mode`: 'fixed' | 'resident_birthday' | 'random'
   - `password.fixed_password`: 施設共通パスワード（mode=fixed時）
4. **統合ポイント**: `InvoicePdfService` の PDF生成後フックで protector 呼び出し
5. **パスワードの連携**:
   - mode='resident_birthday': 入居者生年月日から導出（家族への案内が容易）
   - mode='random': ランダム生成して MonthlyInvoice に `pdf_password` カラム保存、管理画面で確認可能

### テスト計画（リグレッション防止）
- `tests/Feature/PdfPasswordProtectionTest.php` 新設:
  1. パスワード無効時は既存動作を維持（暗号化なし・バイナリ同一）
  2. mode=fixed で暗号化PDFが生成され、正しいパスワードで復号可能
  3. mode=random で `pdf_password` が保存される
  4. 誤ったパスワードでは開けない（暗号化フラグ検証）
  5. 領収書PDFも同様に暗号化される
  6. 既存PDFレイアウト・内容テスト（InvoicePdfLayoutTest等）への影響なし

---

## Item 2: 請求書「計算根拠」セクション追加 (High P1-4)

### 目的
日割り計算の根拠・税額内訳を利用者にも分かる形で請求書PDFに記載し、問い合わせ削減と透明性向上を図る。

### 実装内容
1. **Bladeテンプレート**: `resources/views/pdf/invoice.blade.php`（既存）に「計算根拠」セクション追加
   - 固定費: 月額 × 適用日数/月日数（月中途入居の場合のみ按分表示）
   - 税額内訳: 標準10%対象額・軽減8%対象額・非課税額とそれぞれの税額
2. **DTO/DataProvider**: `app/Services/Pdf/InvoiceDataProvider.php` に計算根拠データ生成メソッド追加
   - `getCalculationBasis(MonthlyInvoice $invoice): array`
3. **設定**: `pdf_template_settings` に `show_calculation_basis` (bool) カラム追加 → 施設ごとに表示切替可能
4. **表示条件**: 月中途入居・月中退去の場合のみ按分計算行を表示（通常月は「月額そのまま」表示）

### テスト計画
- `tests/Feature/InvoiceCalculationBasisTest.php` 新設:
  1. 通常月は「月額」表示で按分行なし
  2. 月中途入居は「日割り計算」行が表示される（日数・金額含む）
  3. 税額内訳（標準/軽減/非課税）が正しく表示される
  4. `show_calculation_basis=false` の施設では非表示
  5. 既存PDFレイアウトテストへの影響なし

---

## Item 3: 品目マスタ表示名・説明フィールド (Medium P2-4)

### 目的
ChargeItem に「入居者向け表示名（display_name）」「説明（description）」を追加し、請求書明細の分かりやすさを向上させる。

### 実装内容
1. **マイグレーション**: `charge_items` テーブルに `display_name` (nullable string)、`description` (nullable text) 追加
2. **モデル**: `ChargeItem` の fillable に追加、アクセサ `getDisplayNameForResident(): string`（display_name ?? name）
3. **Filament**: `ItemMasterResource` フォームに display_name・description フィールド追加
4. **請求書PDF**: 明細名表示時に display_name を優先使用
5. **監査ログ**: 既存の ChargeItem LogsActivity 設定の logOnly に display_name・description 追加

### テスト計画
- `tests/Feature/ChargeItemDisplayNameTest.php` 新設:
  1. display_name が設定されていれば請求書明細で使用される
  2. display_name が null の場合は name にフォールバック
  3. Filament フォームから設定可能
  4. 変更が監査ログに記録される
  5. 既存ChargeItemModelTest/FilamentChargeItemResourceTest への影響なし

---

## Item 4: バックアップ/DRスクリプト・リストアテスト (Critical)

### 目的
データバックアップの自動化・リストア手順の検証・RPO/RTO定義を行い、事業継続性を確保する。

### 実装内容
1. **バックアップコマンド**: `app/Console/Commands/BackupDatabase.php` 新設
   - `php artisan db:backup {--compress} {--retention-days=30}`
   - MySQL/PostgreSQL/SQLite対応（`mysqldump` / `pg_dump` / ファイルコピー）
   - `storage/app/backups/` に保存、保持日数を超えた古いバックアップを自動削除
   - 実行結果を activity_log に記録
2. **リストアコマンド**: `app/Console/Commands/RestoreDatabase.php` 新設
   - `php artisan db:restore {backup-file} {--force}`
   - リストア前の確認プロンプト（--force でスキップ）
3. **スケジューラ**: `routes/console.php` に毎日 03:00 バックアップ実行を登録
4. **DR手順書**: `docs/DR_PROCEDURE.md` 新設
   - RPO: 24時間（日次バックアップ）、RTO: 4時間（目標）
   - リストア手順、検証手順、インシデント連絡フロー
5. **ヘルスチェック**: `php artisan db:backup --verify` でバックアップ整合性チェック

### テスト計画
- `tests/Feature/DatabaseBackupRestoreTest.php` 新設（SQLite環境で実行可能な形にする）:
  1. バックアップコマンドがファイルを生成する
  2. リストアコマンドでデータが復元される（作成→破壊→リストア→検証）
  3. 保持日数超過のバックアップが削除される
  4. activity_log にバックアップ実行が記録される
  5. 不正なファイルパスでエラーになる
  6. --verify で整合性チェックが動作する

---

## 実装順序とタイムライン

```
Week 1 (10/09-10/15)
├─ Item 1: PDFパスワード保護 (3-5日)
│   ├─ Day 1: パッケージ調査・protector サービス実装
│   ├─ Day 2-3: InvoicePdfService 統合・パスワードポリシー
│   └─ Day 4-5: テスト作成・リグレッション確認
├─ Item 2: 計算根拠セクション (1-2日)
│   └─ Blade編集・DataProvider・テスト

Week 2 (10/16-10/22)
├─ Item 3: 品目表示名 (1日)
│   └─ マイグレーション・モデル・Filament・テスト
├─ Item 4: バックアップ/DR (2-3日)
│   ├─ Day 1-2: コマンド実装・スケジューラ
│   └─ Day 3: テスト・DR手順書

Week 3-4: 残存 High 項目（2FA・承認ワークフロー・入金エビデンス）は本計画完了後に別途計画策定
```

---

## リグレッション防止の全体方針

1. **実装前**: 対象機能の既存テストを全実行し、ベースラインを記録
2. **実装中**: 各Item完了時に既存386テスト + 新規テストを全実行
3. **実装後**: 全テストスイート実行、失敗ゼロを確認してから CONSOLIDATED_AUDIT_REPORT.md を更新
4. **テストカバレッジ**: 新規コードは正常系・異常系・境界値（設定ON/OFF、nullフォールバック）をカバー

---

## 完了条件（Definition of Done）

- [ ] 各Itemの実装完了
- [ ] 各Itemのリグレッションテスト作成・全合格
- [ ] 既存386テストの継続通過
- [ ] CONSOLIDATED_AUDIT_REPORT.md の該当項目を「完了」に更新
- [ ] README.md ロードマップのステータス更新
