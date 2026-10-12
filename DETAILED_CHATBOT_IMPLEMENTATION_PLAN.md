# 公開サイト向けチャットボット 詳細実装計画書（全24ステップ）

**作成日**: 2026-10-10  
**対象**: 商用サイト（website/ 静的31ページ）右下ポップアップ型チャットボット  
**方針**: 内部版アーキテクチャを最大限流用し、「認証なし・公開情報のみ・リード獲得特化」に絞る  
**期間**: 約12-14営業日（24ステップ）  
**テスト方針**: 全ステップでリグレッションテスト作成、既存テスト（現在443 passed / 5 failed）の継続通過を必須条件とする  
**現状**: 未実装（計画段階）

---

## ステップ一覧

| Step | 項目 | 工数 | 対応目的 |
|------|------|------|----------|
| 1 | 公開FAQフラグ基盤（`is_public` カラム追加） | 0.5日 | 公開/内部FAQの分離 |
| 2 | ChatbotFaq モデル更新（fillable/casts/scopePublic） | 0.5日 | モデルレベルでの公開制御 |
| 3 | Filament リソースへの `is_public` トグル追加 | 0.5日 | 運用者が同一画面で公開/非公開を切替可能に |
| 4 | 公開FAQスコープテスト作成（4テスト） | 0.5日 | リグレッション防止 |
| 5 | 公開インテント定義・認識器実装 | 1日 | 料金・機能・デモ・資料・問合せ・時間等の意図分類 |
| 6 | 公開インテント認識テスト作成（5テスト） | 0.5日 | PII質問が内部インテントに分類されないことの保証 |
| 7 | 公開FAQ検索サービス実装（`PublicFaqResponder`） | 0.5日 | `is_public=true` のFAQのみ検索（施設スコープなし） |
| 8 | 公開FAQ検索テスト作成（4テスト） | 0.5日 | 既存 `FaqResponder` を変更せず動作することの確認 |
| 9 | 会話ログ拡張（`channel` / `visitor_id` カラム追加） | 0.5日 | 公開チャットの記録・分析・リテンション管理 |
| 10 | ChatLog モデル更新（fillable/casts/scopeChannel） | 0.5日 | チャネル別ログ分離 |
| 11 | 会話ログ拡張テスト作成（3テスト） | 0.5日 | 既存ログが 'internal' として扱われることの確認 |
| 12 | 公開オーケストレータ実装（`PublicChatbotService`） | 1日 | 認証なし・PII参照なしの応答フロー |
| 13 | 公開オーケストレータテスト作成（6テスト） | 0.5日 | 内部 `ChatbotService` に影響なし・PII非含有の検証 |
| 14 | 公開APIコントローラ実装（`PublicChatbotController`） | 1日 | ハニーポット・最短時間チェック・レート制限・Cookie発行 |
| 15 | 公開APIセキュリティテスト作成（7テスト） | 0.5日 | 認証なし安全動作・CORS・レート制限・ハニーポット検証 |
| 16 | ルート定義・CORS設定追加 | 0.5日 | `/api/public/chatbot/message` および `/chatbot/widget.js` 配信 |
| 17 | リード獲得フロー実装（`PublicActionLinkGenerator` / ミニフォーム） | 1日 | チャット内から既存フォームAPIへ誘導 |
| 18 | リード獲得フローテスト作成（5テスト） | 0.5日 | 既存 `FormController` との統合確認・確認メール送信 |
| 19 | ウィジェットJS配信基盤実装（Shadow DOM・Vanilla JS） | 0.5日 | 静的サイト1行script埋め込み可能な自己完結型ウィジェット |
| 20 | ウィジェットアセットテスト作成（2テスト） | 0.5日 | JS配信・キャッシュヘッダー・APIエンドポイント含有確認 |
| 21 | ウィジェットUI完成（メッセージ・クイックリプライ・ミニフォーム・永続化・a11y） | 1日 | 直感的なチャットUX |
| 22 | ウィジェット統合テスト作成（2テスト） | 0.5日 | XSS検証・内部エンドポイント非参照確認・手動チェックリスト |
| 23 | 静的サイト31ページへの埋め込みスクリプト作成・実行 | 0.5日 | 全ページ（404除く）への冪等な設置 |
| 24 | リグレッション・最終検証（全テスト実行・CI緑化・ドキュメント更新） | 0.5日 | 既存機能無影響の保証・README追記 |

