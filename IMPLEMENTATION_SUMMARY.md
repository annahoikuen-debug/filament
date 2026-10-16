## 実装サマリー（ステップ 1〜5）

### ステップ 1: 税率・税区分マスタ（`TaxSetting`）のバージョン管理と有効期間バリデーションの実装
- **変更点**:
  - `App\Models\TaxSetting` モデルに `booted` メソッドを追加し、`saving` イベントで有効な税率設定の日付範囲重複をバリデーション。
  - 重複がある場合は `ValidationException` をスローし、`effective_from` と `effective_until` フィールドにエラーメッセージを設定。
  - 重複チェックは、有効な税率設定 (`is_active = true`) に対してのみ実行。
  - 更新時は自身のレコードを除外してチェック。
- **テスト**:
  - `tests/Unit/TaxSettingTest.php` を作成し、以下を検証：
    - 有効な税率設定間での日付範囲重複を防止すること。
    - 重複しない範囲での作成を許可すること。
    - 非アクティブな設定での重複を許可すること。
    - 更新時の日付変更なしを許可すること。
    - アクティブ状態への更新（重複なし）を許可すること。

### ステップ 2: 品目マスタ（`ChargeItem`）のインボイス適格区分・軽減税率根拠の拡張
- **変更点**:
  - `charge_items` テーブルに `tax_legal_basis` カラムを追加（nullable、税区分の法律的根拠を保存）。
  - `App\Models\ChargeItem` モデル：
    - `$fillable` に `tax_legal_basis` を追加。
    - `getActivitylogOptions` に `tax_legal_basis` を追加し、アクティビティログに記録。
- **マイグレーション**:
  - `2026_10_13_061000_add_tax_legal_basis_to_charge_items_table.php` を作成。

### ステップ 3: 賃料・共益費の内訳分離マスタの導入
- **変更点**:
  - `residents` テーブルに以下のカラムを追加：
    - `base_rent_non_taxable`（非課税部分の基本家賃、デフォルト 0）
    - `base_rent_taxable`（課税部分の基本家賃、デフォルト 0）
    - `base_management_fee_non_taxable`（非課税部分の基本管理費、デフォルト 0）
    - `base_management_fee_taxable`（課税部分の基本管理費、デフォルト 0）
  - `App\Models\Resident` モデル：
    - `$fillable` に上記 4 フィールドを追加。
    - `$casts` に上記 4 フィールドを追加（integer 型）。
    - `booted` メソッドの `saving` イベント内で、`base_rent` と `base_management_fee` を分解フィールドの合計値に自動更新。
    - `getActivitylogOptions` に上記 4 フィールドを追加し、アクティビティログに記録。
- **マイグレーション**:
  - `2026_10_13_062000_add_rent_management_fee_breakdown_to_residents_table.php` を作成。

### ステップ 4: インボイス制度「適格請求書」必須要件チェックバリデーションの実装
- **変更点**:
  - `App\Services\InvoicePdfService` クラスに `validateInvoiceRequirements` メソッドを追加。
  - `generateInvoicePdf` と `generateReceiptPdf` メソッドからこのバリデーションメソッドを呼び出すように修正。
  - バリデーション内容：
    1. 施設の適格請求書登録番号の存在確認
    2. 登録番号のフォーマット検証（T + 13桁数字）
    3. tax_breakdown の存在と構造確認
    4. 標準税率（10%）の項目存在確認
    5. 軽減税率（8%）の項目存在確認
    6. 各税区分の課税額と税額の存在確認
- **テスト**:
  - `tests/Feature/InvoicePdfServiceTest.php` に以下のテストを追加：
    - 適格請求書登録番号が未設定の場合は例外が発生すること
    - 適格請求書登録番号のフォーマットが不正な場合は例外が発生すること
    - 税区分別内訳が存在しない場合は例外が発生すること
    - 標準税率の項目が不足している場合は例外が発生すること
    - 軽減税率の項目が不足している場合は例外が発生すること
    - 課税額が不足している場合は例外が発生すること
    - 税額が不足している場合は例外が発生すること
    - 有効なデータの場合は例外が発生しないこと

### ステップ 5: 軽減税率別（8%・10%）の税額端数処理ルールの統一と監査
- **変更点**:
  - `App\Services\Invoice\TaxCalculator` クラスを修正し、消費税の端数処理を切り捨て（PHP_ROUND_FLOOR）に統一。
  - 定数 `TAX_ROUNDING_MODE = PHP_ROUND_FLOOR` を追加し、ドキュメントコメントで日本の消費税法に準拠していることを明記。
  - `calculate` メソッドで `round()` 関数に丸めモードを明示的に渡すように変更。
- **テスト**:
  - `tests/Unit/TaxCalculatorTest.php` を作成し、以下を検証：
    - 標準税率・軽減税率の両方で切り捨て丸めが正しく適用されること
    - 0.5未満の端数は切り捨てとなること（例: 5円×10% = 0.5円 → 0円）
    - 端数がある場合は必ず切り捨てとなること（例: 1001円×10% = 100.1円 → 100円）
    - tax_breakdown が正しく計算され、丸め後の税額が反映されること
    - ゼロ金額の場合も正しく処理されること

### 次のステップ
ステップ 6 以降（消費税申告書集計レポート出力機能の実装）については、次のインタラクションで続行します。