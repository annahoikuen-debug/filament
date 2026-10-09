# Critical項目 詳細実装計画書

**作成日**: 2026-10-09  
**対象**: 統合監査レポートで特定された Critical/High リスク項目の即時対応（2026 Q4 / 10-12月）

---

## 実装項目一覧（優先度順）

| # | 施策 | 工数 | 関連ファイル・領域 | テスト戦略 |
|---|------|------|-------------------|------------|
| 1 | `spatie/laravel-activitylog` 導入・主要モデル設定 | 1-2週間 | 全モデル、サービス層 | Feature/Unitテストでログ記録検証 |
| 2 | `GenerateMonthlyInvoices` コマンド作成・スケジューラ登録 | 1-2週間 | Console/Commands、Kernel、InvoiceCalculationService | Featureテストで自動生成・重複防止検証 |
| 3 | バックアップスクリプト作成・cron登録・リストアテスト | 2-3週間 | scripts/、DB、Storage | 統合テストでリストア検証 |
| 4 | 2FAミドルウェア追加・管理画面必須化 | 2-3週間 | Filament認証、Userモデル、Middleware | Featureテストで2FAフロー検証 |
| 5 | 請求承認ワークフロー実装 | 2-3週間 | MonthlyInvoiceResource、Status遷移、通知 | Featureテストで承認フロー検証 |
| 6 | インボイスPDF要件チェック関数実装 | 1週間 | InvoicePdfService、InvoicePdfGenerator | Unit/Featureテストでバリデーション検証 |
| 7 | 入金消込時の証憑添付必須化 | 1-2週間 | MonthlyInvoiceResource、MarkAsPaidアクション | Featureテストで必須化検証 |
| 8 | PDFパスワード保護機能追加 | 3-5日 | InvoicePdfService、DomPdfRenderer | Unitテストで暗号化検証 |
| 9 | 請求書に計算根拠セクション追加 | 1週間 | PDFテンプレート、InvoicePdfData | Featureテストで表示検証 |
| 10 | 品目マスタに表示名・説明フィールド追加 | 3-5日 | ChargeItemモデル、マイグレーション、Resource | Unit/FeatureテストでCRUD検証 |

---

## 1. 監査ログ導入（spatie/laravel-activitylog）

### 1.1 要件
- 主要モデルの作成・更新・削除・ステータス変更を自動記録
- ユーザー・IP・ユーザーエージェント・変更前後値を記録
- 管理画面から監査ログを閲覧可能
- パフォーマンス考慮（キュー非同期処理）

### 1.2 対象モデル
```php
// 監査対象モデル（優先度順）
1. MonthlyInvoice      // 請求データ（ステータス変更・金額変更・PDF出力）
2. Resident            // 入居者マスタ（家賃・管理費・ステータス変更）
3. ChargeItem          // 請求項目マスタ（単価・税区分変更）
4. Facility            // 施設設定（銀行口座・インボイス番号・印影変更）
5. TaxSetting          // 税率設定（税率・適用期間変更）
6. User                // ユーザー（権限・施設割当変更）
7. DailyCharge         // 日次利用料（作成・更新・削除）
8. AccountingExportProfile // 会計連携プロファイル
```

### 1.3 実装手順
1. `composer require spatie/laravel-activitylog`
2. マイグレーション実行（`activity_log`テーブル）
3. 設定ファイル公開・カスタマイズ（`config/activitylog.php`）
4. 各モデルに `LogsActivity` トレイト追加
5. `getActivitylogOptions()` で記録項目カスタマイズ
6. Filamentリソース `ActivityLogResource` 作成（閲覧用）
7. キュー設定（`queue_connection` = `database` / `redis`）

### 1.4 テスト計画
| テスト種別 | テストケース | 期待結果 |
|-----------|-------------|---------|
| Unit | MonthlyInvoice作成時にログ記録される | activity_logに1レコード追加 |
| Unit | MonthlyInvoiceステータス変更時に旧値・新値記録 | `properties.attributes` に差分 |
| Unit | Resident家賃変更時に金額差分記録 | old/new 両方記録 |
| Feature | 施設管理者は自施設のログのみ閲覧可能 | ポリシーでフィルタリング |
| Feature | 法人管理者は全施設ログ閲覧可能 | 権限により全件取得 |
| Performance | 1000件一括更新時のパフォーマンス | キュー非同期でレスポンス < 500ms |

---

