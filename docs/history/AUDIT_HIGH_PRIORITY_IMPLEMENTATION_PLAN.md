# 監査指摘事項対応 緊急実装計画書

## 目的
施設利用者視点の包括的監査レポートで指摘された緊急対応項目（P0-1～P0-3、P1-4、P1-5、P2-4）を実装し、セキュリティ、監査対応、透明性、および利用者体験を向上させる。

## 現在の実装状況サマリー（2026-10-13 時点）

| 項目 | 監査ID | 状況 | 備考 |
|------|-------|------|------|
| PDFパスワード保護 | P0-1 | ✅ **実装済み** | `PdfPasswordProtector`, `config/pdf.php`, `InvoicePdfService` で完全実装 |
| 監査ログ基盤 | P0-2 | ✅ **実装済み** | `spatie/laravel-activitylog` 導入済み、全主要モデルでログ記録中、`ActivityLogResource` で閲覧可能 |
| 施設設定変更履歴・承認フロー | P0-3 | ❌ **未実装** | 要実装 |
| 請求書計算根拠表示 | P1-4 | ✅ **実装済み** | `calculation-basis.blade.php`, `InvoicePdfData::buildCalculationBasis()`, テンプレート設定連携済み |
| 領収書番号連番管理・ギャップ検知 | P1-5 | ❌ **未実装** | 番号生成のみ実装、検知機能なし |
| 品目マスタ表示名・説明追加 | P2-4 | 🟡 **部分実装** | DB・Model・ItemMasterResourceは完了。ChargeItemResource未対応、PDF生成でdisplay_name未使用 |
| 入居者メールアドレス | - | ❌ **未実装** | Google Drive連携・メール送信機能の前提条件 |

---

## 対象ファイル（未実装・未完了項目のみ）

### P0-3: 施設設定変更履歴・承認フロー
- `database/migrations/xxxx_xx_xx_xxxxxx_create_facility_setting_histories_table.php`
- `app/Models/FacilitySettingHistory.php`
- `app/Services/FacilitySettingService.php`
- `app/Filament/Resources/FacilitySettingHistoryResource.php` (新規)

### P1-5: 領収書番号連番管理・ギャップ検知
- `app/Services/ReceiptNumberService.php` (新規)
- `app/Models/MonthlyInvoice.php` (`markAsPaid` 修正)

### P2-4 完全実装: 品目マスタ表示名・説明
- `app/Filament/Resources/ChargeItemResource.php` (フォーム・テーブル追加)
- `app/DTOs/Pdf/DailyChargePdfData.php` (display_name 使用に変更)

### 入居者メールアドレス追加
- `database/migrations/xxxx_xx_xx_xxxxxx_add_email_to_residents_table.php`
- `app/Models/Resident.php` (fillable 追加)
- `app/Filament/Resources/ResidentResource.php` (フォーム・テーブル追加)

---

## 実装フェーズとタスク（未実装・未完了項目のみ）

### フェーズ1: 施設設定変更履歴・承認フロー基盤（P0-3 / 見積もり: 3-4日）
#### タスク1-1: 施設設定変更履歴テーブル・モデル実装
- ファイル:
  - `database/migrations/xxxx_xx_xx_xxxxxx_create_facility_setting_histories_table.php`
  - `app/Models/FacilitySettingHistory.php`
- 変更点:
  - `facility_setting_histories` テーブル作成（カラム: id, facility_id, changed_by, setting_key, old_value, new_value, changed_at, approval_status, approved_by, approved_at, rejection_reason）
  - 変更種別: `bank`, `billing`, `invoice_registration_number`, `seal_path`, `basic_info`, `accounting_api` 等
  - `FacilitySettingHistory` Eloquentモデル作成（リレーション: facility, changedBy, approver）
- テスト:
  - マイグレーション正常実行
  - モデルリレーション動作確認

#### タスク1-2: 施設設定サービス・承認フロー基盤実装
- ファイル: `app/Services/FacilitySettingService.php`, `app/Filament/Resources/FacilitySettingHistoryResource.php`
- 変更点:
  - 設定変更時の自動履歴記録メソッド `recordChange()`
  - 承認フロー: `pending` → `approved` / `rejected`
  - 施設管理者は申請のみ、法人管理者が承認
  - 承認前は設定反映しない（暫定反映せず履歴のみ）
  - 既存 `Facility::toConfigArray()` は変更せず、履歴は追加記録のみ
- テスト:
  - 設定変更申請→承認→反映フロー確認
  - 却下時の動作確認
  - 履歴一覧表示確認（法人管理者は全施設、施設管理者は自施設のみ）

