# 業務改善・オペレーション簡便化 詳細実装計画書（全24ステップ）

## 1. 概要と目標
本計画書は、施設・経理現場のオペレーション負荷を大幅に削減し、ヒューマンエラーをゼロに近づけるための3大改善テーマを**1〜24の具体的な実装ステップ**に分解したものです。既存の請求計算・PDF生成・会計連携などの既存機能に影響を与えないよう、**各フェーズでリグレッション防止テスト**を網羅的に組み込んでいます。

---

## 2. 改善テーマと対象領域

| テーマ | 目的 | 主な対象ファイル・コンポーネント |
| :--- | :--- | :--- |
| **Theme A: 全銀口座振替・自動消込連携** | 銀行引落データの出力と、結果取込による自動リコンサイル（消込作業80%削減） | `app/Services/Banking/ZenginService.php`<br>`app/Models/ResidentBankAccount.php`<br>`MonthlyInvoiceResource.php` |
| **Theme B: 自費サービス・プリセット一括入力** | 複数品目の定型セット（食事3食、日用品等）のワンクリック一括展開 | `app/Models/DailyChargePreset.php`<br>`app/Filament/Pages/BulkDailyChargeEntry.php` |
| **Theme C: 月次締めオペレーション・ウィザード** | 異常値検知から一括生成、帳票発行、会計締めまでのステップ統一ナビゲーション | `app/Filament/Pages/MonthlyClosingWizard.php`<br>`app/Services/BillingAnomalyDetector.php` |

---

## 3. 全24ステップ詳細実装ロードマップ

```mermaid
flowchart TD
    subgraph Phase1["Phase 1: 全銀口座振替 & 自動消込 (Steps 1-8)"]
        S1[Step 1: 口座情報スキーマ策定] --> S2[Step 2: ZenginFormatter設計]
        S2 --> S3[Step 3: 全銀依頼ファイル生成サービス]
        S3 --> S4[Step 4: 引落結果パーサー実装]
        S4 --> S5[Step 5: 自動消込・ステータス更新サービス]
        S5 --> S6[Step 6: UI: 口座振替依頼出力 & 結果取込モーダル]
        S6 --> S7[Step 7: 単体/統合テスト実装]
        S7 --> S8[Step 8: リグレッションテスト・検証]
    end

    subgraph Phase2["Phase 2: 自費プリセット一括入力 (Steps 9-16)"]
        S9[Step 9: プリセット・マスター設計] --> S10[Step 10: プリセット管理Resource作成]
        S10 --> S11[Step 11: プリセット展開エンジン実装]
        S11 --> S12[Step 12: 曜日/定期スケジュール定義]
        S12 --> S13[Step 13: BulkDailyChargeEntry画面改修]
        S13 --> S14[Step 14: 一括登録トランザクション最適化]
        S14 --> S15[Step 15: 自費計算リグレッションテスト]
        S15 --> S16[Step 16: E2E・操作性検証]
    end

    subgraph Phase3["Phase 3: 月次締めウィザード (Steps 17-24)"]
        S17[Step 17: 月次締め整合性ルール定義] --> S18[Step 18: 異常値・未入力検知エンジン]
        S18 --> S19[Step 19: 月次締めステートマシン設計]
        S19 --> S20[Step 20: ウィザードUI (Filament Wizard) 構築]
        S20 --> S21[Step 21: 一括生成・ZIP・メール連携統合]
        S21 --> S22[Step 22: 会計締めロック機構]
        S22 --> S23[Step 23: 包括的リグレッションテスト]
        S23 --> S24[Step 24: 本番移行・マニュアル/ヘルプ統合]
    end

    Phase1 --> Phase2
    Phase2 --> Phase3
```

---

### 【Phase 1: 全銀協フォーマット連携 & 自動入金消込（ステップ 1〜8）】

#### Step 1: 口座振替・銀行口座モデル・マイグレーション設計
- 入居者（または支払保証人）の銀行口座情報を保持する `resident_bank_accounts` テーブルのマイグレーション作成。
- 金融機関コード（4桁）、支店コード（3桁）、預金種目（普通/当座）、口座番号（7桁）、口座名義人（半角カナ）、委託者コード。
- 暗号化キャスト（`encrypted`）を適用し機密情報を保護。

#### Step 2: 全銀協固定長フォーマット（Zengin）生成エンジンの構築
- `app/Services/Banking/ZenginFormatService.php` を作成。
- ヘッダーレコード（1）、データレコード（2）、トレーラーレコード（8）、エンドレコード（9）の固定長（120バイト/行）、CRLF改行、Shift-JIS/CP932エンコード処理。

#### Step 3: 月次請求データからの口座振替依頼ファイル出力サービス
- 指定年月の `MonthlyInvoice` のうち `payment_method = direct_debit` かつ `status = unbilled` のレコードを抽出。
- 施設ごとの委託者コード・引落日（毎月27日等）を反映して一括生成するロジックを実装。

