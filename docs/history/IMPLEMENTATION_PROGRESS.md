## 実装サマリー（ステップ 1〜3）

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

### 次のステップ
ステップ 4 以降（マルチテナント認可制御とデータ分離の厳格化）については、次のインタラクションで続行します。