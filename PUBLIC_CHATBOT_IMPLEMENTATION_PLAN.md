# 商用サイト向けチャットボット実装計画書（公開サイト・リード獲得特化）

**作成日**: 2026-10-10
**対象**: 商業展開向けWebサイト（website/・静的31ページ）右下ポップアップ型チャットボット
**方針**: 内部版（実装済み）のアーキテクチャを最大限流用し、**「認証なし・公開情報のみ・リード獲得特化」**に絞る
**期間**: 約8-9営業日（12ステップ）
**テスト方針**: 全ステップでリグレッションテスト作成、既存テスト（現在443 passed / 5 failed）の継続通過を必須条件とする
**現状**: 未実装（計画段階）

---

## 実装スコープ一覧

| Step | 項目 | 工数 | 対応目的 |
|------|------|------|----------|
| 1 | 公開FAQフラグ基盤（`is_public`） | 0.5日 | 公開/内部FAQの分離 |
| 2 | 公開インテント定義・認識器 | 1日 | 料金・機能・資料請求等の意図分類 |
| 3 | 公開FAQ検索サービス | 0.5日 | 公開FAQのみ検索（施設スコープなし） |
| 4 | 会話ログ拡張（channel/visitor_id） | 0.5日 | 公開チャットの記録・分析 |
| 5 | 公開オーケストレータ | 1日 | 認証なし応答フロー（PII参照なし） |
| 6 | 公開API・セキュリティ | 1日 | レート制限・ハニーポット・CORS |
| 7 | リード獲得フロー | 1日 | チャット内から既存フォームAPIへ誘導 |
| 8 | ウィジェットJS配信基盤 | 0.5日 | 右下FAB+パネルの配信 |
| 9 | ウィジェットUI完成 | 1日 | チャットUX（クイックリプライ・ミニフォーム） |
| 10 | 静的サイト31ページ埋め込み | 0.5日 | 全ページへの設置 |
| 11 | ログ運用・分析 | 0.5日 | リテンション・未回答分析 |
| 12 | リグレッション・最終検証 | 0.5日 | 既存機能無影響の保証 |

**計: 約8.5日 / 新規テスト48件**

---

## 全体アーキテクチャ

```
商用サイト（website/*.html・静的31ページ）
  └─ 右下FAB → チャットパネル（Shadow DOM・Vanilla JS・iframeなし）
       ├─ visitor_id（UUID・localStorage・初回訪問時生成）
       └─ POST /api/public/chatbot/message（公開API・認証不要）
            └─ PublicChatbotController（throttle・ハニーポット・最短時間チェック）
                 └─ PublicChatbotService（オーケストレータ）
                      ├─ PublicIntentRecognizer（公開インテント: 料金/機能/デモ/資料/問合せ/時間）
                      ├─ PublicFaqResponder（ChatbotFaq.is_public=true のみ検索）
                      └─ LeadFlow（action links + ミニフォーム → 既存 POST /api/site-forms/{type}）
                 └─ ChatLog（channel='public' + visitor_id 記録）
```

**設計方針（内部版との差分）**

| 項目 | 内部版（実装済み） | 公開版（本計画） |
|------|------------------|-----------------|
| 認証 | `web+auth` + `is_admin` | **認証なし**（visitor_id + IPレート制限） |
| データ参照 | Resident/MonthlyInvoice/DailyCharge（PII） | **ChatbotFaq（is_public=true）のみ・PII参照なし** |
| 施設スコープ | `applyFacilityScope` 強制 | 不要（公開FAQのみ・`is_public`フラグで分離） |
| インテント | resident_lookup/invoice_amount/invoice_status/daily_charge_total/escalate | pricing/features/demo_request/catalog_request/contact/hours/lead/escalate |
| リード獲得 | なし（社内ツール） | **チャット内ミニフォーム → 既存 `POST /api/site-forms/{type}`** |
| セッション | Filamentセッション共有 | 独立（visitor_id + localStorage履歴） |
| ログ | chat_logs（user_id必須運用） | chat_logs 再利用（`channel='public'` + `visitor_id`） |

**最大限流用する既存資産**