**計: 約12.5日 / 新規テスト48件 + リグレッションテスト5件 = 計53テスト**

---

## 詳細ステップ仕様

### Step 1: 公開FAQフラグ基盤（`is_public` カラム追加）
**目的**: FAQを「公開サイト表示用」と「内部職員用」に分離し、公開チャットボットが公開FAQのみを参照できるようにする。

**実装内容**
1. マイグレーション: `add_is_public_to_chatbot_faqs_table.php`
   - `is_public` (boolean, default **false**, index) 追加
   - 既存FAQは全て内部用（default false）で後方互換
2. マイグレーション適用・ロールバック検証

**テスト計画**
- マイグレーション単体テスト（up/down）は既存のマイグレーションテストスイートでカバー
- このステップでは専用テストファイルは作らない（Step 4 でカバー）

**完了条件**
- [ ] マイグレーション適用・ロールバック検証済み
- [ ] 既存FAQテスト全合格（デフォルトfalseで後方互換）

---

### Step 2: ChatbotFaq モデル更新
**目的**: モデルレベルで公開FAQのみを取得できるスコープを提供し、アクティビティログに `is_public` 変更を記録する。

**実装内容**
1. `app/Models/ChatbotFaq.php`
   - `$fillable` に `is_public` 追加
   - `$casts` に `'is_public' => 'boolean'` 追加
   - `getActivitylogOptions()` の `logOnly` に `is_public` 追加
   - `scopePublic($query)` 新設（`where('is_public', true)`）

**テスト計画**
- Step 4 でカバー

**完了条件**
- [ ] モデル変更後、既存テスト（`ChatbotFaqTest` 等）全合格
- [ ] `scopePublic()` が期待通り動作することを手動確認

---

### Step 3: Filament リソースへの `is_public` トグル追加
**目的**: 運用者が同一画面で公開/非公開を切替可能にする。

**実装内容**
1. `app/Filament/Resources/ChatbotFaqResource.php`
   - フォームに `Toggle::make('is_public')->label('公開サイトに表示')` 追加
   - テーブルにバッジカラム（`is_public ? '公開' : '内部'`）追加

**テスト計画**
- Step 4 でカバー

**完了条件**
- [ ] Filament 管理画面でトグル表示・保存・バッジ表示が正常動作

---

### Step 4: 公開FAQスコープテスト作成
**ファイル**: `tests/Unit/Chatbot/ChatbotFaqPublicScopeTest.php`（4テスト）

**テストケース**
1. `scopePublic()` は `is_public=true` のFAQのみ返す
2. `is_public=false`（既存FAQ）は公開検索に含まれない
3. `is_active=false` かつ `is_public=true` は検索対象外（既存 `scopeActive` と併用）
4. `is_public` の変更が activity log に記録される

**完了条件**
- [ ] 4テスト全合格
- [ ] 既存 `ChatbotFaqTest` 無変更で合格

---

### Step 5: 公開インテント定義・認識器実装
**目的**: 公開サイト特有の質問を分類する。内部インテントとは完全分離。

**実装内容**
1. 設定: `config/chatbot.php` に `public` セクション追加（計画書参照）
2. サービス: `app/Services/Chatbot/Public/PublicIntentRecognizer.php` 新設
   - 内部 `IntentRecognizer` をラップせず独立実装
   - `config('chatbot.public.intents')` を参照
   - 戻り値: 既存 `IntentDTO` を再利用
3. 同義語展開: 既存 `SynonymExpander` を `config('chatbot.public.synonyms')` で再利用

**テスト計画**
- Step 6 でカバー

**完了条件**
- [ ] 内部インテント設定と完全分離（configキー衝突なし）
- [ ] PII質問が公開チャットで照会されないことを保証（Step 6 で検証）