#### Step 4: 口座振替結果ファイル（返却全銀データ）の解析パーサー実装
- 銀行から返却された結果ファイル（振替済：振替結果コード0、不能：コード1〜4など）のパーサーを作成。
- 不能コード（1:資金不足、2:取引なし、3:預金者都合、8:委託者都合など）のマッピング。

#### Step 5: 自動リコンサイル（消込・ステータス更新）トランザクション処理
- `ZenginReconcileService.php` を作成。
- 振替成功分：`markAsPaid(PaymentMethod::DirectDebit, $paidAt)` をトランザクション内で実行。
- 振替不能分：請求ステータスを `failed`（またはアラートフラグ）とし、不備理由ログを記録。

#### Step 6: Filament UIの実装（依頼出力 & 結果インポート）
- `MonthlyInvoiceResource` のテーブルヘッダー/バルクアクションに「全銀口座振替依頼出力」を追加。
- 銀行結果ファイルをドラッグ＆ドロップできる「全銀結果取込（自動消込）」モーダル画面を実装。
- 取込プレビュー画面（成功件数・不能件数・合計金額の確認画面）の提供。

#### Step 7: 単体テスト & 振替シミュレーションテスト実装
- `tests/Unit/Services/Banking/ZenginFormatServiceTest.php`:
  - 120バイト固定長、パディング（数字右詰めゼロ埋め、カナ左詰めスペース埋め）、改行コード検証。
- `tests/Feature/Banking/ZenginReconcileTest.php`:
  - 結果ファイル取込によるステータス変更・領収書番号自動採番の検証。

#### Step 8: Phase 1 リグレッション防止テスト
- 既存の請求ステータス遷移（手動消込、一括口座振替変更）、PDF領収書生成に影響がないかを検証。
- `tests/Feature/Regression/MonthlyInvoiceLockingTest.php` と連携し、確定済みレコードの整合性を担保。

---

### 【Phase 2: 自費サービス・プリセット一括入力の高度化（ステップ 9〜16）】

#### Step 9: 自費プリセットマスターのDBマイグレーション & モデル作成
- テーブル `daily_charge_presets`（プリセット名、施設ID、説明、並び順）および中間テーブル `daily_charge_preset_items`（preset_id, charge_item_id, default_quantity）を作成。
- 例: 「デイサービス利用パック（昼食 + おやつ + 入浴介助）」「標準日用品パック」。

#### Step 10: プリセット管理画面（Filament Resource）の実装
- `app/Filament/Resources/DailyChargePresetResource.php` を作成。
- プリセットごとの構成品目・数量をリピーター形式で登録・編集可能にする。

#### Step 11: プリセット一括展開サービスの構築
- `app/Services/DailyChargePresetService.php` を作成。
- 選択されたプリセットと複数の入居者ID・対象日付を受け取り、バルクインサート用レコード配列を生成するロジックを分離。

#### Step 12: 曜日パターン・スケジュール一括適用ロジック
- 「毎週月・水・金の利用者を自動選択」「平日のみ適用」など、日付範囲と曜日指定で一括展開できるスケジュール適用メソッドを追加。

