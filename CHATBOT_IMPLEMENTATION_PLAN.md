# チャットボット導入実装計画書（職員向け内部ツール）

**作成日**: 2026-10-09
**対象**: 職員向けFAQ自動応答＋データベース連携チャットボット
**期間**: 2026-10-09 〜 2026-10-23（約2週間）
**テスト方針**: 全実装にリグレッションテスト作成、既存386テストの継続通過を必須条件とする

---

## 実装スコープ一覧

| # | 項目 | 優先度 | 工数 | 対応目的 |
|---|------|--------|------|----------|
| 1 | チャットボット基盤（API＋FilamentチャットUI） | High | 3日 | 職員向け対話インターフェース |
| 2 | FAQ管理機能（Filament Resource） | High | 2日 | FAQ自動応答 |
| 3 | インテント認識・DB連携（入居者・請求照会） | High | 4日 | データベース連携 |
| 4 | 会話ログ・セキュリティ（施設スコープ・PII保護） | Critical | 2日 | 個人情報保護・施設間データ分離 |

---

## 全体アーキテクチャ

```
Filament管理画面 (/admin/chatbot)
  └─ チャットUI（Alpine.js ウィジェット）
       └─ POST /api/chatbot/message（web+auth ミドルウェア）
            └─ ChatbotService（オーケストレータ）
                 ├─ IntentRecognizer（キーワード/パターン照合）
                 ├─ FaqResponder（FAQ検索）
                 └─ ResidentQueryService（入居者・請求照会／施設スコープ強制適用）
```

**設計方針**
- v1はLLM依存なし（キーワード/パターンマッチング）→ 決定論的・テスト可能・外部APIコストなし
- 既存モデル（Resident / MonthlyInvoice / DailyCharge / ChargeItem）を直接参照、データ複製なし
- 施設スコープは `ResidentResource::getEloquentQuery()` の既存パターンを踏襲し、サービス層で強制適用
- 認証は既存の `web` + `auth` セッション認証を流用（Filamentのログインセッションを共用）

---

## Item 1: チャットボット基盤（API＋FilamentチャットUI）

### 目的
職員がFilament管理画面内で利用できるチャットインターフェースと、メッセージ受付APIを提供する。

### 実装内容
1. **APIエンドポイント**: `routes/web.php` に `POST /api/chatbot/message` 追加
   - ミドルウェア: `['web', 'auth']`（既存のPDFダウンロードルートと同様）
   - レート制限: `throttle:30,1`
   - リクエスト: `{ message: string, session_id?: string }`
   - レスポンス: `{ reply: string, intent: string, data?: array, sources?: array }`
2. **コントローラ**: `app/Http/Controllers/ChatbotController.php` 新設
   - `message(Request $request): JsonResponse`
   - 認可: `Auth::user()->is_admin === true`（`canAccessPanel` と同等条件）
3. **チャットUI**: `app/Filament/Pages/ChatbotAssistant.php` 新設（Filament Page）
   - Alpine.jsベースのチャットウィジェット（`resources/views/filament/pages/chatbot-assistant.blade.php`）
   - 会話履歴はブラウザ localStorage でセッション管理
   - AdminPanelProvider の `pages()` に登録
4. **DTO**: `app/Services/Chatbot/DTO/ChatRequest.php` / `ChatResponse.php` 新設
5. **設定**: `config/chatbot.php` 新設（rate_limit, retention_days, mask_names, intents定義）

### テスト計画（リグレッション防止）
- `tests/Feature/Chatbot/ChatbotApiTest.php` 新設:
  1. 未認証アクセスは401（リダイレクト）
  2. is_admin=false のユーザーは403
  3. 正規のメッセージでJSON応答が返る（reply, intent を含む）
  4. 空メッセージはバリデーションエラー（422）
  5. レート制限が動作する（31回目で429）

---

## Item 2: FAQ管理機能

### 目的
FAQの登録・編集・無効化をFilament上で行い、キーワード検索による自動応答を実現する。

### 実装内容
1. **マイグレーション**: `chatbot_faqs` テーブル新設
   - `question` (string), `keywords` (json), `answer` (text), `category` (string), `is_active` (bool, default true), `sort_order` (int), `facility_id` (nullable, nullOnDelete)
   - `facility_id=null` は全施設共通FAQ、値ありは施設固有FAQ
2. **モデル**: `app/Models/ChatbotFaq.php` 新設（`keywords` を array cast、`LogsActivity` 設定）
3. **Filament**: `app/Filament/Resources/ChatbotFaqResource.php` 新設
   - リスト: question, category, is_active（バッジ）, updated_at
   - フォーム: question, keywords（TagsInput）, answer（Textarea）, category（Select）, is_active（Toggle）
   - 権限: corporate_admin は全件管理、facility_admin は自施設FAQのみ（`getEloquentQuery` でスコープ）