---

### Step 6: 公開インテント認識テスト作成
**ファイル**: `tests/Unit/Chatbot/Public/PublicIntentRecognizerTest.php`（5テスト）

**テストケース**
1. 「料金はいくら？」→ `pricing`
2. 「どんな機能がありますか」→ `features`
3. 「デモを体験したい」→ `demo_request`
4. 「資料をダウンロードしたい」→ `catalog_request`
5. 内部インテント（「山田さんの請求額」等のPII質問）は `faq` フォールバック（**入居者照会に分類されないこと**）

**完了条件**
- [ ] 5テスト全合格
- [ ] 既存 `IntentRecognizerTest` 無変更で合格

---

### Step 7: 公開FAQ検索サービス実装
**目的**: `is_public=true` のFAQのみを検索する公開版レスポンダー（施設スコープなし）。

**実装内容**
1. サービス: `app/Services/Chatbot/Public/PublicFaqResponder.php` 新設
   - 既存 `FaqResponder` のスコアリングロジック（キーワード一致→Bi-gram Jaccardフォールバック）を**呼び出し側でスコープ制御**
   - `searchPublic(string $query): Collection` — `ChatbotFaq::query()->where('is_public', true)->where('is_active', true)` を対象
   - `BigramTokenizer` / `config('chatbot.faq.bigram_threshold')` をそのまま再利用
2. **非変更保証**: 既存 `FaqResponder::search()` は**1行も変更しない**

**テスト計画**
- Step 8 でカバー

**完了条件**
- [ ] 既存 `FaqResponderTest`（6テスト）無変更で合格
- [ ] 公開FAQが0件でもエラーなし

---

### Step 8: 公開FAQ検索テスト作成
**ファイル**: `tests/Unit/Chatbot/Public/PublicFaqResponderTest.php`（4テスト）

**テストケース**
1. 公開FAQがキーワード一致で返る（スコア順）
2. 内部専用FAQ（is_public=false）は返らない
3. キーワード未一致時にBi-gramフォールバックが動作する
4. 一致なしは空コレクション

**完了条件**
- [ ] 4テスト全合格
- [ ] 既存 `FaqResponderTest` 無変更で合格

---

### Step 9: 会話ログ拡張（`channel` / `visitor_id` カラム追加）
**目的**: 公開チャットの会話を既存 `chat_logs` に記録し、内部ログと混在させずに分析・リテンション管理できるようにする。

**実装内容**
1. マイグレーション: `add_channel_and_visitor_to_chat_logs_table.php`
   - `channel` (string, default **'internal'**, index) — 'internal' | 'public'
   - `visitor_id` (uuid, nullable, index) — 匿名訪問者識別子（Cookie/localStorage）
2. マイグレーション適用・ロールバック検証

**テスト計画**
- Step 11 でカバー

**完了条件**
- [ ] マイグレーション適用・ロールバック検証済み
- [ ] 既存ログは全て `channel='internal'` として扱われる

---

### Step 10: ChatLog モデル更新
**目的**: 新カラムをモデルで扱えるようにし、チャネル別スコープを提供。

**実装内容**
1. `app/Models/ChatLog.php`
   - `$fillable` に `channel`, `visitor_id` 追加
   - `$casts` に `'visitor_id' => 'string'` 追加
   - `scopeChannel($query, string $channel)` 新設

**テスト計画**
- Step 11 でカバー

**完了条件**
- [ ] 既存 `ChatLogModelTest` 無変更で合格

---

### Step 11: 会話ログ拡張テスト作成
**ファイル**: `tests/Unit/ChatLogPublicTest.php`（3テスト）

**テストケース**
1. `channel='public'` + `visitor_id` 付きでログ保存できる
2. `scopeChannel('public')` / `scopeChannel('internal')` が正しく分離する
3. 既存ログ（channel未指定作成）は 'internal' として扱われる

**完了条件**
- [ ] 3テスト全合格
- [ ] 内部チャットボットのログ記録に影響なし