## 2. 請求締め自動化スケジューラ

### 2.1 要件
- 毎月1日 02:00 に前月分の請求データを自動生成
- 既存データ（Unbilled）は再計算更新、Billed/Paid はスキップ
- 実行結果をログ出力・通知（成功件数・スキップ件数・エラー件数）
- 手動実行も可能（`php artisan billing:generate-monthly`）
- 失敗時のリトライ・アラート機能

### 2.2 実装手順
1. `GenerateMonthlyInvoices` コマンド作成（`app/Console/Commands/GenerateMonthlyInvoices.php`）
2. `InvoiceCalculationService::generateMonthlyInvoices()` 活用
3. オプション: `--year-month=2026-10`、`--facility-id=1`、`--force`（確定済みも再計算）
4. `routes/console.php` または `Kernel.php` でスケジュール登録
5. 実行結果を `activitylog` に記録
6. 失敗時は例外を投げてスケジューラのリトライ機構に任せる

### 2.3 コマンド仕様
```bash
# 基本実行（前月分を自動判定）
php artisan billing:generate-monthly

# 特定年月指定
php artisan billing:generate-monthly --year-month=2026-10

# 特定施設のみ
php artisan billing:generate-monthly --facility-id=1

# 確定済みも強制再計算（管理者のみ）
php artisan billing:generate-monthly --force
```

### 2.4 テスト計画
| テスト種別 | テストケース | 期待結果 |
|-----------|-------------|---------|
| Feature | 通常実行で新規作成される | created > 0, updated = 0, skipped = 0 |
| Feature | 2回目実行でスキップされる | created = 0, updated = 0, skipped > 0 |
| Feature | --force で確定済みも更新 | updated > 0 |
| Feature | 施設指定で該当施設のみ処理 | 他施設データは影響なし |
| Feature | エラー時のログ・通知 | activitylogにエラー記録 |
| Unit | 年月自動判定ロジック | 1月実行時は前年12月を対象 |

---

## 3. 自動バックアップ・DR体制

### 3.1 要件
- **RPO: 24時間**（日次バックアップ）
- **RTO: 4時間**（リストア手順書・テスト済み）
- DB（MySQL/PostgreSQL）+ Storage（PDF・アップロードファイル）の両方
- 地理的冗長（別リージョン/別ストレージへのコピー）
- 四半期ごとのリストアテスト実施・記録

### 3.2 実装手順
1. バックアップスクリプト作成（`scripts/backup.sh` / `scripts/backup.ps1`）
   - `mysqldump` / `pg_dump` でDBダンプ（圧縮・暗号化）
   - `storage/app` をアーカイブ（tar.gz）
   - S3互換ストレージ / 別サーバーへアップロード
2. リストアスクリプト作成（`scripts/restore.sh`）
   - ダウンロード→解凍→DBリストア→ファイルリストア
3. cron / Windowsタスクスケジューラ登録
4. リストアテスト手順書作成（`docs/backup-restore-procedure.md`）
5. 監視・アラート（バックアップ失敗時の通知）

### 3.3 テスト計画
| テスト種別 | テストケース | 期待結果 |
|-----------|-------------|---------|
| Integration | バックアップスクリプト実行 | ファイル生成・アップロード成功 |
| Integration | リストアスクリプト実行 | DB・ファイル完全復元 |
| Integration | ポイントインタイムリカバリ | 指定日時のデータに復元 |
| DR Drill | 四半期リストアテスト実施 | 手順書通りに < 4時間で完了 |

---

## 4. 2FA必須化（管理画面）

### 4.1 要件
- 管理画面（Filament）ログイン時に TOTP（Google Authenticator等）必須
- 初回ログイン時のQRコード表示・セットアップフロー
- バックアップコード生成・保存
- 施設管理者・法人管理者両方に適用

### 4.2 実装手順
1. `composer require pragmarx/google2fa-laravel` または `laravel-fortify` 活用
2. `User` モデルに `two_factor_secret`、`two_factor_recovery_codes` カラム追加（マイグレーション）
3. Filament認証カスタマイズ（`Login.php` ページ拡張）
4. 2FA設定ページ作成（プロフィールメニューからアクセス）
5. ミドルウェアで管理画面ルートに2FA必須化
6. 信頼デバイス機能（30日間スキップ）オプション