4. **サービスクラス**: `app/Services/Chatbot/FaqResponder.php` 新設
   - `search(string $query, ?int $facilityId): Collection`
   - キーワード一致スコアリング（一致キーワード数順、タイブレークは sort_order）
   - `is_active=true` のみ対象

### テスト計画
- `tests/Unit/Chatbot/FaqResponderTest.php` 新設:
  1. キーワード一致でFAQが検索される（スコア順）
  2. `is_active=false` のFAQは検索対象外
  3. 施設固有FAQは他施設から検索されない
  4. 共通FAQ（facility_id=null）は全施設から検索される
  5. 一致なしは空コレクション
- `tests/Feature/Chatbot/FilamentChatbotFaqResourceTest.php` 新設:
  1. corporate_admin でFAQ CRUD可能
  2. facility_admin は自施設FAQのみ管理可能（他施設件は一覧に出ない）
  3. keywords の変更が監査ログに記録される

---

## Item 3: インテント認識・DB連携

### 目的
入居者検索・請求金額照会・支払状況確認など、業務データへの自然文問い合わせに対応する。

### 実装内容
1. **インテント認識**: `app/Services/Chatbot/IntentRecognizer.php` 新設
   - パターン定義（config/chatbot.php の `intents`）:
     - `resident_lookup`: 「{名前}の情報」「{部屋番号}号室」
     - `invoice_amount`: 「{名前}の今月の請求額」
     - `invoice_status`: 「{名前}の支払い状況」
     - `daily_charge_total`: 「{名前}の月額利用料」
     - `faq`: 上記パターンに合致しない質問文
   - 戻り値: `IntentDTO(intent, entities: [name, room_number, year_month])`
   - 名前抽出は入居者マスタの name/name_kana からの部分一致（2文字以上）
2. **照会サービス**: `app/Services/Chatbot/ResidentQueryService.php` 新設
   - `findResident(string $keyword, User $user): ?Resident`（**施設スコープ強制適用**）
   - `getLatestInvoice(Resident $resident): ?MonthlyInvoice`
   - `getPaymentStatus(Resident $resident): array`（status, due_date, unpaid_amount）
   - `getMonthlyDailyChargeTotal(Resident $resident, string $yearMonth): int`
3. **オーケストレータ**: `app/Services/Chatbot/ChatbotService.php` 新設
   - `handle(ChatRequest $request, User $user): ChatResponse`
   - インテント → ハンドラ分岐 → 応答テンプレ描画
   - 応答テンプレート: `resources/views/chatbot/responses/*.blade.php`
   - 金額表示は `number_format` + 円、税込表記
4. **計算根拠連携**: 日割り計算の問い合わせ時は按分根拠（日数・式）を応答に含める（Item 2 計算根拠セクションのデータを流用）

### テスト計画
- `tests/Unit/Chatbot/IntentRecognizerTest.php` 新設:
  1. 入居者名を含む質問が `resident_lookup` に分類される
  2. 「請求額」「いくら」等の質問が `invoice_amount` に分類される
  3. 「支払い」「未払い」等の質問が `invoice_status` に分類される
  4. 曖昧な質問は `faq` に分類される
  5. 部屋番号（数字＋号室）から entity を抽出できる
- `tests/Feature/Chatbot/ResidentQueryTest.php` 新設:
  1. 名前・部屋番号で入居者を検索できる
  2. 最新請求書の金額が正しく返る（税込表示）
  3. 支払状況（未払/済）が正しく返る
  4. 月中途入居者の日割り根拠が応答に含まれる
  5. 存在しない名前は「見つかりません」応答

---

## Item 4: 会話ログ・セキュリティ（Critical）

### 目的
会話の監査可能性を確保しつつ、個人情報保護と施設間データ分離を徹底する。

### 実装内容
1. **会話ログ**: `chat_logs` テーブル新設
   - `user_id` (foreignId, nullOnDelete), `session_id` (string), `user_message` (text), `intent` (string), `bot_reply` (text), `facility_id` (nullable), `created_at`
   - リテンション: `config/chatbot.php` の `retention_days`（default 90）で定期削除（`routes/console.php` に毎日登録）
2. **施設スコープ（Critical）**:
   - facility_admin の照会は必ず `facility_id` で絞り込む（`ResidentResource::getEloquentQuery()` パターン踏襲）
   - corporate_admin は全施設対象
   - スコープ漏れ防止: `ResidentQueryService` 内で強制適用し、コントローラ側での生クエリを禁止
3. **PII保護**:
   - ログ保存時に氏名をマスキングする設定（`mask_names`, default true）
   - 応答に含める情報は最小限（氏名・部屋番号・金額・ステータスのみ）
   - 請求書PDFのダウンロードリンクはチャット応答に含めない（既存の認証済みルート経由で手動取得）
4. **レート制限**: `throttle:30,1` ＋ ユーザー単位の利用回数を activity_log に記録
5. **権限**: `canAccessPanel()` 既存ロジックを流用（`is_admin` のみ利用可能）