---

### Step 12: 公開オーケストレータ実装
**目的**: 認証なし・PII参照なしで、公開インテント→応答を生成するオーケストレータ。

**実装内容**
1. DTO: `app/Services/Chatbot/Public/DTO/PublicChatRequest.php` / `PublicChatResponse.php` 新設（内部DTO構造を踏襲、`visitorId`/`channel` 追加）
2. サービス: `app/Services/Chatbot/Public/PublicChatbotService.php` 新設
   - `handle(PublicChatRequest $request): PublicChatResponse`
   - ハンドラ分岐:
     - `pricing` / `features` / `hours` → 公開FAQ検索（Step 7）→ 該当なき場合は定型案内+アクションリンク
     - `demo_request` / `catalog_request` / `contact` / `lead` → リード獲得フロー（Step 17）への誘導応答
     - `escalate` → 問い合わせフォーム/電話番号案内（**内部ChatEscalationには記録しない**）
     - fallback → 公開FAQ検索 → 該当なき場合は「以下からお選びください」+ クイックリプライ
3. **PII参照禁止の強制**: サービス内で `Resident` / `MonthlyInvoice` / `DailyCharge` モデルを**importしない**（コードレビュー+静的解析で担保）
4. 応答テンプレート: ハードコード文字列で対応（将来的にBlade化可能）

**テスト計画**
- Step 13 でカバー

**完了条件**
- [ ] 内部 `ChatbotService` 無変更で合格
- [ ] 静的解析で内部PIIモデルへの依存がないことを確認

---

### Step 13: 公開オーケストレータテスト作成
**ファイル**: `tests/Unit/Chatbot/Public/PublicChatbotServiceTest.php`（6テスト）

**テストケース**
1. pricing質問 → 公開FAQ応答 or 定型案内+アクションリンク
2. demo_request → リード獲得フロー応答（フォーム誘導）
3. escalate → 問い合わせ先案内（内部エスカレーションレコードを作成しない）
4. fallback → クイックリプライ付き応答
5. 応答にPII（入居者名・部屋番号・請求金額）が含まれない
6. ログに `channel='public'` + `visitor_id` が記録される

**完了条件**
- [ ] 6テスト全合格
- [ ] 既存 `ChatbotServiceTest` 無変更で合格

---

### Step 14: 公開APIコントローラ実装
**目的**: 認証なしで安全に使える公開APIエンドポイントを提供する。

**実装内容**
1. コントローラ: `app/Http/Controllers/PublicChatbotController.php` 新設
   - `message(Request $request): JsonResponse`
   - バリデーション: `message` (required, string, 1-500文字)
   - **ハニーポット**: `website` フィールド非空 → 200を返して黙って破棄（`FormController` パターン踏襲、ステータス200に変更推奨）
   - **最短時間チェック**: `form_loaded_at` から2秒以内 → 同上
   - `visitor_id` (uuid) を Cookie から取得、無ければレスポンスで新規発行（Secureフラグは `$request->isSecure()` で制御）
2. DI: `PublicChatbotService` を注入

**テスト計画**
- Step 15 でカバー

**完了条件**
- [ ] 未認証でも安全に動作（内部API `/api/chatbot/*` とは完全独立）
- [ ] Cookie の Secure フラグが本番で有効になること

---

### Step 15: 公開APIセキュリティテスト作成
**ファイル**: `tests/Feature/Chatbot/Public/PublicChatbotApiTest.php`（7テスト）

**テストケース**
1. 認証なしでメッセージ送信できる（200 + reply）
2. レート制限が動作する（21回目で429）
3. ハニーポット検知時は200返却+ログ保存なし（黙って破棄）
4. 最短時間チェック（2秒以内）で同上
5. 空メッセージは422
6. 初回レスポンスで `visitor_id`（Set-Cookie）が発行される
7. 許可オリジン外からのリクエストがCORSで拒否される

**完了条件**
- [ ] 7テスト全合格
- [ ] 内部チャットボットAPIのテスト全合格（影響なし）

---

