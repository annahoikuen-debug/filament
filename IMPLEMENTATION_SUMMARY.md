# セルフサーブトライアル 自動化 - 実装完了サマリー

## 実装ステップ完了状況

### ステップ1: トライアルデータ基盤の構築 ✅
- `database/migrations/2026_10_08_000001_create_trials_table.php` - trialsテーブル基本スキーマ
- `app/Models/Trial.php` - Trial Eloquentモデル
- `database/migrations/2026_10_08_000002_add_indexes_to_trials_table.php` - インデックス追加

### ステップ2: 基本的なトライアル作成API ✅
- `app/Http/Controllers/TrialController.php` - トライアル作成コントローラー
- `app/Http/Controllers/Controller.php` - 基本コントローラークラス
- `routes/api.php` - APIルート定義
- `bootstrap/app.php` - APIルートを読み込むように修正

### ステップ3: 施設とユーザーの自動作成 ✅
- `app/Services/TrialProvisioningService.php` - トライアルプロビジョニングサービス
- `app/Http/Controllers/TrialController.php` - サービスを使用するように更新
- `app/Models/Trial.php` - `$fillable` に `facility_id` を追加、施設リレーションを追加
- `database/migrations/2026_10_08_000003_add_facility_id_to_trials_table.php` - facility_id カラム追加マイグレーション

### ステップ4: サンプルデータ投入オプション ✅
- `database/migrations/2026_10_08_000004_add_config_to_trials_table.php` - trial_config カラム追加マイグレーション
- `app/Http/Controllers/TrialController.php` - seed_sample_data フィールドのバリデーションと trial_config への保存を追加
- `app/Services/TrialProvisioningService.php` - seedSampleData メソッドを実装し、設定に従ってサンプルデータ投入

### ステップ5: フロントエンド連携 ✅
- `website/request/inquiry.html` - フロントエンドフォームとJavaScriptロジックを更新
  - タイムラインが「immediate」の場合を検知しトライアル申請フローに分岐
  - /api/trialsエンドポイントにデータを送信
  - サンプルデータ投入オプションのチェックボックスを追加
  - ローディング状態と適切な成功/エラーメッセージを表示

### ステップ6: トライアルダッシュボード表示 ✅
- `app/Filament/Widgets/TrialOverview.php` - TrialOverviewウィジェットを作成
- `app/Providers/Filament/AdminPanelProvider.php` - ウィジェットをダッシュボードに追加

### ステップ7: トライアル期限管理 ✅
- `app/Jobs/SendTrialExpiryNotification.php` - 期限通知ジョブを作成
- `app/Jobs/CheckAndNotifyTrialExpiries.php` - スケーラージョブを作成
- `app/Console/Kernel.php` - コンソールカーネルを作成しスケーラーを登録

## 動作フロー

1. **トライアル申請**
   - ユーザーがウェブフォームで「すぐにでも導入したい」を選択して送信
   - フロントエンドJavaScriptが/api/trialsエンドポイントにデータを送信
   - TrialControllerがバリデーションを行いTrialレコードを作成
   - TrialProvisioningServiceが施設と管理者ユーザーを自動作成
   - オプションでサンプルデータを投入
   - トライアルステータスを'active'に更新し、14日間のトライアル期間を設定

2. **トライアル期間中**
   - ユーザーはFilamentダッシュボードにログイン可能
   - TrialOverviewウィジェットがトライアル残り日数、入居者数、今月の請求書数を表示
   - 毎日午前9時にスケーラージョブが実行され、期限が近いトライアルをチェック

3. **トライアル期限通知**
   - 7日前、3日前、1日前、当日にログを通じて通知（実際の実装ではメール送信）
   - 期限切れになったトライアルは自動でステータスが'expired'に更新

4. **トライアル→本契約移行**
   - （別実装が必要だが、基盤は整っている）
   - トライアル期間中または終了前に本契約に移行可能
   - 移行時にトライアル施設を本契約施設にアップグレード
   - トライアルステータスを'converted'に更新

## 管理者が「直接対応」する残る業務

1. **エンタープライズ案件のカスタム要件ヒアリング**
2. **クレーム/トラブル対応**（システム障害・データ不整合）
3. **大型導入のプロジェクトマネジメント**（複数施設一斉導入など）
4. **戦略的判断**（価格改定・新機能優先度・パートナーシップ）

## 期待される効果

1. **トライアル申請から環境利用までの時間**: 目標 <5分（現状 2営業日）
2. **トライアル登録完了率**: 目標 >70%（フォーム離脱率改善）
3. **トライアル→本契約転換率**: 目標 >25%（現状の営業プロセス比較）
4. **営業工数削減**: トライアル対応工数 80%削減
5. **顧客満足度（NPS）**: トライアル体験での向上目標 +15ポイント

この実装により、管理者はトライアル環境の自動プロビジョニング・期限管理によって、
本来の価値提供（機能改善・カスタム対応）に集中できるようになります。

## 評価・微調整（2026-10-07）

実装後のコードレビューで発見した問題と修正内容：

### 修正したバグ
1. **`Trial::isExpired()` の未定義変数** - `$trial->trial_ends_at` が `$this->trial_ends_at` に修正（常に false を返していた）
2. **`trial_config` 未永続化** - コントローラーが `seed_sample_data` を無視していたため、サンプルデータ投入オプションが機能しなかった。`trial_config` に保存するように修正（モデルに `$fillable`・array cast 追加）
3. **DBレベル一意制約とソフトデリートの衝突** - `trials.email` の UNIQUE制約がソフトデリート済みレコードで再申込を拒否していた。アプリケーション層バリデーション（`Rule::unique()->whereNull('deleted_at')`）に変更し、DB制約は通常インデックスに降格（`2026_10_08_000005_fix_trials_email_unique.php`）
4. **`invoice_registration_number` の衝突** - 全トライアル施設が同一の仮番号 `T0000000000000` で2件目以降のプロビジョニングが失敗。トライアルIDベースの一意な仮番号に変更
5. **`daysUntilExpiry()` の負値/精度問題** - Carbon 3 の `diffInDays()` は符号付きfloatを返すため、期限切れで正の値を返していた。日付単位の差分（`startOfDay`）に変更し、期限切れは0にクランプ
6. **管理者パスワードの未保存** - ランダムパスワードを生成するのみで保存・送信していなかった。`trial_config.temp_password` に退避（メール送信実装時に送信後クリア予定）
7. **既存ユーザーのメールアドレスでクラッシュ** - `User::create` が一意制約違反で失敗。`User::firstOrCreate` に変更し既存ユーザーは流用（パスワードは変更しない）
8. **同一企業の複数トライアルで施設が混在** - 施設名にトライアルIDを含め一意化

### 改善
- `CheckAndNotifyTrialExpiries` - 通知対象日（7/3/1/0日前）のみジョブをdispatch（毎日の無駄なdispatchを削減）
- フロントエンド - トライアル申込モード（`trial_mode` チェックボックス）を追加し、`/api/trials` へ fetch 送信。バリデーションエラーを日本語で表示。`capacity` 選択肢をAPIのバリデーション値に整合（`100_200`/`over_200`/`unknown`）

### 回帰防止テスト
- `tests/Feature/TrialApiTest.php` - 9ケース（プロビジョニング正常系/異常系、二重申込、ソフトデリート後再申込、既存ユーザー、施設分離、期限クランプ、期限切れ自動更新）
- 既存テストスイート 124 passed（PDF関連16件の失敗は事前存在・本変更と無関係）