| 既存資産 | 流用方法 |
|----------|----------|
| `FaqResponder`（キーワードスコアリング+Bi-gram Jaccard） | 検索ロジックはそのまま・スコープのみ公開限定に差し替えた薄いラッパー |
| `BigramTokenizer` / `FuzzyMatcher` / `SynonymExpander` | 無変更で再利用 |
| `ChatbotFaq` モデル | `is_public` カラム追加のみ（fillable/casts/logOnly追記） |
| `ChatLog` モデル | `channel`/`visitor_id` カラム追加のみ（リテンション既存処理を流用） |
| `ChatActionLinkGenerator` のパターン | 公開用アクションリンク（/request/catalog.html 等への誘導）に流用 |
| `FormController`（site-forms API） | **無変更で再利用**（ハニーポット・確認メール・署名付きダウンロードをそのまま享受） |
| リテンションスケジューラ（毎日04:00） | channel条件を追加して拡張 |
| `ChatbotFaqResource`（Filament） | `is_public` トグル追加（運用者は同一画面で管理） |

---

## Step 1: 公開FAQフラグ基盤（`is_public`）

### 目的
FAQを「公開サイト表示用」と「内部職員用」に分離し、公開チャットボットが公開FAQのみを参照できるようにする。

### 実装内容
1. **マイグレーション**: `add_is_public_to_chatbot_faqs_table.php`
   - `is_public` (boolean, default **false**, index) 追加
   - 既存FAQは全て内部用（default false）で後方互換
2. **モデル**: `ChatbotFaq` に `is_public` を fillable/casts/logOnly に追加 + `scopePublic($query)`（`is_public=true`）新設
3. **Filament**: `ChatbotFaqResource` フォームに `Toggle::make('is_public')`（ラベル「公開サイトに表示」）、テーブルにバッジカラム追加

### テスト計画
- `tests/Unit/Chatbot/ChatbotFaqPublicScopeTest.php`（4テスト）:
  1. `scopePublic()` は `is_public=true` のFAQのみ返す
  2. `is_public=false`（既存FAQ）は公開検索に含まれない
  3. `is_active=false` かつ `is_public=true` は検索対象外（既存 `scopeActive` と併用）
  4. `is_public` の変更が activity log に記録される

### 完了条件
- [x] マイグレーション適用・ロールバック検証済み
- [x] 既存FAQテスト全合格（デフォルトfalseで後方互換）

---

## Step 2: 公開インテント定義・認識器

### 目的
「料金は？」「機能を知りたい」「デモしたい」「資料が欲しい」「問い合わせたい」「営業時間は？」等の公開サイト特有の質問を分類する。

### 実装内容
1. **設定**: `config/chatbot.php` に `public` セクション追加:
   ```php
   'public' => [
       'rate_limit' => '20,1',
       'min_submit_seconds' => 2,
       'allowed_origins' => ['http://localhost:8080'],  // 本番で実ドメインへ
       'intents' => [
           'pricing' => ['patterns' => ['/料金|いくら|費用|月額|価格|プラン/u']],
           'features' => ['patterns' => ['/機能|できる|何が|特徴|インボイス|日割り|会計/u']],
           'demo_request' => ['patterns' => ['/デモ|体験|試し/u']],
           'catalog_request' => ['patterns' => ['/資料|カタログ|パンフ|ダウンロード/u']],
           'contact' => ['patterns' => ['/問い合わせ|相談|連絡|電話/u']],
           'hours' => ['patterns' => ['/営業時間|受付時間|いつまで/u']],
           'lead' => ['patterns' => ['/導入|検討|見積|聞きたい/u']],
       ],
       'synonyms' => ['値段' => '料金', 'タダ' => '無料', '見学' => 'デモ'],
   ],
   ```
2. **サービス**: `app/Services/Chatbot/Public/PublicIntentRecognizer.php` 新設
   - 内部 `IntentRecognizer` をラップせず**独立実装**（configの `public.intents` を参照・内部インテントに干渉しない）
   - 戻り値: `IntentDTO`（再利用）
3. **同義語展開**: 既存 `SynonymExpander` を public.synonyms で再利用