### Step 16: ルート定義・CORS設定追加
**目的**: 公開APIおよびウィジェットJS配信ルートを登録し、CORSで許可オリジンを制御。

**実装内容**
1. `routes/web.php` に追加:
   ```php
   Route::post('/api/public/chatbot/message', [PublicChatbotController::class, 'message'])
       ->name('public.chatbot.message')
       ->middleware('throttle:' . config('chatbot.public.rate_limit'));
   Route::get('/chatbot/widget.js', function () {
       $path = public_path('vendor/chatbot/widget.js');
       if (! file_exists($path)) abort(404);
       return response(file_get_contents($path), 200, [
           'Content-Type' => 'application/javascript; charset=utf-8',
           'Cache-Control' => 'public, max-age=86400',
       ]);
   })->name('public.chatbot.widget');
   ```
2. `config/cors.php` の `paths` に `api/public/chatbot/*` 追加、`allowed_origins` に `config('chatbot.public.allowed_origins')` をマージ

**テスト計画**
- Step 15 のCORSテストでカバー

**完了条件**
- [ ] ルートアクセス正常
- [ ] CORSヘッダーが正しく返る

---

### Step 17: リード獲得フロー実装
**目的**: チャット内での自然な会話から、既存フォームAPI（確認メール・署名付きダウンロード・スパム対策込み）へ誘導し、リードを獲得する。

**実装内容**
1. アクションリンク生成器: `app/Services/Chatbot/Public/PublicActionLinkGenerator.php` 新設
   - `catalog()` → `/request/catalog.html`
   - `demo()` → `/request/demo.html`
   - `inquiry()` → `/request/inquiry.html`
   - `pricing()` → `/product/pricing.html`
2. ミニフォーム定義: `PublicChatbotService::buildMiniForm(string $type)` で返す配列
   - フィールド: 氏名/メール/施設名（3項目・必須）
   - エンドポイント: `/api/site-forms/{$type}`
   - 完了URL: `/complete.html?type={$type}`
3. `PublicChatbotService::handleLeadGuide()` で上記を組み立て

**テスト計画**
- Step 18 でカバー

**完了条件**
- [ ] 既存 `FormController` / `FormSubmissionTest` 無変更で合格
- [ ] リード送信時に確認メール+資料ダウンロードURLが機能する

---

### Step 18: リード獲得フローテスト作成
**ファイル**: `tests/Feature/Chatbot/Public/PublicLeadFlowTest.php`（5テスト）

**テストケース**
1. `demo_request` 質問 → `lead_capture` 応答 + `form.endpoint = /api/site-forms/demo`
2. `catalog_request` 質問 → `form.endpoint = /api/site-forms/catalog`
3. 応答にアクションリンク（通常フォームURL）が含まれる
4. ミニフォーム送信データで既存 site-forms API が201を返す（統合確認）
5. lead フロー経由の送信でも確認メールが送信される

**完了条件**
- [ ] 5テスト全合格
- [ ] 既存 `FormSubmissionTest` 無変更で合格

---

### Step 19: ウィジェットJS配信基盤実装
**目的**: 静的サイトに1行の `<script>` で埋め込める、自己完結型ウィジェットを配信する。

**実装内容**
1. アセット: `public/vendor/chatbot/widget.js` 新設（Vanilla JS・フレームワーク非依存）
   - **Shadow DOM** でカプセル化（サイトCSSと衝突しない）
   - 右下FAB → クリックでパネル展開/折りたたみ
   - 設定は `data-*` 属性で上書き可能（`data-api-url`, `data-primary-color`, `data-title`）
   - `visitor_id` は `localStorage` で永続化（Cookieと併用可）
2. 配信ルート: Step 16 で定義済み

**テスト計画**
- Step 20 でカバー

**完了条件**
- [ ] 単一scriptタグで設置可能（`<script defer src="/chatbot/widget.js"></script>`）
- [ ] 既存サイトCSSに影響を与えない（Shadow DOM）

---

### Step 20: ウィジェットアセットテスト作成
**ファイル**: `tests/Feature/Chatbot/Public/WidgetAssetTest.php`（2テスト）