### 4.3 テスト計画
| テスト種別 | テストケース | 期待結果 |
|-----------|-------------|---------|
| Feature | 2FA未設定ユーザーのログイン | QRコード表示・設定強制 |
| Feature | 正しいTOTPでログイン成功 | ダッシュボード表示 |
| Feature | 間違ったTOTPでログイン失敗 | エラーメッセージ表示 |
| Feature | バックアップコードでログイン | 1回限り有効・その後無効化 |
| Feature | 信頼デバイスでスキップ | 30日間2FA不要 |

---

## 5. 請求承認ワークフロー

### 5.1 要件
- Unbilled → **PendingApproval** → Billed の3段階ステータス
- 施設管理者が作成・申請、法人管理者（または施設長）が承認
- 差戻し機能（理由必須）
- 承認履歴の記録（activitylog連携）
- メール通知（申請時・承認時・差戻し時）

### 5.2 実装手順
1. `InvoiceStatus` Enum に `PendingApproval` 追加
2. `MonthlyInvoice` に `approved_by`、`approved_at`、`rejection_reason` カラム追加
3. `MonthlyInvoiceResource` にアクション追加：
   - `submitForApproval`（施設管理者）
   - `approve` / `reject`（承認権限者）
4. ポリシーで権限制御（`approveInvoices` 権限）
5. Filament Notification / Mail で通知

### 5.3 テスト計画
| テスト種別 | テストケース | 期待結果 |
|-----------|-------------|---------|
| Feature | 施設管理者が申請→ステータスPendingApproval | status変更・通知送信 |
| Feature | 法人管理者が承認→ステータスBilled | status変更・承認者記録・通知 |
| Feature | 法人管理者が差戻し→ステータスUnbilled | 理由記録・通知 |
| Feature | 施設管理者は承認不可 | 403 Forbidden |
| Unit | 承認履歴がactivitylogに記録 | 適切なプロパティで記録 |

---

## 6. インボイスPDF要件チェック関数

### 6.1 要件
- 適格請求書（インボイス）要件の機械的検証
- PDF生成前に必須項目存在確認→不足時エラー
- チェック項目：
  - 適格請求書発行事業者登録番号（T+13桁）
  - 税率ごとの合計対価額（標準10%・軽減8%・非課税）
  - 税率ごとの消費税額
  - 適用税率の明記
  - 取引年月日・取引内容

### 6.2 実装手順
1. `InvoicePdfService::validateInvoiceRequirements(MonthlyInvoice $invoice): ValidationResult` 作成
2. `InvoiceDataProvider` で必要データ取得・検証
3. 不足項目がある場合は `InvoiceValidationException` 投げる
4. `InvoicePdfGenerator` 生成前にバリデーション実行
5. テンプレート側でも必須項目の出力保証（Bladeで `@isset` 等）

### 6.3 テスト計画
| テスト種別 | テストケース | 期待結果 |
|-----------|-------------|---------|
| Unit | 登録番号なし施設で検証 | エラー「登録番号が未設定」 |
| Unit | 軽減税率品目があるのに内訳欠落 | エラー「軽減税率内訳が不足」 |
| Unit | 全項目揃っている場合 | 検証パス（true返却） |
| Feature | PDF生成時にバリデーション実行 | 不足時は例外・PDF生成されない |

---

## 7. 入金消込時の証憑添付必須化

### 7.1 要件
- `markAsPaid` アクション実行時に証憑ファイル（通帳画像・入金データCSV等）アップロード必須
- ファイル種別：画像（jpg/png/pdf）・CSV・Excel
- 最大ファイルサイズ：10MB
- 保存先：`storage/app/payment-evidences/{year-month}/{invoice-id}/`
- 証憑なしでは入金消込不可（バリデーション）

### 7.2 実装手順
1. `MonthlyInvoice` に `payment_evidence_path` カラム追加（マイグレーション）
2. `MonthlyInvoiceResource` の `markAsPaid` アクションをフォーム化
3. ファイルアップロードフィールド追加（`FileUpload` コンポーネント）
4. バリデーションルール追加（`required|file|max:10240|mimes:jpg,png,pdf,csv,xlsx`）
5. 保存処理でファイル移動・パス記録
6. 一覧表示で証憑アイコン・ダウンロードリンク表示

### 7.3 テスト計画
| テスト種別 | テストケース | 期待結果 |
|-----------|-------------|---------|
| Feature | 証憑なしで入金消込実行 | バリデーションエラー |
| Feature | 画像ファイル添付で入金消込 | 成功・ファイル保存・パス記録 |
| Feature | 不正なファイル形式でアップロード | バリデーションエラー |
| Feature | 10MB超過ファイル | バリデーションエラー |
| Feature | 証憑ダウンロードリンク動作 | 署名付きURLでダウンロード可能 |

