# 残タスク実装計画書：メール送信・本契約移行・営業プロセス自動化

作成日: 2026-10-07
前提: セルフサーブトライアル（ステップ1-7）完了済み

## 背景と制約

### 現状
- メール送信は全て `Log::info` プレースホルダー（`TrialProvisioningService::sendProvisioningCompleteEmail`、`SendTrialExpiryNotification`）
- トライアル→本契約の移行フローが未実装（status に `converted` はあるが遷移しない）
- 提案2「営業プロセス自動化」（リードスコアリング、メールシーケンス、カレンダー予約、見積書自動生成、電子契約）が未実装

### 環境制約
- `MAIL_MAILER` 未設定（`.env` に mail 設定なし）→ 設定未検知時にログへフォールバックする `MailService` を介在させる
- `QUEUE_CONNECTION=sync`（ジョブは同期実行、テスト可能）
- Stripe パッケージ未導入 → 決済は DB レコード（`subscriptions` テーブル）で管理し、決済連携は拡張ポイントとして残す
- 外部カレンダー API 未連携 → 予約は DB 管理＋確認メール自動送

## Phase A: メール送信基盤

### A-1. Mailable クラス作成
| クラス | 用途 | 送信タイミング |
|---|---|---|
| `TrialProvisioningCompleteMail` | トライアル開始通知（ログインURL＋一時パスワード） | プロビジョニング完了時 |
| `TrialExpiryWarningMail` | 期限切れ間近通知（残り日数） | 7/3/1日前 |
| `TrialExpiredMail` | 期限切れ通知＋延長/本契約案内 | 期限切れ時 |
| `QuoteMail` | 見積書（PDF添付） | 見積依頼時 |
| `BookingConfirmationMail` | デモ予約確認 | 予約受付時 |
| `ContractCompletedMail` | 本契約完了通知 | 移行完了時 |

全 Mailable に `->onQueue('emails')` を付与しキュー送信にする。

### A-2. `app/Services/MailService.php`
- `config('mail.mailer')` と接続設定を検査
- 未設定（smtp かつ host 未設定等）の場合は `Log::info` にフォールバックし例外を握りつぶさない
- `send(Mailable $mailable, string $email): bool` インターフェース

### A-3. 既存コードの配線
- `TrialProvisioningService::sendProvisioningCompleteEmail` → `TrialProvisioningCompleteMail` 送信後、`trial_config.temp_password` をクリア
- `SendTrialExpiryNotification` → `TrialExpiryWarningMail` / `TrialExpiredMail`

## Phase B: トライアル→本契約移行

### B-1. マイグレーション
- `subscriptions` テーブル: `id, trial_id, facility_id, plan (starter/standard/enterprise), status (trialing/active/cancelled), monthly_price, started_at, ends_at, contract_accepted_at, contract_accepted_ip, contract_accepted_user_agent, timestamps, softDeletes`

### B-2. `app/Services/TrialConversionService.php`
- `convert(Trial $trial, array $data): array`
  1. trial status を `converted` に更新
  2. 施設を本契約施設に昇格（名称の「トライアル」プレフィックス除去、notes 更新、invoice_registration_number を本番番号に更新）
  3. `subscriptions` レコード作成（plan・価格・契約受諾情報）
  4. `ContractCompletedMail` 送信

### B-3. `app/Http/Controllers/TrialConversionController.php`
- `POST /api/trials/{trial}/convert`
- バリデーション: `plan` (Rule::in), `invoice_registration_number` (T+13桁), `bank` 必須項目, `contract_accepted` (true 必須＝電子契約の同意記録)
- 電子契約の同意は `contract_accepted_at/IP/UA` を記録（電子契約連携の拡張ポイント）

## Phase C: 営業プロセス自動化

### C-1. リードスコアリング（`app/Services/LeadScoringService.php`）
- `trials.score` カラム追加（マイグレーション）
- スコアリング基準:
  - 施設種別: 特養/老健/介護医療院=30, 有料老人=25, グループホーム=20, 訪問通所=15, その他=10
  - 入居定員: over_200=30, 100_200=25, 50_100=20, 30_50=15, under_30=10, unknown=5
  - 課題（challenges）: 各5点（上限25）
  - 予算感: over_50k=20, 30k_50k=15, 10k_30k=10, under_10k=5, undecided=5
  - サンプルデータ投入希望: +10
- 判定: >=80 hot（即営業連絡）, 50-79 warm（シーケンス優先）, <50 cold（ナーチャリング）
- トライアル作成時に自動計算・保存

### C-2. メールシーケンス（ナーチャリング）
- `app/Jobs/SendTrialNurtureEmails.php`（毎日実行、スケジューラ登録）
- シーケンス: 3日目=利用チェックイン, 7日目=導入事例紹介, 10日目=本契約移行案内
- `trial_config.emails_sent` で既送信管理（重複防止）
- `app/Mail/TrialNurtureMail.php`（ステージ別コンテンツ）

### C-3. カレンダー予約（デモ予約）
- `bookings` テーブル: `id, trial_id, name, email, preferred_date, preferred_time, notes, status (pending/confirmed), confirmed_at, timestamps`
- `POST /api/bookings`（`BookingController`）: 予約受付→`BookingConfirmationMail` 送信
- 営業担当の確認は今後の拡張ポイント（カレンダーAPI連携）

### C-4. 見積書自動生成（`app/Services/QuoteService.php`）
- プラン推奨ロジック（pricing.html の料金体系に基づく）:
  - under_30/30_50 → スターター ¥15,000/月
  - 50_100/100_200 → スタンダード ¥35,000/月
  - over_200/unknown・複数施設 → エンタープライズ（要見積）
- `GET /api/trials/{trial}/quote` で見積金額・プランをJSON返却
- `POST /api/trials/{trial}/quote/send` で `QuoteMail`（見積書PDF添付）送信
- PDF は既存 `InvoicePdfService` のパターンを流用（barryvdh/dompdf）

## 実装ステップ（検証付き）

1. Phase A: Mailable 6クラス + MailService + 配線（既存テスト回帰確認）
2. Phase B: subscriptions マイグレーション + TrialConversionService + Controller + ルート
3. Phase C-1: score カラム + LeadScoringService + TrialController への統合
4. Phase C-2: nurture ジョブ + スケジューラ登録
5. Phase C-3: bookings テーブル + BookingController + ルート
6. Phase C-4: QuoteService + ルート + QuoteMail
7. 全体テスト: `tests/Feature/` に新規テスト追加、全スイート回帰確認

## 完了条件
- トライアル申込→完了メール送信（未設定環境ではログ）→期限通知→本契約移行まで自動で回る
- リードスコアリングがトライアル作成時に自動算出される
- デモ予約・見積書発行が API で完結する
- 既存テスト 124 passed を維持（PDF事前失敗16件は除く）
