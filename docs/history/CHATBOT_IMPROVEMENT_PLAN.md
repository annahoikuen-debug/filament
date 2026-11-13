# チャットボット改善実装計画書（サーバー低負荷版）

**作成日**: 2026-10-10
**対象**: チャットボット使い勝手改善（サーバー追加負荷の小さい改善策1〜5）
**期間**: 2026-10-10 〜 2026-10-31（約3週間）
**テスト方針**: 全実装にリグレッションテスト作成、既存441テストの継続通過を必須条件とする
**設計方針**: 外部API呼び出しなし・DB集計はオフピーク・既存テーブルのインデックスを活用

---

## 改善スコープ一覧（サーバー負担の小さい順）

| Phase | 改善項目 | 優先度 | 工数 | サーバー負荷 |
|-------|----------|--------|------|--------------|
| 1 | 期間指定対応（先月・任意月の請求照会） | High | 3日 | 極小（regex解析のみ） |
| 2 | クイックリプライ（よくある質問ボタン） | Medium | 1.5日 | 極小（静的設定） |
| 3 | 曖昧検索・カナ一致（誤字に強い入居者検索） | High | 3日 | 小（PHP内計算） |
| 4 | 人間へのエスカレーション（担当者引き継ぎ） | Medium | 2.5日 | 小（失敗時のみ書込） |
| 5 | 未回答質問の分析（FAQ改善支援） | Low | 2日 | 小（日次バッチ） |

---

## Phase 1: 期間指定対応（ステップ 1〜8）

### 目的
「先月の請求額」「2026年8月の請求」「8月の利用料」など、期間を指定した照会に対応する。現状は最新月固定で、過去月の照会ができない。

### 実装内容
1. **設定追加**: `config/chatbot.php` に `periods` 定義を追加
   - 相対表現: 先月, 先々月, 今月, 昨年, 今年
   - 絶対表現: YYYY年M月, M月, YYYY/M
2. **MonthParser**: `app/Services/Chatbot/MonthParser.php` 新設
   - `parse(string $message, string $currentYearMonth): ?string`（YYYY-mm を返す）
   - 相対表現は現在年月から算出（年末年跨ぎを考慮）
   - 無効な月（13月等）は null を返す
3. **インテント認識拡張**: `IntentRecognizer` に year_month エンティティ抽出を追加
4. **照会サービス拡張**: `ResidentQueryService` の各メソッドに `?string $yearMonth` 引数を追加
   - `getLatestInvoice(Resident, ?string $yearMonth)`
   - `getPaymentStatus(Resident, ?string $yearMonth)`
   - `getMonthlyDailyChargeTotal(Resident, string $yearMonth)`（既存）
5. **ハンドラ修正**: `ChatbotService` の invoice_amount / invoice_status / daily_charge_total ハンドラで year_month を使用
6. **応答文言**: 期間を明記（"2026年8月の請求額: 75,000円"）
7. **将来月ガード**: 未来の月を指定された場合はエラー応答

### テスト計画（リグレッション防止）
- `tests/Unit/Chatbot/MonthParserTest.php` 新設:
  1. 「先月」が現在年月-1を返す
  2. 「先々月」が現在年月-2を返す
  3. 「2026年8月」が '2026-08' を返す
  4. 「8月」が当年の8月を返す
  5. 1月の「先月」が前年12月を返す（年末年跨ぎ）
  6. 無効な月（13月）は null を返す
  7. 期間指定なしは null を返す
- `tests/Feature/Chatbot/PeriodQueryTest.php` 新設:
  1. 先月の請求額が正しく返る
  2. 任意月（2026年8月）の請求額が正しく返る
  3. 未来月はエラー応答
  4. 期間指定なしは最新月（既存動作維持）
  5. 既存の最新月照会テストへの影響なし

---

## Phase 2: クイックリプライ（ステップ 9〜14）

### 目的
よくある質問をボタンで選択可能にし、入力の手間とタイポを減らす。

### 実装内容
1. **設定追加**: `config/chatbot.php` に `quick_replies` 定義（ラベル→質問文のリスト）
2. **DTO拡張**: `ChatResponse` に `quickReplies` フィールド（`array<int, string>`）を追加
3. **サービス修正**: `ChatbotService.handle` で応答に quick_replies を付与
   - 初回メッセージ・FAQ不一致時に表示
4. **UI実装**: `chatbot-assistant.blade.php` にボタン描画（Alpine.js）
5. **JS修正**: ボタンクリックで `send()` を呼び出す
6. **アクセシビリティ**: ボタンに aria-label を付与

### テスト計画
- `tests/Feature/Chatbot/QuickReplyTest.php` 新設:
  1. 応答に quick_replies が含まれる
  2. FAQ不一致時に quick_replies が表示される
  3. quick_replies のラベルが設定値と一致する
  4. ボタンクリックでメッセージ送信が発生する（JS構造検証）
  5. 既存応答形式（reply/intent/data/sources）への影響なし

---

## Phase 3: 曖昧検索・カナ一致（ステップ 15〜23）

### 目的
「山田太朗」（誤字）や「やまだたろう」（カナ）でも入居者を見つけられるようにする。現状は完全一致のみ。