---

## 8. PDFパスワード保護機能

### 8.1 要件
- 請求書・領収書PDFにパスワード保護オプション
- パスワード生成ルール：入居者ごと固有（例：部屋番号+生年月日下4桁）または施設共通
- 設定は施設単位で ON/OFF・ルール選択可能
- 復号化ライブラリ非依存（標準PDFパスワード）

### 8.2 実装手順
1. `Facility` に `pdf_password_enabled`、`pdf_password_rule` カラム追加
2. `InvoicePdfService` / `DomPdfRenderer` でパスワード適用オプション追加
3. DomPDF の `setEncryption()` 使用（owner password = 施設管理用、user password = 入居者用）
4. パスワード生成サービス `PdfPasswordGenerator` 作成
5. PDFテンプレート設定画面でパスワード設定UI追加

### 8.3 テスト計画
| テスト種別 | テストケース | 期待結果 |
|-----------|-------------|---------|
| Unit | パスワード生成ルール適用 | 正しいパスワード生成 |
| Unit | DomPDF暗号化適用 | PDFがパスワード保護される |
| Feature | 施設設定ONでPDF生成 | パスワード付きPDF出力 |
| Feature | 施設設定OFFでPDF生成 | パスワードなしPDF出力 |
| Integration | 既知パスワードでPDF開封 | 正常に開ける |

---

## 9. 請求書に計算根拠セクション追加

### 9.1 要件
- 請求書PDFに「計算根拠・用語解説」セクション追加
- 内容：
  - 日割り計算式（在籍日数 ÷ 月日数 × 月額）
  - 税区分理由（非課税・標準課税・軽減課税の根拠法令）
  - 品目別内訳の凡例
  - 支払期限・振込先の案内
  - よくある質問へのリンク（QRコード）

### 9.2 実装手順
1. `resources/views/pdf/components/calculation-basis.blade.php` 作成
2. `InvoicePdfData` DTO に計算根拠データ追加（`proration_formula`、`tax_basis` 等）
3. `InvoiceDataProvider` で計算式・税区分理由を組み立て
4. `InvoiceTemplate` でセクション挿入（明細テーブル後・フッター前）
5. 施設設定で表示ON/OFF切替可能に

### 9.3 テスト計画
| テスト種別 | テストケース | 期待結果 |
|-----------|-------------|---------|
| Feature | 日割り対象入居者の請求書 | 計算式が正しく表示 |
| Feature | 全日在籍入居者の請求書 | 「日割り対象外」と表示 |
| Feature | 軽減税率品目ありの請求書 | 軽減税率根拠が表示 |
| Feature | 設定OFFで請求書生成 | セクション非表示 |

---

## 10. 品目マスタに表示名・説明フィールド追加

### 10.1 要件
- `ChargeItem` に `display_name`（入居者向け表示名）・`description`（説明）追加
- 例：「紙おむつ(パンツタイプ)」→「紙おむつ・パンツタイプ（1枚180円）」
- PDF明細・請求書・ポータルで表示名を使用
- 管理画面では従来名（内部名）も併記

### 10.2 実装手順
1. マイグレーション作成（`display_name`、`description` カラム追加）
2. `ChargeItem` モデルにアクセサ・ミューテタ追加
3. `ChargeItemResource` フォーム・テーブルにフィールド追加
4. `DailyChargeAggregator` / `InvoiceDataProvider` で表示名優先使用
5. 既存データの移行（シーダーで初期値設定）

### 10.3 テスト計画
| テスト種別 | テストケース | 期待結果 |
|-----------|-------------|---------|
| Unit | display_name設定時はそれを表示 | PDF・一覧でdisplay_name使用 |
| Unit | display_name未設定時はname使用 | フォールバック動作 |
| Feature | 作成・編集フォームで入力可能 | バリデーション・保存成功 |
| Feature | PDF明細で表示名反映 | 請求書PDFに表示名出力 |

---

## 共通：リグレッションテスト戦略