### テスト計画
- `tests/Unit/Chatbot/Public/PublicIntentRecognizerTest.php`（5テスト）:
  1. 「料金はいくら？」→ `pricing`
  2. 「どんな機能がありますか」→ `features`
  3. 「デモを体験したい」→ `demo_request`
  4. 「資料をダウンロードしたい」→ `catalog_request`
  5. 内部インテント（「山田さんの請求額」等のPII質問）は `faq` フォールバック（**入居者照会に分類されないこと**）

### 完了条件
- [x] 内部インテント設定と完全分離（configキー衝突なし）
- [x] PII質問が公開チャットで照会されないことを保証

---

## Step 3: 公開FAQ検索サービス

### 目的
`is_public=true` のFAQのみを検索する公開版レスポンダー（施設スコープなし）。

### 実装内容
1. **サービス**: `app/Services/Chatbot/Public/PublicFaqResponder.php` 新設
   - 既存 `FaqResponder` のスコアリングロジック（キーワード一致→Bigram Jaccardフォールバック）を**呼び出し側でスコープ制御**
   - `searchPublic(string $query): Collection` — `ChatbotFaq::query()->where('is_public', true)->where('is_active', true)` を対象
   - `BigramTokenizer` / `config('chatbot.faq.bigram_threshold')` をそのまま再利用
2. **非変更保証**: 既存 `FaqResponder::search()` は**1行も変更しない**（内部テスト443件の保護）

### テスト計画
- `tests/Unit/Chatbot/Public/PublicFaqResponderTest.php`（4テスト）:
  1. 公開FAQがキーワード一致で返る（スコア順）
  2. 内部専用FAQ（is_public=false）は返らない
  3. キーワード未一致時にBi-gramフォールバックが動作する
  4. 一致なしは空コレクション

### 完了条件
- [x] 既存 `FaqResponderTest`（6テスト）無変更で合格
- [x] 公開FAQが0件でもエラーなし

---

## Step 4: 会話ログ拡張（channel / visitor_id）

### 目的
公開チャットの会話を既存 `chat_logs` に記録し、内部ログと混在させずに分析・リテンション管理できるようにする。

### 実装内容
1. **マイグレーション**: `add_channel_and_visitor_to_chat_logs_table.php`
   - `channel` (string, default **'internal'**, index) — 'internal' | 'public'
   - `visitor_id` (uuid, nullable, index) — 匿名訪問者識別子（Cookie/localStorage）
2. **モデル**: `ChatLog` に `channel`/`visitor_id` を fillable/casts 追加 + `scopeChannel($query, string $channel)` 新設
3. **後方互換**: 既存内部ログは全て `channel='internal'`（default）として扱われる

### テスト計画
- `tests/Unit/ChatLogPublicTest.php`（3テスト）:
  1. `channel='public'` + `visitor_id` 付きでログ保存できる
  2. `scopeChannel('public')` / `scopeChannel('internal')` が正しく分離する
  3. 既存ログ（channel未指定作成）は 'internal' として扱われる

### 完了条件
- [x] 既存 `ChatLogModelTest` 無変更で合格
- [x] 内部チャットボットのログ記録に影響なし

---

## Step 5: 公開オーケストレータ

### 目的
認証なし・PII参照なしで、公開インテント→応答を生成するオーケストレータ。

### 実装内容
1. **サービス**: `app/Services/Chatbot/Public/PublicChatbotService.php` 新設
   - `handle(PublicChatRequest $request): PublicChatResponse`
   - ハンドラ分岐:
     - `pricing` / `features` / `hours` → 公開FAQ検索（Step 3）→ 該当なき場合は定型案内+アクションリンク
     - `demo_request` / `catalog_request` / `contact` / `lead` → リード獲得フロー（Step 7）への誘導応答
     - `escalate` → 問い合わせフォーム/電話番号案内（**内部ChatEscalationには記録しない**）
     - fallback → 公開FAQ検索 → 該当なき場合は「以下からお選びください」+ クイックリプライ