### テスト計画
- `tests/Feature/Chatbot/FacilityScopeTest.php` 新設（Critical）:
  1. facility_admin が他施設の入居者名を質問しても情報が返らない（「見つかりません」）
  2. facility_admin のログに `facility_id` が正しく記録される
  3. corporate_admin は全施設を検索できる
  4. 未認証ユーザーはAPIにアクセスできない
  5. 他施設の入居者名を直接指定してもデータが漏洩しない（境界値）
- `tests/Feature/Chatbot/ChatLogRetentionTest.php` 新設:
  1. 会話がログに記録される（intent 含む）
  2. 保持期間超過ログが削除される
  3. `mask_names=true` で氏名がマスキングされる
  4. `mask_names=false` で氏名が保持される

---

## リグレッション防止の全体方針

1. **実装前**: 対象機能の既存テストを全実行し、ベースラインを記録（`php artisan test`）
2. **実装中**: 各Item完了時に既存386テスト＋新規テストを全実行
3. **実装後**: `tests/Feature/Regression/ChatbotRegressionTest.php` 新設:
   - チャットボット導入後も既存の請求生成・PDF出力・CSV出力が正常動作すること
   - Filament既存リソース（入居者・請求書・品目）のCRUDが正常動作すること
   - チャットボット用マイグレーションが既存テーブルに影響を与えないこと
4. **テストカバレッジ**: 新規コードは正常系・異常系・境界値（施設スコープON/OFF、nullフォールバック、空結果、レート制限）をカバー
5. **DB分離**: チャットボット用テーブルは既存テーブルに外部キー制約を追加しない（`users` への参照は `nullOnDelete`）
6. **マイグレーション安全性**: `down()` を全マイグレーションに実装し、ロールバック可能を検証

---

## 実装順序とタイムライン

```
Week 1 (10/09-10/15)
├─ Item 1: 基盤（API＋UI） 3日
│   ├─ Day 1: ルート・コントローラ・DTO・config
│   ├─ Day 2: ChatbotService骨格・Filament Page（Alpine.js UI）
│   └─ Day 3: ChatbotApiTest 作成・既存テスト全実行
├─ Item 2: FAQ管理 2日
│   ├─ Day 4: マイグレーション・モデル・Filament Resource
│   └─ Day 5: FaqResponder・テスト作成

Week 2 (10/16-10/23)
├─ Item 3: インテント・DB連携 4日
│   ├─ Day 6-7: IntentRecognizer・ResidentQueryService
│   ├─ Day 8: オーケストレータ統合・応答テンプレ
│   └─ Day 9: テスト作成
├─ Item 4: ログ・セキュリティ 2日
│   ├─ Day 10: chat_logs・スコープ強制・マスキング・スケジューラ
│   └─ Day 11: テスト・ChatbotRegressionTest・全テスト最終実行
```

---

## 完了条件（Definition of Done）

- [x] 各Itemの実装完了
- [x] 各Itemのリグレッションテスト作成・全合格（48テスト新規作成）
- [x] 既存386テストの継続通過（計441テスト全て合格）
- [x] 施設間データ分離の検証完了（他施設情報漏洩テスト合格）
- [x] README.md へのチャットボット利用手順追記
- [x] config/chatbot.php のデフォルト値レビュー（セキュリティ設定の確認）

---

## 実装完了記録（2026-10-09）

**成果物**:
- 設定: `config/chatbot.php`（レート制限・ログ保持・マスキング・インテント定義）
- DTO: `ChatRequest`, `ChatResponse`, `IntentDTO`
- サービス: `ChatbotService`（オーケストレータ）, `IntentRecognizer`, `FaqResponder`, `ResidentQueryService`
- モデル: `ChatbotFaq`, `ChatLog`
- コントローラ: `ChatbotController`（`POST /api/chatbot/message`）
- Filament: `ChatbotAssistant` ページ, `ChatbotFaqResource`（FAQ管理）
- マイグレーション: `chatbot_faqs`, `chat_logs` テーブル
- スケジューラ: チャットログ保持期間クリーンアップ（毎日04:00）

**テスト（48テスト新規）**:
- `tests/Unit/Chatbot/IntentRecognizerTest.php`（7テスト）
- `tests/Unit/Chatbot/FaqResponderTest.php`（6テスト）
- `tests/Feature/Chatbot/ChatbotApiTest.php`（6テスト）
- `tests/Feature/Chatbot/ResidentQueryTest.php`（9テスト）
- `tests/Feature/Chatbot/FacilityScopeTest.php`（8テスト）
- `tests/Feature/Chatbot/ChatLogRetentionTest.php`（4テスト）
- `tests/Feature/Chatbot/FilamentChatbotFaqResourceTest.php`（5テスト）
- `tests/Feature/Regression/ChatbotRegressionTest.php`（5テスト）

**最終結果**: 441 tests passed (1902 assertions), 0 failed