### テスト実行順序
```bash
# 1. 既存テスト全件実行（ベースライン確認）
./vendor/bin/pest

# 2. 各機能実装ごとに該当テスト実行
./vendor/bin/pest --filter="ActivityLog"
./vendor/bin/pest --filter="GenerateMonthlyInvoices"
./vendor/bin/pest --filter="TwoFactor"
# ...

# 3. 全テスト再実行（リグレッション確認）
./vendor/bin/pest --parallel

# 4. カバレッジ確認
./vendor/bin/pest --coverage --min=80
```

### テスト分類・命名規則
```
tests/
├── Feature/
│   ├── AuditLogTest.php                    # 監査ログ
│   ├── GenerateMonthlyInvoicesTest.php     # 請求自動化
│   ├── BackupRestoreTest.php               # バックアップ/リストア
│   ├── TwoFactorAuthenticationTest.php     # 2FA
│   ├── InvoiceApprovalWorkflowTest.php     # 承認ワークフロー
│   ├── InvoicePdfValidationTest.php        # PDF要件検証
│   ├── PaymentEvidenceTest.php             # 証憑添付
│   ├── PdfPasswordProtectionTest.php       # PDFパスワード
│   ├── CalculationBasisDisplayTest.php     # 計算根拠表示
│   └── ChargeItemDisplayNameTest.php       # 品目表示名
├── Unit/
│   ├── Services/
│   │   ├── ActivityLogServiceTest.php
│   │   ├── GenerateMonthlyInvoicesCommandTest.php
│   │   ├── PdfPasswordGeneratorTest.php
│   │   └── InvoiceValidatorTest.php
│   └── Models/
│       ├── ChargeItemDisplayNameTest.php
│       └── MonthlyInvoiceApprovalTest.php
```

### CI/CD パイプライン追加項目
```yaml
# .github/workflows/ci.yml への追加
- name: Run Critical Feature Tests
  run: ./vendor/bin/pest --filter="AuditLog|GenerateMonthlyInvoices|TwoFactor|InvoiceApproval|InvoicePdfValidation|PaymentEvidence|PdfPassword|CalculationBasis|ChargeItemDisplayName"
  
- name: Full Regression Suite
  run: ./vendor/bin/pest --parallel --coverage --min=80
```

---

## 実装スケジュール（2026 Q4: 10-12月）

| 週 | 実装項目 | マイルストーン |
|---|---------|---------------|
| Week 1-2 | 1. 監査ログ導入 | 全主要モデルでログ記録開始 |
| Week 2-3 | 2. 請求締め自動化 | スケジューラ登録・手動実行確認 |
| Week 3-4 | 3. バックアップ/DR | スクリプト完成・初回リストアテスト |
| Week 4-5 | 4. 2FA必須化 | 管理画面で2FA必須化完了 |
| Week 5-6 | 5. 承認ワークフロー | 申請・承認・差戻しフロー完成 |
| Week 6 | 6. PDF要件検証 | 生成時バリデーション動作確認 |
| Week 6-7 | 7. 証憑添付必須化 | 入金消込フロー完成 |
| Week 7 | 8. PDFパスワード | 請求書・領収書で暗号化動作確認 |
| Week 7-8 | 9. 計算根拠表示 | PDFにセクション追加確認 |
| Week 8 | 10. 品目表示名 | マスタ・PDF・ポータルで反映確認 |
| Week 9-10 | 全項目統合テスト・リグレッション | 全テストパス・カバレッジ80%以上 |
| Week 11-12 | ドキュメント整備・デプロイ準備 | 手順書・運用マニュアル完成 |

---

## リスク・依存関係

| リスク | 影響度 | 対策 |
|--------|--------|------|
| spatie/laravel-activitylog のパフォーマンス影響 | 中 | キュー非同期・サンプリング設定・インデックス最適化 |
| 既存データとの互換性（ステータス追加等） | 高 | マイグレーションでデフォルト値設定・段階的移行 |
| DomPDF パスワード暗号化の日本語フォント互換性 | 中 | 事前検証・代替ライブラリ検討（tcpdf等） |
| スケジューラ実行環境（本番・ステージング差異） | 低 | 環境変数で制御・ドライランモード実装 |

---

## 完了基準（Definition of Done）

- [ ] 全実装項目のコードレビュー完了
- [ ] 全Feature/Unitテストパス（既存含む）
- [ ] カバレッジ 80% 以上維持
- [ ] 本番同等環境での動作確認完了
- [ ] 運用ドキュメント・手順書更新完了
- [ ] ステークホルダー（経営・現場・税理士）へのデモ・合意完了