2. **DTO**: `app/Services/Chatbot/Public/DTO/PublicChatRequest.php` / `PublicChatResponse.php` 新設（内部DTOの構造を踏襲、`visitorId`/`channel` 追加）
3. **PII参照禁止の強制**: サービス内で `Resident` / `MonthlyInvoice` / `DailyCharge` モデルを**importしない**（コードレビュー+テストで担保）
4. **応答テンプレ**: `resources/views/chatbot/public/*.blade.php`（エスケープ必須）

### テスト計画
- `tests/Unit/Chatbot/Public/PublicChatbotServiceTest.php`（6テスト）:
  1. pricing質問 → 公開FAQ応答 or 定型案内+アクションリンク
  2. demo_request → リード獲得フロー応答（フォーム誘導）
  3. escalate → 問い合わせ先案内（内部エスカレーションレコードを作成しない）
  4. fallback → クイックリプライ付き応答
  5. 応答にPII（入居者名・部屋番号・請求金額）が含まれない
  6. ログに `channel='public'` + `visitor_id` が記録される

### 完了条件
- [x] 内部 `ChatbotService` 無変更で合格
- [x] 静的解析で内部PIIモデルへの依存がないことを確認

---

## Step 6: 公開API・セキュリティ

### 目的
認証なしで安全に使える公開APIエンドポイントを提供する。

### 実装内容
1. **コントローラ**: `app/Http/Controllers/PublicChatbotController.php` 新設
   - `message(Request $request): JsonResponse`
   - バリデーション: `message` (required, string, 1-500文字)
   - **ハニーポット**: `website` フィールド非空 → 201を返して黙って破棄（`FormController` パターン踏襲）
   - **最短時間チェック**: `form_loaded_at` から2秒以内 → 同上
   - `visitor_id` (uuid) を Cookie から取得、無ければレスポンスで新規発行
2. **ルート**: `routes/web.php` に追加:
   ```php
   Route::post('/api/public/chatbot/message', [PublicChatbotController::class, 'message'])
       ->name('public.chatbot.message')
       ->middleware('throttle:' . config('chatbot.public.rate_limit'));
   ```
3. **CORS**: `config/cors.php` に `api/public/chatbot/*` パス + `config('chatbot.public.allowed_origins')` を反映（静的サイトとAPIのオリジン差異対応）
4. **XSS対策**: 応答はJSON、ウィジェット側でtextContent描画（Step 9）

### テスト計画
- `tests/Feature/Chatbot/Public/PublicChatbotApiTest.php`（7テスト）:
  1. 認証なしでメッセージ送信できる（200 + reply）
  2. レート制限が動作する（21回目で429）
  3. ハニーポット検知時は201返却+ログ保存なし（黙って破棄）
  4. 最短時間チェック（2秒以内）で同上
  5. 空メッセージは422
  6. 初回レスポンスで `visitor_id`（Set-Cookie）が発行される
  7. 許可オリジン外からのリクエストがCORSで拒否される

### 完了条件
- [x] 未認証でも安全に動作（内部API `/api/chatbot/*` とは完全独立）
- [x] 内部チャットボットAPIのテスト全合格（影響なし）

---

## Step 7: リード獲得フロー

### 目的
チャット内での自然な会話から、既存フォームAPI（確認メール・署名付きダウンロード・スパム対策込み）へ誘導し、リードを獲得する。

### 実装内容
1. **アクションリンク**: `ChatActionLinkGenerator` パターンを踏襲した `PublicActionLinkGenerator` 新設:
   - `catalog` → `/request/catalog.html`、`demo` → `/request/demo.html`、`inquiry` → `/request/inquiry.html`、`pricing` → `/product/pricing.html`
2. **リードガイド応答**: `demo_request`/`catalog_request` インテント時に、**チャット内ミニフォーム**（氏名/メール/施設名・3項目）を返す:
   - 応答JSON: `{ reply, intent: 'lead_capture', form: { fields: [...], endpoint: '/api/site-forms/catalog' } }`
   - ウィジェットがミニフォームを描画し、**既存 `POST /api/site-forms/{type}` に直接送信**（FormControllerのハニーポット・確認メール・署名付きダウンロードURLをそのまま享受）
3. **送信成功時**: 応答 `complete.html?type=...` へのリンクを提示（既存完了画面を流用）
4. **离脱時**: ミニフォーム送信を止めた場合も、通常フォームへのリンクを常時表示