#### Step 13: `BulkDailyChargeEntry` ページのUI刷新
- 既存の [BulkDailyChargeEntry.php](file:///e:/seikyu/app/Filament/Pages/BulkDailyChargeEntry.php) を拡張。
- 「単一品目モード」と「プリセット一括モード」をタブ切替可能にする。
- 部屋番号順のリストに加え、「全選択」「フロア別選択」「プリセット対象者ワンクリック選択」を実装。

#### Step 14: 一括登録処理の高速化 & 排他制御
- 25名〜数百名×複数品目の一括作成時に N+1 クエリを防止し、`DailyChargeObserver` を適切にハンドリング。
- 同一日・同一品目の重複登録防止（警告モーダルまたは自動マージ）。

#### Step 15: 自費小計および月次合算リグレッションテスト
- プリセット経由で登録されたデータが、[InvoiceCalculationService.php](file:///e:/seikyu/app/Services/InvoiceCalculationService.php) で正しく月次請求（`service_subtotal`）に反映されるかを検証するテスト。
- `tests/Feature/BulkDailyChargeEntryPresetTest.php` を新規作成。

#### Step 16: UI操作性検証 & 現場テストシナリオ確認
- 入力エラー時のフィードバック、金額のリアルタイム合計プレビュー、登録後のトースト通知を検証。

---

### 【Phase 3: 月次締めオペレーション・ウィザード（ステップ 17〜24）】

#### Step 17: 月次締め整合性・異常値検知ルールの仕様策定
- チェック条件の定義：
  1. 「当月在籍しているのに請求額が0円の入居者」
  2. 「入退去日と請求対象期間の不整合」
  3. 「未確定の下書き（Draft）状態の介護サービス請求」
  4. 「自費利用記録が0件の入居者（長期不在理由の未記載）」
  5. 「前月比で請求金額が±30%以上乖離している異常データ」

#### Step 18: 異常検知サービス `BillingAnomalyDetector` の実装
- `app/Services/BillingAnomalyDetector.php` を作成。
- 対象年月・施設IDを渡すと、上記ルールに基づく Warning / Error リストを返す判定エンジンを構築。

#### Step 19: 月次締めステートマシン & ロック機構の設計
- 請求年月の締め状態を管理する `MonthlyClosingState`（Unclosed / Generated / Approved / Sent / Closed）。
- Closedになった月は、日々の自費や請求データの更新をガード（Observer / Policyで制御）。

#### Step 20: Filament Wizard ページの構築
- `app/Filament/Pages/MonthlyClosingWizard.php` を新規作成。
- Filamentの `Step` コンポーネントを使用し、1画面で視覚的に進捗がわかるUIを提供。
  - **Step 1: 整合性チェック & 異常値確認**（Errorがある場合は次へ進めないバリデーション）
  - **Step 2: 月次請求データの一括生成 & 差額レビュー**
  - **Step 3: 口座振替依頼出力 / 請求書PDF一括ZIP / メール一括配信**
  - **Step 4: 会計仕訳CSVエクスポート & 月次クローズ（確定ロック）**

#### Step 21: 既存サービスとのパイプライン連携
- 既存の [InvoiceCalculationService](file:///e:/seikyu/app/Services/InvoiceCalculationService.php), [InvoicePdfService](file:///e:/seikyu/app/Services/InvoicePdfService.php), [InvoiceCsvExportService](file:///e:/seikyu/app/Services/InvoiceCsvExportService.php), [MailService](file:///e:/seikyu/app/Services/MailService.php) をウィザードの各ステップに安全にバインド。

#### Step 22: 会計締めロックおよびロールバック（管理者権限解除）機能
- 一般職員による過去月改変を完全にブロック。
- 施設管理者・法人管理者のみが実行できる「緊急差戻し（Reopen Closing）」アクションと操作ログ（ActivityLog）記録。

#### Step 23: 網羅的リグレッションテストスイートの実行
- 既存の `tests/Feature/Regression/` の全テスト（`InvoiceCalculationBasisTest`, `LockingRegressionTest`, `ProrationRegressionTest` など）を実行。
- 新規ウィザード経由で締めを行った場合でも、既存の単独画面操作と同じ計算結果・CSV出力が得られることを担保。

#### Step 24: 本番導入・ガイドツアー & ヘルプ連携
- 初回利用時のツールチップ案内、ダッシュボードへの「当月締め進捗バッジ」の表示。
- `CHATBOT_FAQ` に「月次締めの手順」「口座振替エラーの対応方法」を追加。

---

## 4. リグレッション防止のためのテスト計画マトリクス

| テスト分類 | テスト対象ファイル | 検証項目・保護対象 |
| :--- | :--- | :--- |
| **単体テスト (Unit)** | `tests/Unit/Services/Banking/ZenginFormatServiceTest.php` | 全銀協120バイト固定長、文字コード（CP932）、パディング規約 |
| | `tests/Unit/Services/BillingAnomalyDetectorTest.php` | 0円請求、退去日不整合、前月比異常値の検知精度 |
| **機能テスト (Feature)** | `tests/Feature/Banking/ZenginReconcileTest.php` | 引落結果取込時のステータス更新・不能コードマッピング |
| | `tests/Feature/DailyChargePresetTest.php` | プリセット展開によるDB登録と数量・金額の正確性 |
| | `tests/Feature/MonthlyClosingWizardTest.php` | ウィザードのステップ進行、エラー時の中断、クローズ後のロック |
| **既存リグレッション保護** | `tests/Feature/Regression/InvoiceCalculationBasisTest.php` | 家賃・管理費・自費の合算金額、日割り計算の不変性 |
| | `tests/Feature/Regression/LockingRegressionTest.php` | 確定済レコードの改ざん防止・Observerの排他制御 |
| | `tests/Feature/InvoiceCsvExportServiceTest.php` | freee / MF / 弥生 / 勘定奉行用仕訳CSV出力形式の完全一致 |
| | `tests/Feature/InvoicePdfFeaturesTest.php` | 請求書・領収書PDFレイアウト、印影、税率計算の不変性 |

---

## 5. 導入スケジュール & マイルストーン

```
Week 1: [Phase 1] Steps 1〜8  (全銀口座振替 & 自動消込) + 単体・Featureテスト
Week 2: [Phase 2] Steps 9〜16 (自費プリセット一括入力) + UI改修・リグレッション検証
Week 3: [Phase 3] Steps 17〜22(月次締めウィザード & 異常検知) + パイプライン連携
Week 4: [Phase 3] Steps 23〜24(統合リグレッションテスト完走、本番リリース・現場展開)
```