### 実装内容
1. **FuzzyMatcher**: `app/Services/Chatbot/FuzzyMatcher.php` 新設
2. **レーベンシュタイン距離**: `similarity(string $a, string $b): float`（0〜1）を実装
3. **カナ正規化**: 全角/半角・長音・濁点の正規化（`normalizeKana(string): string`）
4. **しきい値**: `config/chatbot.php` に `fuzzy.threshold`（default 0.7）を追加
5. **検索拡張**: `ResidentQueryService.findResident` を FuzzyMatcher 経由に変更
   - 完全一致 → カナ一致 → 曖昧一致の優先順位
6. **スコアリング**: 複数候補をスコア順に並べる
7. **確認応答**: 候補が複数ある場合は上位3件を提示（"どちらですか？"）
8. **施設スコープ維持**: 曖昧検索でも facility_id 絞り込みを強制
9. **性能**: 施設内入居者は数十〜数百件なので全件スキャン可（インデックス不要）

### テスト計画
- `tests/Unit/Chatbot/FuzzyMatcherTest.php` 新設:
  1. 完全一致は類似度1.0
  2. 1文字誤字はしきい値以上
  3. 全く違う名前はしきい値未満
  4. カナ正規化（全角→半角・長音統一）
  5. カナ一致で氏名を検出
- `tests/Feature/Chatbot/ResidentQueryFuzzyTest.php` 新設:
  1. 誤字（山田太朗）で入居者が見つかる
  2. カナ（やまだたろう）で入居者が見つかる
  3. 複数候補時は確認応答になる
  4. 曖昧検索でも他施設の入居者は見つからない（スコープ維持）
  5. しきい値未満は「見つかりません」
  6. 既存の完全一致検索への影響なし

---

## Phase 4: 人間へのエスカレーション（ステップ 24〜30）

### 目的
チャットボットが回答できない場合、担当者に会話コンテキスト付きで引き継ぐ。

### 実装内容
1. **意図追加**: `config/chatbot.php` の intents に `escalate` を追加
   - パターン: 担当者, 人に連絡, 対応して, サポート, ヘルプ
2. **マイグレーション**: `chat_escalations` テーブル新設
   - user_id, session_id, summary, resident_id（nullable）, facility_id, status（pending/resolved）, created_at
3. **モデル**: `app/Models/ChatEscalation.php` 新設
4. **ハンドラ**: `ChatbotService` に `handleEscalate` を実装
   - 直近の会話ログ（session_id 単位）を要約して保存
   - 応答: "担当者に引き継ぎました。折り返しご連絡します。"
5. **通知**: 担当者に Filament Notification または activity_log で記録
6. **ステータス管理**: 担当者が対応済みにできる簡易API（任意）
7. **レート制限**: エスカレーションはユーザー毎に1日最大5件（悪用防止）

### テスト計画
- `tests/Feature/Chatbot/EscalationTest.php` 新設:
  1. 「担当者」でエスカレーション意図が判定される
  2. chat_escalations に会話コンテキストが保存される
  3. 引き継ぎ応答が返る
  4. facility_id が正しく記録される
  5. 1日5件を超えるエスカレーションは制限される
  6. 未認証ユーザーはエスカレーションできない

---

## Phase 5: 未回答質問の分析（ステップ 31〜36）

### 目的
FAQに一致しなかった質問を集計し、FAQ改善の優先順位を明らかにする。

### 実装内容
1. **マイグレーション**: `chat_logs` に `faq_matched` (bool, nullable) カラム追加
2. **記録**: `ChatbotService.handleFaq` で faq_matched を記録
3. **集計コマンド**: `app/Console/Commands/AnalyzeChatFaqMisses.php` 新設
   - `php artisan chatbot:analyze-faq-misses {--days=30}`
   - 未回答質問の件数・頻度ランキングを出力
4. **スケジューラ**: `routes/console.php` に日次登録（05:00）
5. **表示**: Filament widget またはダッシュボードに未回答ランキング表示
6. **CSV出力**: ランキングをCSVでエクスポート（任意）

### テスト計画
- `tests/Feature/Chatbot/FaqMissAnalysisTest.php` 新設:
  1. FAQ不一致時に faq_matched=false が記録される
  2. FAQ一致時に faq_matched=true が記録される
  3. 集計コマンドがランキングを出力する
  4. --days オプションで期間を絞れる
  5. スケジューラに登録されている
  6. 既存チャットログ機能への影響なし

---

## 全36ステップ一覧