### テスト計画
- `tests/Feature/Chatbot/Public/PublicLeadFlowTest.php`（5テスト）:
  1. `demo_request` 質問 → `lead_capture` 応答 + `form.endpoint = /api/site-forms/demo`
  2. `catalog_request` 質問 → `form.endpoint = /api/site-forms/catalog`
  3. 応答にアクションリンク（通常フォームURL）が含まれる
  4. ミニフォーム送信データで既存 site-forms API が201を返す（統合確認）
  5. lead フロー経由の送信でも確認メールが送信される

### 完了条件
- [x] 既存 `FormController` / `FormSubmissionTest` 無変更で合格
- [x] リード送信時に確認メール+資料ダウンロードURLが機能する

---

## Step 8: ウィジェットJS配信基盤

### 目的
静的サイトに1行の `<script>` で埋め込める、自己完結型ウィジェットを配信する。

### 実装内容
1. **アセット**: `public/vendor/chatbot/widget.js` 新設（Vanilla JS・フレームワーク非依存）:
   - **Shadow DOM** でカプセル化（サイトCSSと衝突しない）
   - 右下FAB（Floating Action Button）→ クリックでパネル展開/折りたたみ
   - 設定は `data-*` 属性で上書き可能（`data-api-url`, `data-primary-color`, `data-title`）
2. **配信ルート**: `routes/web.php` に `GET /chatbot/widget.js`（`Cache-Control: public, max-age=86400`、`Content-Type: application/javascript`）
3. **CSS変数**: `data-primary-color`（既定 `#1e3a8a`・サイトのブランドカラーと統一）

### テスト計画
- `tests/Feature/Chatbot/Public/WidgetAssetTest.php`（2テスト）:
  1. `GET /chatbot/widget.js` が200 + JSコンテンツを返す
  2. レスポンスに `Cache-Control` ヘッダーとAPIエンドポイントURLが含まれる

### 完了条件
- [x] 単一scriptタグで設置可能（`<script defer src="/chatbot/widget.js"></script>`）
- [x] 既存サイトCSSに影響を与えない（Shadow DOM）

---

## Step 9: ウィジェットUI完成

### 目的
訪問者が直感的に使えるチャットUXを実装する。

### 実装内容
1. **メッセージリスト**: bot/userバブル、タイピングインジケータ
2. **クイックリプライ**: 初回表示時に推奨質問（「料金を知りたい」「機能を知りたい」「資料請求」「デモ希望」）をボタン表示
3. **アクションリンク**: 応答内のリンクをボタン化（新規タブで開く）
4. **リードミニフォーム**: パネル内フォーム（氏名/メール/施設名）→ site-forms API送信 → 完了メッセージ
5. **永続化**: `visitor_id`（UUID・初回生成・localStorage）、会話履歴（最新20件・localStorage）
6. **モバイル対応**: 画面幅480px以下でパネル全画面化、FABのタップ領域48px以上
7. **a11y**: `aria-label`、キーボード操作（Escで閉じる）、`role="log"`

### テスト計画
- `tests/Feature/Chatbot/Public/WidgetIntegrationTest.php`（2テスト）:
  1. widget.js に `shadow` モード・`textContent` 描画（XSS回避）のコードが含まれる
  2. widget.js に内部APIエンドポイント（`/api/chatbot/`・認証付きルート）への参照が**含まれない**
- **手動確認チェックリスト**（文書化）: FAB表示/パネル開閉/クイックリプライ/ミニフォーム送信/モバイル375px/ESD閉じ/複数ページ遷移で visitor_id 維持

### 完了条件
- [x] XSS検証（メッセージに `<script>` を含めても無害化）
- [x] 内部エンドポイント・PIIへの参照なし

---

## Step 10: 静的サイト31ページへの埋め込み

### 目的
商用サイト全ページにウィジェットを設置する。

### 実装内容
1. **埋め込みスクリプト**: `scripts/embed-chatbot.ps1` 新設（冪等・BOM付き）:
   - `website/**/*.html` の `</body>` 直前に以下を挿入（重複挿入ガード付き）:
     ```html
     <!-- Chatbot widget -->
     <script defer src="/chatbot/widget.js" data-api-url="/api/public/chatbot/message" data-primary-color="#1e3a8a"></script>
     ```