### フェーズ2: 領収書番号連番管理・ギャップ検知（P1-5 / 見積もり: 2-3日）
#### タスク2-1: 領収書番号サービス実装
- ファイル: `app/Services/ReceiptNumberService.php`
- 変更点:
  - 連番生成ロジック一元管理: `REC-{YYYYMM}-{4桁連番}`
  - 施設別・年月別の連番カウンター管理（DBテーブル `receipt_number_counters` または Redis）
  - ギャップ検知メソッド: `detectGaps(string $yearMonth, int $facilityId): array`
  - 重複防止: ユニーク制約 + アトミックな採番（DBトランザクション + 行ロック）
  - 手動割当・再発行時の連番スキップ記録
- テスト:
  - 連番生成の正確性（施設別・年月別）
  - ギャップ検知の正確性（欠番・削除済み番号の検出）
  - 並行実行時の重複防止確認

#### タスク2-2: MonthlyInvoice への統合
- ファイル: `app/Models/MonthlyInvoice.php` (`markAsPaid` メソッド修正)
- 変更点:
  - `ReceiptNumberService` を使用して番号生成
  - 番号割当時の履歴記録（監査ログ連携・自動で行われる）

### フェーズ3: 品目マスタ表示名・説明の完全実装（P2-4 / 見積もり: 1-2日）
#### タスク3-1: ChargeItemResource への表示名・説明フィールド追加
- ファイル: `app/Filament/Resources/ChargeItemResource.php`
- 変更点:
  - フォームに `display_name` (TextInput), `description` (Textarea) 追加
  - テーブルに `display_name` カラム追加（検索・ソート・表示切替対応）
  - ItemMasterResource と同等の機能を提供
- テスト:
  - 入力・保存・表示確認
  - ItemMasterResource との整合性確認

#### タスク3-2: PDF生成で display_name を使用
- ファイル: `app/DTOs/Pdf/DailyChargePdfData.php`
- 変更点:
  - `itemName` 生成時に `chargeItem->display_name ?? chargeItem->name` を使用
  - 既存データ（display_name=null）は従来通り name を使用
- テスト:
  - 表示名あり/なしでのPDF出力確認
  - 請求書・領収書両方で確認

### フェーズ4: 入居者メールアドレス追加（将来のメール送信機能前提 / 見積もり: 1-2日）
#### タスク4-1: マイグレーション・モデル・Filament対応
- ファイル:
  - `database/migrations/xxxx_xx_xx_xxxxxx_add_email_to_residents_table.php`
  - `app/Models/Resident.php`
  - `app/Filament/Resources/ResidentResource.php`
- 変更点:
  - `residents` テーブルに `email` (string, nullable), `email_cc` (string, nullable), `locale` (string, 5文字, default 'ja') 追加
  - メールバリデーション追加（形式チェック、重複許可）
  - Filamentフォーム・テーブルに追加
- テスト:
  - マイグレーション正常実行
  - 入力・保存・表示確認

---

## 実装順序と依存関係

1. **フェーズ1（施設設定履歴・承認フロー）** を最優先で実装
   - 監査レポート P0-3 対応、コンプライアンス要件のため最優先
   - 他機能への依存なし、独立実装可能

2. **フェーズ2（領収書番号管理）** を次に実装
   - 税務・監査要件（P1-5）対応
   - `MonthlyInvoice::markAsPaid()` 修正のみで既存フロー影響最小

3. **フェーズ3（品目表示名完全実装）** を並行実装可能
   - 既存実装の完成（ChargeItemResource, PDF連携）
   - 影響範囲小、即効性あり

4. **フェーズ4（入居者メール）** を後続実装
   - 将来の「Google Drive連携・一括メール送信」機能の前提
   - 独立実装可能

---

## リグレッション防止テスト戦略（更新版）

### 既存テストの活用
- `php artisan test` で全テスト実行、リグレッションなしを確認
- 特に以下の既存テストスイートを重点確認:
  - `InvoicePdfServiceTest` / `InvoicePdfServiceZipTest` - PDF生成・ZIP生成
  - `InvoiceCalculationServiceTest` - 請求計算ロジック
  - `FilamentMonthlyInvoiceResourceTest` - 管理画面操作
  - `MonthlyInvoiceModelTest` / `MonthlyInvoiceObserverTest` - モデル動作
  - `FilamentItemMasterResourceTest` - 品目マスタ操作（あれば）