| # | ステップ | 成果物 | テスト |
|---|----------|--------|--------|
| 1 | config/chatbot.php に periods 定義追加 | 設定 | - |
| 2 | MonthParser.php 新設（基本解析） | サービス | - |
| 3 | MonthParser の境界値実装（年末年跨ぎ・無効月） | サービス | - |
| 4 | IntentRecognizer に year_month 抽出追加 | サービス | - |
| 5 | ResidentQueryService に yearMonth 引数追加 | サービス | - |
| 6 | ChatbotService ハンドラで yearMonth 使用 | サービス | - |
| 7 | 応答文言に期間明記・未来月ガード | サービス | - |
| 8 | MonthParserTest・PeriodQueryTest 作成 | テスト | ✅ |
| 9 | config/chatbot.php に quick_replies 定義追加 | 設定 | - |
| 10 | ChatResponse に quickReplies フィールド追加 | DTO | - |
| 11 | ChatbotService で quick_replies 付与 | サービス | - |
| 12 | chatbot-assistant.blade.php にボタン描画 | UI | - |
| 13 | ボタンクリックで send() 呼び出し | UI | - |
| 14 | QuickReplyTest 作成 | テスト | ✅ |
| 15 | FuzzyMatcher.php 新設 | サービス | - |
| 16 | レーベンシュタイン距離による類似度実装 | サービス | - |
| 17 | カナ正規化（全角/半角・長音・濁点） | サービス | - |
| 18 | config/chatbot.php に fuzzy.threshold 追加 | 設定 | - |
| 19 | ResidentQueryService.findResident を FuzzyMatcher 経由に変更 | サービス | - |
| 20 | 複数候補のスコアリング順位付け | サービス | - |
| 21 | 複数候補時の確認応答（上位3件提示） | サービス | - |
| 22 | FuzzyMatcherTest・ResidentQueryFuzzyTest 作成 | テスト | ✅ |
| 23 | 曖昧検索でも施設スコープ維持の検証 | テスト | ✅ |
| 24 | config/chatbot.php に escalate 意図パターン追加 | 設定 | - |
| 25 | IntentRecognizer に escalate 判定追加 | サービス | - |
| 26 | chat_escalations テーブル新設（マイグレーション） | DB | - |
| 27 | ChatEscalation.php モデル新設 | モデル | - |
| 28 | ChatbotService に handleEscalate 実装（コンテキスト保存） | サービス | - |
| 29 | 担当者通知（Filament Notification / activity_log） | サービス | - |
| 30 | EscalationTest 作成（意図・保存・通知・レート制限） | テスト | ✅ |
| 31 | chat_logs に faq_matched カラム追加（マイグレーション） | DB | - |
| 32 | ChatbotService.handleFaq で faq_matched 記録 | サービス | - |
| 33 | AnalyzeChatFaqMisses.php コマンド新設（集計） | コマンド | - |
| 34 | routes/console.php に日次スケジューラ登録 | スケジューラ | - |
| 35 | Filament widget で未回答ランキング表示 | UI | - |
| 36 | FaqMissAnalysisTest 作成（記録・集計・スケジューラ） | テスト | ✅ |

---

## リグレッション防止の全体方針

1. **実装前**: 既存441テスト全実行しベースライン記録
2. **実装中**: 各Phase完了時に既存テスト＋新規テスト全実行
3. **実装後**: `tests/Feature/Regression/ChatbotImprovementRegressionTest.php` 新設:
   - 改善後も既存の入居者照会・請求照会・FAQ応答が正常動作すること
   - 施設スコープ（他施設情報漏洩防止）が維持されていること
   - チャットログ・マスキングが維持されていること
4. **テストカバレッジ**: 新規コードは正常系・異常系・境界値（年末年跨ぎ・無効月・しきい値境界・レート制限）をカバー
5. **性能担保**: 曖昧検索は施設内全件スキャンだが、入居者数が少ないため許容（基準: 1リクエスト < 100ms）

---

## 実装順序とタイムライン

```
Week 1 (10/10-10/16)
├─ Phase 1: 期間指定対応（ステップ 1-8）
│   ├─ Day 1: 設定・MonthParser・境界値
│   ├─ Day 2: インテント・照会サービス・ハンドラ
│   └─ Day 3: テスト作成・リグレッション確認
├─ Phase 2: クイックリプライ（ステップ 9-14）
│   ├─ Day 4: 設定・DTO・サービス
│   └─ Day 5: UI実装・テスト

Week 2 (10/17-10/23)
├─ Phase 3: 曖昧検索・カナ一致（ステップ 15-23）
│   ├─ Day 6-7: FuzzyMatcher・カナ正規化・しきい値
│   ├─ Day 8: 検索拡張・スコアリング・確認応答
│   └─ Day 9: テスト作成
├─ Phase 4: エスカレーション（ステップ 24-30）
│   ├─ Day 10: 意図・マイグレーション・モデル
│   └─ Day 11: ハンドラ・通知・テスト

Week 3 (10/24-10/31)
├─ Phase 5: 未回答質問の分析（ステップ 31-36）
│   ├─ Day 12: マイグレーション・記録・コマンド
│   ├─ Day 13: スケジューラ・widget表示
│   └─ Day 14: テスト・ChatbotImprovementRegressionTest・全テスト最終実行
```

---

## 完了条件（Definition of Done）

- [x] 全36ステップの実装完了
- [x] 各Phaseのリグレッションテスト作成・全合格
- [x] 既存441テストの継続通過（全体600+テスト通過）
- [x] 施設スコープ維持の検証完了（他施設情報漏洩テスト合格）
- [x] 性能基準の確認（1リクエスト < 100ms）
- [x] README.md のチャットボット機能説明更新