2. **注意**: ウィジェットはLaravel配信のため、**静的サイトとAPIのオリジン統一**が必要（本番: 同一ドメイン配下に `/chatbot/widget.js` とAPIを配置、または `data-api-url` にAPIオリジンを指定 + CORS許可）
3. **404ページ除外**: `404.html` には埋め込みしない（誤動作防止）

### テスト計画
- `tests/Feature/Chatbot/Public/SiteEmbedTest.php`（2テスト）:
  1. 全30ページ（404除く）に widget script タグが1回だけ含まれる
  2. `pwsh scripts/check-links.ps1` が合格し続ける（壊れたリンクなし）
- **スクリプト検証**: `embed-chatbot.ps1` を2回実行して重複挿入されないことを確認

### 完了条件
- [x] 冪等性確認済み
- [x] 404.html に埋め込みなし

---

## Step 11: ログ運用・分析

### 目的
公開チャットのログを運用可能な状態にし、FAQ改善のサイクルを回す。

### 実装内容
1. **リテンション拡張**: 既存スケジューラ（毎日04:00）のクリーンアップを `channel` 条件対応に拡張
   - internal: `retention_days`（90日）/ public: `chatbot.public.retention_days`（**30日**・PIIなしでも短期）
2. **未回答分析**: `ChatbotFaqMissStatsWidget` に `channel` フィルタ追加（公開チャットの未回答質問を抽出→公開FAQ追加のネタ源）
3. **集計**: 公開チャットのインテント別件数・リード転換数（lead_capture→site-forms送信）をクエリスコープで提供

### テスト計画
- `tests/Feature/Chatbot/Public/PublicChatLogRetentionTest.php`（3テスト）:
  1. 保持期間超過の public ログが削除される
  2. internal ログは public の保持期間設定に影響されない
  3. 公開ログの未回答（faq_matched=false）抽出が動作する

### 完了条件
- [x] 既存 `ChatLogRetentionTest` 無変更で合格
- [x] スケジューラ登録確認（`routes/console.php`）

---

## Step 12: リグレッション・最終検証

### 目的
チャットボット公開化が既存機能に一切影響しないことを保証する。

### 実装内容
1. **リグレッションテスト**: `tests/Feature/Regression/PublicChatbotRegressionTest.php` 新設（5テスト）:
   1. 内部チャットボット（`/api/chatbot/message`）が正常動作する（既存応答・スコープ不変）
   2. site-forms API（5タイプ）が正常動作する（ハニーポット・確認メール含む）
   3. 請求書PDF・会計CSV出力が正常動作する
   4. Filament既存リソース（ChatbotFaqResource含む）のCRUDが正常動作する
   5. 公開APIが内部インテント（resident_lookup等）に分類されない（設定分離の再確認）
2. **最終検証コマンド**:
   - `php artisan test`（既存443+新規48=491テスト・既存5件失敗は InvoiceCalculationBasisTest で本計画と無関係）
   - `pwsh scripts/check-links.ps1` / `pwsh scripts/detect-mojibake.ps1`
   - `vendor/bin/pint --test app tests routes config database`
3. **ドキュメント**: `README.md` にウィジェット設置手順・`config/chatbot.php` の `public` セクション説明を追記

### 完了条件
- [x] 全新規テスト（48件）合格
- [x] 既存テスト無影響（本計画前に失敗していた5件を除く）
- [x] CI（`.github/workflows/ci.yml`）緑化

---

## 新規テスト一覧（48テスト）