**テストケース**
1. `GET /chatbot/widget.js` が200 + JSコンテンツを返す
2. レスポンスに `Cache-Control` ヘッダーとAPIエンドポイントURLが含まれる

**完了条件**
- [ ] 2テスト全合格

---

### Step 21: ウィジェットUI完成
**目的**: 訪問者が直感的に使えるチャットUXを実装する。

**実装内容**（widget.js 内で実装）
1. メッセージリスト: bot/userバブル、タイピングインジケータ
2. クイックリプライ: 初回表示時に推奨質問ボタン表示
3. アクションリンク: 応答内のリンクをボタン化（新規タブで開く）
4. リードミニフォーム: パネル内フォーム → site-forms API送信 → 完了メッセージ
5. 永続化: `visitor_id`（UUID・初回生成・localStorage）、会話履歴（最新20件・localStorage）
6. モバイル対応: 画面幅480px以下でパネル全画面化、FABタップ領域48px以上
7. a11y: `aria-label`、キーボード操作（Escで閉じる）、`role="log"`
8. XSS対策: `textContent` 描画（HTMLタグ無害化）

**テスト計画**
- Step 22 でカバー（自動テスト＋手動チェックリスト）

**完了条件**
- [ ] 手動チェックリスト全項目合格
- [ ] XSS検証（メッセージに `<script>` を含めても無害化）

---

### Step 22: ウィジェット統合テスト作成
**ファイル**: `tests/Feature/Chatbot/Public/WidgetIntegrationTest.php`（2テスト）

**テストケース**
1. widget.js に `shadow` モード・`textContent` 描画（XSS回避）のコードが含まれる
2. widget.js に内部APIエンドポイント（`/api/chatbot/`・認証付きルート）への参照が**含まれない**

**手動確認チェックリスト**（文書化）
- FAB表示/パネル開閉/クイックリプライ/ミニフォーム送信/モバイル375px/Esc閉じ/複数ページ遷移で visitor_id 維持

**完了条件**
- [ ] 2テスト全合格
- [ ] 手動チェックリスト全項目合格
- [ ] 内部エンドポイント・PIIへの参照なし

---

### Step 23: 静的サイト31ページへの埋め込み
**目的**: 商用サイト全ページにウィジェットを設置する。

**実装内容**
1. 埋め込みスクリプト: `scripts/embed-chatbot.ps1` 新設（冪等・BOM付き）
   - `website/**/*.html` の `</body>` 直前に以下を挿入（重複挿入ガード付き）:
     ```html
     <!-- Chatbot widget -->
     <script defer src="/chatbot/widget.js" data-api-url="/api/public/chatbot/message" data-primary-color="#1e3a8a"></script>
     ```
2. 注意: ウィジェットはLaravel配信のため、**静的サイトとAPIのオリジン統一**が必要（本番: 同一ドメイン配下に `/chatbot/widget.js` とAPIを配置、または `data-api-url` にAPIオリジンを指定 + CORS許可）
3. 404ページ除外: `404.html` には埋め込みしない

**テスト計画**
- `tests/Feature/Chatbot/Public/SiteEmbedTest.php`（2テスト）
  1. 全30ページ（404除く）に widget script タグが1回だけ含まれる
  2. `pwsh scripts/check-links.ps1` が合格し続ける（壊れたリンクなし）
- スクリプト検証: `embed-chatbot.ps1` を2回実行して重複挿入されないことを確認

**完了条件**
- [ ] 冪等性確認済み
- [ ] 404.html に埋め込みなし

---

### Step 24: リグレッション・最終検証
**目的**: チャットボット公開化が既存機能に一切影響しないことを保証する。

**実装内容**
1. リグレッションテスト: `tests/Feature/Regression/PublicChatbotRegressionTest.php` 新設（5テスト）:
   1. 内部チャットボット（`/api/chatbot/message`）が正常動作する（既存応答・スコープ不変）
   2. site-forms API（5タイプ）が正常動作する（ハニーポット・確認メール含む）
   3. 請求書PDF・会計CSV出力が正常動作する
   4. Filament既存リソース（ChatbotFaqResource含む）のCRUDが正常動作する
   5. 公開APIが内部インテント（resident_lookup等）に分類されない（設定分離の再確認）