### 新規テスト追加項目
1. **施設設定履歴テスト**
   - 設定変更時の自動履歴記録
   - 承認フロー（申請→承認→反映 / 申請→却下）
   - 却下時の設定値未反映確認
   - 権限別閲覧制御（法人vs施設管理者）

2. **領収書番号サービステスト**
   - 施設別・年月別連番生成
   - ギャップ検知（削除・手動割当による欠番）
   - 並行実行時の重複防止（DBロック or Redis atomic）

3. **品目表示名テスト**
   - 表示名あり/なしでのPDF出力差分確認
   - ChargeItemResource / ItemMasterResource 両方での入力・表示

4. **入居者メールテスト**
   - バリデーション（形式・重複許可）
   - Filamentフォーム保存・表示

---

## リスクと Mitigation（更新版）

| リスク | 影響度 | Mitigation |
|--------|--------|------------|
| 施設設定承認フロー導入による既存設定変更フローの変更 | 高 | 既存 `Facility` モデルの setter/getter は変更せず、Service層で承認フローを挟む。最初は「記録のみ」で運用開始し、承認必須は後段階で。 |
| 領収書番号採番ロジック変更による既存番号との整合性 | 高 | 既存データはそのまま、新規発行分から新ロジック適用。移行期間中は両方併存可能な設計に。 |
| 品目表示名フィールド追加による既存PDFレイアウトへの影響 | 低 | 表示名がない場合は従来通り `name` を使用。テンプレート側で null チェック実装済み。 |
| 入居者メール追加による既存データ互換性 | 低 | 全カラム NULL 許容、デフォルト値なし。既存データ影響なし。 |

---

## 完了基準（更新版）

1. ✅ PDFパスワード保護機能（実装済み・動作確認済み）
2. ✅ 監査ログ自動記録（実装済み・主要操作で記録確認済み）
3. ⬜ 施設設定変更が履歴として記録され、承認フローが動作すること
4. ✅ 請求書PDFに計算根拠セクションが正しく表示されること（実装済み）
5. ⬜ 領収書番号の連番管理・ギャップ検知機能が動作すること
6. 🟡 品目マスタに入居者向け表示名・説明フィールドが追加され、PDFに反映されること（ItemMasterResource完了、ChargeItemResource・PDF連携未完了）
7. ⬜ 入居者にメールアドレス・CC・言語設定フィールドが追加されること
8. ✅ 新規テストが追加され、既存テストがすべてパスすること
9. ✅ セキュリティスキャンで重大な脆弱性が検出されないこと

---

## 次のステップ

1. **即着手**: フェーズ1（施設設定履歴・承認フロー）のマイグレーション・モデル・サービス作成
2. **並行実施**: フェーズ2（領収書番号サービス）の設計・実装
3. **短期完了**: フェーズ3（品目表示名完全実装 - ChargeItemResource, DailyChargePdfData）
4. **後続**: フェーズ4（入居者メール）の実装
5. **将来拡張**: 入居者メールを前提とした「Google Drive連携・一括メール送信機能」の実装計画策定

---

## 実装済み機能の活用メモ（参考）

### PDFパスワード保護（P0-1 完了）
- 有効化: `.env` で `PDF_PASSWORD_ENABLED=true`
- モード: `PDF_PASSWORD_MODE=fixed` または `resident_birthday`
- 固定パスワード: `PDF_PASSWORD_FIXED=任意の文字列`
- 入居者生年月日モード: `birth_date` カラム必須（実装済み）

### 監査ログ（P0-2 完了）
- `ActivityLogResource` で管理画面から確認可能（法人管理者のみ）
- 全主要モデル（`MonthlyInvoice`, `Resident`, `Facility`, `ChargeItem`, `TaxSetting`, `User` 等）で自動記録
- イベント種別: `created`, `updated`, `deleted`, `status_changed`
- 施設管理者は自施設分のみ閲覧可能（ポリシー実装済み）
- `batch_uuid`, `event` カラム追加済み（2026-10-09）

### 計算根拠表示（P1-4 完了）
- テンプレート設定 `show_calculation_basis` で表示/非表示切替可能
- 日割り計算の詳細（月日数 × 在籍日数）、税率別内訳（標準10%・軽減8%・非課税）を自動表示
- `InvoicePdfData::buildCalculationBasis()` でデータ構築、`calculation-basis.blade.php` で描画

### 品目マスタ拡張（P2-4 部分完了）
- `display_name` (請求書・領収書表示用), `description` (内部備考) カラム追加済み
- `ItemMasterResource` で入力・一覧表示対応済み
- **残作業**: `ChargeItemResource` 対応、`DailyChargePdfData` で `display_name` 使用