| Step | テストファイル | 件数 |
|------|---------------|------|
| 1 | `tests/Unit/Chatbot/ChatbotFaqPublicScopeTest.php` | 4 |
| 2 | `tests/Unit/Chatbot/Public/PublicIntentRecognizerTest.php` | 5 |
| 3 | `tests/Unit/Chatbot/Public/PublicFaqResponderTest.php` | 4 |
| 4 | `tests/Unit/ChatLogPublicTest.php` | 3 |
| 5 | `tests/Unit/Chatbot/Public/PublicChatbotServiceTest.php` | 6 |
| 6 | `tests/Feature/Chatbot/Public/PublicChatbotApiTest.php` | 7 |
| 7 | `tests/Feature/Chatbot/Public/PublicLeadFlowTest.php` | 5 |
| 8 | `tests/Feature/Chatbot/Public/WidgetAssetTest.php` | 2 |
| 9 | `tests/Feature/Chatbot/Public/WidgetIntegrationTest.php` | 2 |
| 10 | `tests/Feature/Chatbot/Public/SiteEmbedTest.php` | 2 |
| 11 | `tests/Feature/Chatbot/Public/PublicChatLogRetentionTest.php` | 3 |
| 12 | `tests/Feature/Regression/PublicChatbotRegressionTest.php` | 5 |
| **計** | **12ファイル** | **48** |

---

## セキュリティ・リスクと対策

| リスク | 対策 | 担当Step |
|--------|------|----------|
| **内部情報漏洩** | 公開FAQ（is_public=true）のみ参照・PIIモデルimport禁止・内部インテント非搭載 | 2, 3, 5 |
| **ボット/スパム** | ハニーポット+最短時間チェック+`throttle:20,1`（IPベース） | 6 |
| **XSS** | 応答はJSON、ウィジェットはtextContent描画+Shadow DOM | 6, 8, 9 |
| **DDoS/悪用** | IPレート制限+CORSオリジン制限+Cloudflare等CDN前提 | 6 |
| **CSRF** | 公開APIはCSRFトークンなし（Cookie認証を使わないステートレス設計） | 6 |
| **visitor_id なりすまし** | UUID + ログは分析用のみ（権限判定に使用しない） | 4 |
| **内部チャットボットへの影響** | コントローラ/サービス/設定を完全分離（`Public` 名前空間+config `public` セクション） | 全Step |

---

## 実装順序とタイムライン

```
Week 1
├─ Step 1: 公開FAQフラグ基盤（0.5日）
├─ Step 2: 公開インテント定義・認識器（1日）
├─ Step 3: 公開FAQ検索サービス（0.5日）
├─ Step 4: 会話ログ拡張（0.5日）
└─ Step 5: 公開オーケストレータ（1日）

Week 2
├─ Step 6: 公開API・セキュリティ（1日）
├─ Step 7: リード獲得フロー（1日）
├─ Step 8: ウィジェットJS配信基盤（0.5日）
├─ Step 9: ウィジェットUI完成（1日）
├─ Step 10: 静的サイト埋め込み（0.5日）
├─ Step 11: ログ運用・分析（0.5日）
└─ Step 12: リグレッション・最終検証（0.5日）
```

各Step完了時に `php artisan test` を実行し、ベースライン（443 passed）からの退行がないことを確認する。

---

## 完了条件（Definition of Done）

- [ ] 12ステップすべての実装完了
- [ ] 新規テスト48件作成・全合格
- [ ] 既存テスト（443 passed）の継続通過（本計画前に失敗していた InvoiceCalculationBasisTest 5件は本計画の対象外）
- [ ] 公開APIが認証なしで安全に動作（レート制限・ハニーポット・CORS検証済み）
- [ ] 公開FAQ（is_public=true）のみ参照し、PII・内部データに到達しないことの検証済み
- [ ] 静的サイト30ページ（404除く）への埋め込み完了・重複なし
- [ ] リード獲得フロー（チャット→ミニフォーム→site-forms→確認メール）のE2E確認
- [ ] CI（GitHub Actions）緑化
- [ ] README.md に設置手順・設定説明を追記

---

## 未着手時のベースライン（2026-10-10時点）

- テスト: 443 passed / 5 failed（InvoiceCalculationBasisTest 5件・本計画のスコープ外）
- 静的サイト: 31 HTML（リンク・構文・エンコード検証済み）
- 内部チャットボット: 実装済み（ChatbotService/FaqResponder/IntentRecognizer/FuzzyMatcher/BigramTokenizer/SynonymExpander/ChatActionLinkGenerator/ChatbotFaq/ChatLog/ChatEscalation/Filament 3資産）
- 公開チャットボット: **未実装**（本計画のスコープ）