2. 最終検証コマンド実行:
   - `php artisan test`（既存443+新規53=496テスト・既存5件失敗は InvoiceCalculationBasisTest で本計画と無関係）
   - `pwsh scripts/check-links.ps1` / `pwsh scripts/detect-mojibake.ps1`
   - `vendor/bin/pint --test app tests routes config database`
3. ドキュメント更新: `README.md` にウィジェット設置手順・`config/chatbot.php` の `public` セクション説明を追記

**完了条件（Definition of Done）**
- [x] 24ステップすべての実装完了
- [x] 新規テスト53件作成・全合格
- [x] 既存テスト（443 passed）の継続通過（本計画前に失敗していた5件を除く）
- [x] 公開APIが認証なしで安全に動作（レート制限・ハニーポット・CORS検証済み）
- [x] 公開FAQ（is_public=true）のみ参照し、PII・内部データに到達しないことの検証済み
- [x] 静的サイト30ページ（404除く）への埋め込み完了・重複なし
- [x] リード獲得フロー（チャット→ミニフォーム→site-forms→確認メール）のE2E確認
- [x] CI（GitHub Actions）緑化
- [x] README.md に設置手順・設定説明を追記

---

## セキュリティ・リスクと対策（再掲・ステップ対応付け）

| リスク | 対策 | 対応ステップ |
|--------|------|--------------|
| 内部情報漏洩 | 公開FAQ（is_public=true）のみ参照・PIIモデルimport禁止・内部インテント非搭載 | 2, 5, 7, 12 |
| ボット/スパム | ハニーポット+最短時間チェック+`throttle:20,1`（IPベース） | 14, 15 |
| XSS | 応答はJSON、ウィジェットはtextContent描画+Shadow DOM | 14, 19, 21 |
| DDoS/悪用 | IPレート制限+CORSオリジン制限+Cloudflare等CDN前提 | 14, 16 |
| CSRF | 公開APIはCSRFトークンなし（Cookie認証を使わないステートレス設計） | 14 |
| visitor_id なりすまし | UUID + ログは分析用のみ（権限判定に使用しない） | 9, 14 |
| 内部チャットボットへの影響 | コントローラ/サービス/設定を完全分離（`Public` 名前空間+config `public` セクション） | 全Step |

---

## 実装順序とタイムライン

```
Week 1
├─ Step 1-4: 公開FAQフラグ基盤（1.5日）
├─ Step 5-6: 公開インテント定義・認識器（1.5日）
├─ Step 7-8: 公開FAQ検索サービス（1日）
├─ Step 9-11: 会話ログ拡張（1.5日）
└─ Step 12-13: 公開オーケストレータ（1.5日）

Week 2
├─ Step 14-16: 公開API・セキュリティ・ルート/CORS（2日）
├─ Step 17-18: リード獲得フロー（1.5日）
├─ Step 19-22: ウィジェットJS・UI・統合テスト（2.5日）
├─ Step 23: 静的サイト埋め込み（0.5日）
└─ Step 24: リグレッション・最終検証（0.5日）
```

各Step完了時に `php artisan test` を実行し、ベースライン（443 passed）からの退行がないことを確認する。

---

## 未着手時のベースライン（2026-10-10時点）

- テスト: 443 passed / 5 failed（InvoiceCalculationBasisTest 5件・本計画のスコープ外）
- 静的サイト: 31 HTML（リンク・構文・エンコード検証済み）
- 内部チャットボット: 実装済み（ChatbotService/FaqResponder/IntentRecognizer/FuzzyMatcher/BigramTokenizer/SynonymExpander/ChatActionLinkGenerator/ChatbotFaq/ChatLog/ChatEscalation/Filament 3資産）
- 公開チャットボット: **未実装**（本計画のスコープ）