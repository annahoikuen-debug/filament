# チャットボット改善 詳細実装計画書（LLM不使用・全9案）

**作成日**: 2026-10-13
**前提**: LLM / 外部API / 機械学習サービスを一切使用しない。全て PHP 内・DB 内・クライアント側で完結する。
**設計原則**:
1. サーバー負荷の極小化（クライアント完結 → PHP 軽量処理 → インデックス活用 DB → 深夜バッチ）
2. マルチテナント `facility_id` スコープ分離の堅持
3. 後方互換性の完全担保（既存 API 形式・`ChatResponse` DTO・`quick_replies` 等の契約を壊さない）
4. 既存計画書 `CHATBOT_9_IMPROVEMENT_PLAN.md`（36 ステップ）との共存

---

## 改善テーマ一覧（推奨実装順）

| No | テーマ | サーバー負荷 | 効果 | 対象コンポーネント |
|:---|:---|:---:|:---:|:---|
| ① | 同義語辞書の DB 化・管理画面化 | ★☆☆☆☆ | 大 | Migration / `SynonymExpander` / Filament |
| ⑤ | マルチターン文脈の拡張 | ★☆☆☆☆ | 大 | `ChatbotService` / Cache |
| ④ | 入居者名の正規化索引 + 前方一致優先 | ★★☆☆☆ | 大 | Migration / `ResidentQueryService` / `FuzzyMatcher` |
| ② | 意図認識のスコアリング方式化 | ★☆☆☆☆ | 大 | `IntentRecognizer` / config |
| ⑥ | 不明確入力時の候補確認質問 | ★☆☆☆☆ | 中 | `ChatbotService` / Blade |
| ③ | FAQ キーワードの IDF 重み付け | ★★★☆☆ | 中 | Migration / `FaqResponder` / バッチ |
| ⑦ | 照会対象の拡充（5〜8 intent） | ★★☆☆☆ | 大 | `ChatbotService` / `ChatActionLinkGenerator` |
| ⑧ | 応答のリッチ化（テーブル・カード） | ★☆☆☆☆ | 中 | `ChatResponse` / Blade / Alpine.js |
| ⑨ | FAQ 未回答のクラスタリング自動提案 | ★★★★☆ | 中 | `AnalyzeChatFaqMisses` / Filament |

---

# ① 同義語辞書の DB 化・管理画面化

## 目的
`config/chatbot.php` の `'synonyms'`（現行 7 組のみ）を施設職員自身が追加・編集できるようにし、施設ごとの言い回し（方言・専門用語）に対応する。

## 現状の問題
- 同義語はコード固定のため、運用中に追加するにはデプロイが必要
- 施設間で用語が異なる場合に対応不可（マルチテナント制約）

## 実装内容

### Step 1-1: マイグレーション
```php
// database/migrations/2026_10_13_XXXXXX_create_chatbot_synonyms_table.php
Schema::create('chatbot_synonyms', function (Blueprint $table) {
    $table->id();
    $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete(); // null = 全施設共通
    $table->string('from_word', 100);   // 入力されやすい語（例: 引き落とし）
    $table->string('to_word', 100);     // 正規化後の語（例: 口座振替）
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->unique(['facility_id', 'from_word']);
    $table->index(['facility_id', 'is_active']);
});
```

### Step 1-2: `ChatbotSynonym` モデル + `SynonymExpander` 改修
- `SynonymExpander::fromDatabase(?int $facilityId): self` ファクトリを追加
- DB から `(facility_id IS NULL OR facility_id = ?) AND is_active = true` で取得
- **Cache: `chatbot_synonyms:{facility_id ?? 'global'}` で 60 分キャッシュ**。FAQ 保存・同義語保存時に `Cache::forget` する Observer を追加
- config のシノニムとマージ（config 側が優先）し、最長一致順ソートは現行ロジックを維持

### Step 1-3: Filament 管理画面 `ChatbotSynonymResource`
- `ChatbotFaqResource` をパターンに複製（from_word / to_word / is_active / facility スコープ）
- 同義語テストプレビュー欄: 入力文に対する変換結果をリアルタイム表示（Livewire アクション、DB 読み取りのみ）

### Step 1-4: テスト
- `tests/Unit/Chatbot/SynonymDbTest.php`: DB 読み込み・キャッシュ無効化・config マージ
- `tests/Feature/Chatbot/SynonymCrudTest.php`: Filament CRUD + facility スコープ分離

## 互換性
- `SynonymExpander` のコンストラクタシグネチャは変更しない（`$synonyms` 配列引数を維持、ファクトリで DB 結果を注入）

---

# ⑤ マルチターン文脈の拡張

## 目的
「佐藤さんの請求額は？」「先月は？」「未払いは？」のような連続質問で、名前の再入力を不要にする。現状は入居者 ID のみ文脈保持。

## 現状の問題
- [`ChatbotService.php:88`](../app/Services/Chatbot/ChatbotService.php) で `chatbot_context:{session_id}:resident_id` のみ保存
- 「先月は？」は名前が無いため FAQ にフォールバックし無関係な回答を返す
- 直前の照会種別・年月が失われる

## 実装内容

### Step 5-1: 文脈ストア `ChatbotSessionContext` の新設
```php
// app/Services/Chatbot/ChatbotSessionContext.php
final class ChatbotSessionContext
{
    private const TTL_MINUTES = 15;

    public static function get(string $sessionId, string $key): mixed;
    public static function put(string $sessionId, string $key, mixed $value): void;
    // キー: resident_id / last_intent / last_year_month / last_data
}
```
- 既存の直接 `Cache::get/put` 呼び出し（`ChatbotService` 内 3 箇所）を本クラス経由に置換（動作は同一）

### Step 5-2: `IntentRecognizer` に主語省略検知を追加
- `IntentDTO` に `bool $elliptical` フラグを追加（後方互換のためデフォルト `false`）
- 判定条件: メッセージに `name`/`room` エンティティが無く、かつ以下のいずれかにマッチ
  - `/^(?:先々月|先月|今月|当月|\d{4}[\/\-年]\d{1,2}月?|\d{1,2}月)は?[？?。.!！]*$/u`（年月だけの質問）
  - `/^(?:未払い|支払い状況|請求額|利用料|自費)(?:は|について)?[？?。.!！]*$/u`（照会語だけの質問）
- この場合 intent は `invoice_amount`/`invoice_status`/`daily_charge_total` のいずれかを **文脈から推定**:
  - `last_intent` が照会系ならそれを引き継ぐ
  - なければメッセージ内の照会語（請求額→invoice_amount 等）で判定

### Step 5-3: `ChatbotService::handle()` の文脈マージ
```php
$intent = $this->intentRecognizer->recognize($request->message);
// 文脈マージ: 名前が無く文脈に入居者 ID がある場合のみ補完（既存 resolveResident の Cache フォールバックを整理）
$yearMonth = $intent->entities['year_month'] ?? null;
if ($yearMonth === null) {
    $intent = $intent->withEntity('year_month', ChatbotSessionContext::get($request->sessionId, 'last_year_month'));
}
```
- 各ハンドラ成功時に `last_intent` / `last_year_month` / `resident_id` を保存
- **失敗時（該当データなし・入居者未特定）は `last_intent` を上書きしない**（誤文脈連鎖防止）

### Step 5-4: テスト
- `tests/Feature/Chatbot/MultiTurnContextTest.php`:
  1. 「田中さんの今月の請求額」→ 成功
  2. 「先月は？」→ 前ターンの入居者・invoice_amount を引き継ぎ先月分を回答
  3. 「未払いは？」→ 同一入居者の invoice_status を回答
  4. 文脈なしで「先月は？」→ 従来どおり FAQ フォールバック

## 互換性
- `ChatResponse` / API 形式は無変更。単一ターン利用時は現行動作と完全同一

---

# ④ 入居者名の正規化索引 + 前方一致優先

## 目的
「さとう」→「佐藤」などの表記ゆれを高速かつ高精度に解決する。レーベンシュタイン全件走査をフォールバックに格下げ。

## 現状の問題
- [`ResidentQueryService`](../app/Services/Chatbot/ResidentQueryService.php) は候補抽出に正規表現スコア + `FuzzyMatcher::similarity`（O(名前長×候補数)）
- ひらがな入力は `normalizeKana` 依存で、DB 側に索引が無い
- 旧姓・通称・別表記に対応不可

## 実装内容

### Step 4-1: マイグレーション（既存 2026_10_10_151000 を拡張）
```php
Schema::table('residents', function (Blueprint $table) {
    $table->string('nickname', 100)->nullable();               // 通称・旧姓
    $table->index(['facility_id', 'name_normalized']);        // 前方一致用
    $table->index(['facility_id', 'kana_normalized']);        // カナ前方一致用
});
```

### Step 4-2: `ResidentObserver` で正規化カラム自動更新
- 保存時に `FuzzyMatcher::normalizeKana()` を `name` / `kana` / `nickname` に適用し `*_normalized` に書き込み（既存 migration の移行値と同じ変換関数を使い一貫性を担保）

### Step 4-3: `ResidentQueryService::findCandidates` の 3 段階検索
1. **完全一致**: `name_normalized = 入力正規化` → score 1.0
2. **前方一致**: `name_normalized LIKE '入力%'` または `kana_normalized LIKE '入力%'` → score 0.9
3. **部分一致**: `LIKE '%入力%'` → score 0.85
4. **フォールバック**: 上記不成立時のみ現行の fuzzy スコアリング（レーベンシュタイン）を実行
- 検索対象に `nickname` を追加（`OR` 結合、facility スコープ維持）

### Step 4-4: バックフィルコマンド
- `php artisan residents:rebuild-normalized` を新設（既存 migration 移行と同じ変換で全行再計算・チャンク処理）

### Step 4-5: テスト
- `tests/Feature/Chatbot/ResidentNormalizedLookupTest.php`: ひらがな入力 / カタカナ入力 / 通称 / 前方一致 / フォールバック
- `tests/Unit/ResidentObserverNormalizedTest.php`: 保存時の正規化反映

## 互換性
- 戻り値の `['resident' => ..., 'score' => ...]` Collection 形式は維持

---

# ② 意図認識のスコアリング方式化

## 目的
正規表現「最初にマッチしたもの勝ち」を廃し、複数 intent が競合する複合質問（「佐藤さんの未払いの請求額」）でも最適な intent を選択する。

## 現状の問題
- [`IntentRecognizer::recognize()`](../app/Services/Chatbot/IntentRecognizer.php) は config 順に最初のマッチで確定
- 複合質問の優先順位が config の並び順依存で不定

## 実装内容

### Step 2-1: config への重み定義追加
```php
// config/chatbot.php の各 intent に weights を追加（既存 patterns は無変更・後方互換）
'invoice_amount' => [
    'patterns' => [ /* 現行維持 */ ],
    'weights'  => [10, 8, 9, 5],  // patterns 配列のインデックスに対応
],
```
- `weights` が無い intent は全パターン一律 5 として扱う（旧 config でも動作）

### Step 2-2: `IntentRecognizer` 改修
- 全 intent × 全 pattern をスキャンし `{intent, weight, entityCount}` を収集
- 合計スコア = Σ weight + エンティティ数 × 2
- 最高スコア intent を採用。**同点時は config 定義順（現行動作）でタイブレーク**
- `IntentDTO` に `array $matchedIntents`（デバッグ・ログ用）を追加

### Step 2-3: エスカレーション特別扱い
- `escalate` パターン（「担当者」「サポート」）は明確な依頼のため、競合時でも +3 のブーストを適用

### Step 2-4: テスト
- `tests/Unit/Chatbot/IntentScoringTest.php`:
  - 「佐藤さんの未払いの請求額」→ invoice_status（滞納→未払いの同義語展開とスコア競合確認）
  - 「今月の請求額」→ invoice_amount（年月 + 照会語で高スコア）
  - 旧 config（weights なし）での回帰 = 現行テスト全パス

## 互換性
- 単一マッチ時は挙動不変。`recognize()` の返却型 `IntentDTO` は拡張のみ

---

# ⑥ 不明確入力時の候補確認質問

## 目的
「見つかりませんでした」で終わらせず、ワンクリックで解決できる導線を常設する。

## 現状の問題
- 入居者未特定（[`ChatbotService.php:60`](../app/Services/Chatbot/ChatbotService.php)）→ 文面のみで終了
- FAQ 未ヒット（`ChatbotService.php:317`）→ 定型文のみで終了
- `quick_reply_categories` はあるが失敗時に活用されていない場面がある

## 実装内容

### Step 6-1: 入居者照会失敗時の候補提示
- `findCandidates` が空 or 閾値未満でも、**同 facility の在籍者（status=active）を name/kana 順で上位 5 名** を取得
- `ChatResponse` の `quickReplies` に `['田中 太郎さんに質問' => '田中 太郎の請求額を教えてください', ...]` 形式で設定
- reply 文面: 「〜に一致する入居者は見つかりませんでした。こちらの中にお探しの方はいますか？」

### Step 6-2: FAQ 未ヒット時の「もしかして」提示
- `FaqResponder::search` は現行閾値（0.35）でヒット無し → **閾値を 0.2 に下げて再検索**し上位 2 件を `sources` + quickReplies に「もしかして:」で提示
- それでも無ければ `categoryQuickReplies`（config `chatbot.quick_reply_categories`）を必ず添付（既存 `handleFaq` は対応済みのため、`handleFallback` 経路でも同じ構造を統一）

### Step 6-3: 複数候補時の選択 UI 連携
- `handleResidentLookup` の複数候補応答（`data.candidates`）にも `quickReplies` を付与し、フロントでボタン化（Blade 側は既存クイックリプライ描画を流用）

### Step 6-4: テスト
- `tests/Feature/Chatbot/ClarificationFlowTest.php`:
  - 見つからない名前 → 在籍者 5 名の quickReplies が返る
  - FAQ 未ヒット → 低閾値再検索の候補 or カテゴリクイックリプライが返る
  - 既存成功パスの回帰（現行テスト全パス）

## 互換性
- `ChatResponse` の既存フィールドのみ使用。API 契約変更なし

---

# ③ FAQ キーワードの IDF 重み付け

## 目的
「請求」等の汎用語より「領収書」「口座振替」等の特徴語を重視した FAQ マッチングに改善する。

## 現状の問題
- [`FaqResponder::score()`](../app/Services/Chatbot/FaqResponder.php) は包含カウントのみで、汎用語がスコアを独占
- 誤マッチ（汎用語だけが一致した FAQ が上位）の原因

## 実装内容

### Step 3-1: キーワード DF テーブル
```php
// database/migrations/2026_10_13_XXXXXX_create_chatbot_keyword_stats_table.php
Schema::create('chatbot_keyword_stats', function (Blueprint $table) {
    $table->id();
    $table->string('keyword', 64)->unique();
    $table->unsignedInteger('document_frequency')->default(0); // FAQ 質問文における出現 FAQ 数
    $table->timestamps();
});
```

### Step 3-2: 深夜バッチコマンド
- `php artisan chatbot:rebuild-keyword-stats` を新設
- 全 active FAQ の `question` + `keywords` から `FaqResponder::extractKeywords()` と同じ抽出ロジックで分かち書きし、DF を再計算（全削除→再挿入、トランザクション内）
- `routes/console.php` に `Schedule::command('chatbot:rebuild-keyword-stats')->dailyAt('02:30')` を追加

### Step 3-3: `FaqResponder::score()` の改修
```php
$idf = log((1 + $totalFaqCount) / (1 + $df)) + 1;  // スムージング済み IDF
$score += (int) round($idf * 2);                    // 包含マッチ 1 回あたりの重み
```
- `chatbot_keyword_stats` を 60 分 Cache。テーブルが空（初回・バッチ未実行）の場合は **現行のカウント方式にフォールバック**（段階的導入）

### Step 3-4: テスト
- `tests/Feature/Chatbot/FaqIdfScoringTest.php`:
  - 汎用語のみの質問より特徴語を含む質問が正しい FAQ にマッチ
  - stats テーブル空時のフォールバック回帰

## 互換性
- `search()` の返却型（FAQ Collection）は不変。閾値判定は維持

---

# ⑦ 照会対象の拡充（5〜8 intent）

## 目的
照会可能なデータ種を 4 → 8 に増やし、チャットボットを実業務に直結させる。

## 追加 intent 一覧

| intent | 質問例 | データソース | 実装負荷 |
|:---|:---|:---|:---|
| `unpaid_list` | 「未払いの入居者はいますか」 | `MonthlyInvoice` where payment_status | 低 |
| `billing_summary` | 「今月の売上・未回収合計は」 | `MonthlyInvoice` 集計（または closing_states） | 中 |
| `invoice_pdf` | 「田中さんの請求書 PDF ちょうだい」 | `ChatActionLinkGenerator::invoicePdf()` | 低 |
| `daily_charge_detail` | 「田中さんの今月の自費の内訳」 | `DailyCharge` 項目別一覧 | 中 |
| `service_invoice` | 「介護保険の請求状況は」 | `ServiceInvoice` | 中 |

## 実装内容

### Step 7-1: `unpaid_list`（最優先）
- config intents に `'/未払いの一?覧/u', '/未払いの入居者/u'` を追加
- ハンドラ: `MonthlyInvoice::where('facility_id', $user->facility_id)->whereYear/Month(...)->unpaid()` を name/room 付きで取得（limit 10 + 「他 N 件」）
- 応答は ⑧ のリスト形式を併用

### Step 7-2: `invoice_pdf`
- [`ChatActionLinkGenerator`](../app/Services/Chatbot/ChatActionLinkGenerator.php) に `invoicePdf(int $invoiceId): array` を追加（既存 `residentEdit` と同じ `{label, url, type}` 形式）
- `handleInvoiceAmount` 成功時の `actionLinks` に PDF リンクを自動付加（質問しなくても答える設計）

### Step 7-3: `daily_charge_detail`
- `DailyCharge` を resident + year-month で取得、charge_item 名ごとの合計一覧
- 5 件超は「上位 5 項目 + 合計」表示

### Step 7-4: `billing_summary` / `service_invoice`
- `billing_summary`: 月次の請求合計・入金済合計・未回収合計を 1 クエリ集計（`monthly_invoices` の facility_id + year_month 複合インデックス活用、既存 index 追加 migration あり）
- `service_invoice`: `ServiceInvoice` の status/金額照会（`handleInvoiceStatus` のパターンを流用）

### Step 7-5: クイックリプライ・サジェスト更新
- `config/chatbot.php` の `quick_replies` / `suggestions` / `quick_reply_categories` に新 intent の質問例を追加

### Step 7-6: テスト
- `tests/Feature/Chatbot/NewIntentsTest.php`: 各 intent の正常系・facility スコープ分離・権限（他施設データ返却しない）・データ 0 件時の応答

## 互換性
- 新 intent は追加のみで既存 intent の挙動・応答形式を変更しない

---

# ⑧ 応答のリッチ化（テーブル・カード表示）

## 目的
一覧・複数件応答を構造化表示にし、職員の読み取り時間を短縮する。

## 実装内容

### Step 8-1: `ChatResponse` DTO 拡張
```php
public readonly string $displayType = 'text';  // 'text' | 'list' | 'table' | 'candidates'
public readonly array  $columns = [];          // table 用ヘッダ
```
- `toArray()` に `display_type`, `columns` を追加（既存キーは無変更・純追記）

### Step 8-2: サーバー側の適用箇所
| 応答 | displayType |
|:---|:---|
| `unpaid_list` / `daily_charge_detail`（⑦） | `table` |
| 入居者複数候補 / ⑥候補提示 | `candidates` |
| FAQ「もしかして」 | `list` |
| 既存単一照会 | `text`（デフォルト・無変更） |

### Step 8-3: Blade / Alpine.js 描画分岐
- `resources/views/filament/pages/chatbot-assistant.blade.php` のメッセージ描画部に `x-show="msg.display_type === ..."` 分岐を追加
- `table`: 単純な HTML table + 横スクロール（モバイル対応）
- `candidates`: ボタンカード（クリックで quickReplies 送信と同じ挙動）
- スタイリングは既存デザインシステムに合わせ `list` 表示

### Step 8-4: 公開サイトチャットへの展開（任意）
- `PublicChatResponse` も同構造に追記し、`website` 埋め込み widget に同じ分岐を追加

### Step 8-5: テスト
- `tests/Feature/Chatbot/DisplayTypeTest.php`: 各 intent で期待する display_type / columns が返る
- フロントは手動検証 + `scripts/` のスクリーンショット取得（既存 gif 生成スクリプトのパターン流用可）

## 互換性
- 旧フロント（display_type 非対応）は `reply` テキストのみ表示で正常動作（graceful degradation）

---

# ⑨ FAQ 未回答のクラスタリング自動提案

## 目的
FAQ に答えられなかった質問を夜間バッチでクラスタリングし、新規 FAQ 候補として管理者に提示して FAQ を自動的に育てる。

## 現状の問題
- [`AnalyzeChatFaqMisses`](../app/Console/Commands/AnalyzeChatFaqMisses.php) は集計ランキングのみで、FAQ 追加まで人手
- 未回答質問の「同じ内容の集まり」が見えない

## 実装内容

### Step 9-1: クラスタリングロジック（LLM 不使用）
- 未ヒット `ChatLog`（`intent='faq'` かつ `faq_matched=false`、過去 N 日）を取得
- 貪欲（greedy）クラスタリング: 各ログの `BigramTokenizer` ベクトルと既存クラスタ代表の類似度 ≥ 0.5 なら同一クラスタに帰属、なければ新規クラスタ
- クラスタごとに: 代表文（クラスタ内で最も長い文）/ 件数 / 最終発生時刻を集計

### Step 9-2: FAQ 候補テーブル
```php
Schema::create('chatbot_faq_suggestions', function (Blueprint $table) {
    $table->id();
    $table->unsignedInteger('cluster_key')->unique(); // 代表 log id など
    $table->text('representative_message');
    $table->unsignedInteger('occurrence_count');
    $table->string('status', 20)->default('pending'); // pending | approved | dismissed
    $table->foreignId('approved_faq_id')->nullable()->constrained('chatbot_faqs')->nullOnDelete();
    $table->timestamps();
});
```

### Step 9-3: 夜間バッチ拡張
- `chatbot:analyze-faq-misses` に `--suggest` オプションを追加: クラスタリング実行 → `occurrence_count >= 2` のクラスタを suggestions に upsert
- `Schedule` で毎日 03:00 実行（既存 `retention_days` クリーンアップの後）

### Step 9-4: Filament 管理画面
- `ChatbotFaqSuggestionResource`（一覧: 代表文 / 件数 / 状態）
- 「承認して FAQ 作成」アクション: `ChatbotFaqResource::Create` へ代表文を question 初期値としてリダイレクト
- 「却下」アクション: status を dismissed に更新（同一クラスタの再提案抑止）
- 既存 [`ChatbotFaqMissStatsWidget`](../app/Filament/Widgets/ChatbotFaqMissStatsWidget.php) に「承認待ち候補 N 件」バッジを追加

### Step 9-5: テスト
- `tests/Feature/Chatbot/FaqSuggestionClusteringTest.php`:
  - 類似文 3 件 → 1 クラスタ、occurrence_count=3
  - 異文は別クラスタ
  - 承認フローで FAQ が作成され status が approved になる
  - バッチの冪等性（再実行で重複 upsert されない）

## 互換性
- 既存 `chatbot:analyze-faq-misses` の出力は維持（オプション追記のみ）

---

## 総合スケジュール案（4 週間）

| 週 | 実装内容 | 完了条件 |
|:---|:---|:---|
| W1 | ① 同義語 DB 化、⑤ マルチターン文脈 | 単体・機能テスト緑、既存 665+ テスト回帰緑 |
| W2 | ④ 名前正規化索引、② 意図スコアリング | 同上 + パフォーマンス確認（名前検索 < 50ms） |
| W3 | ⑥ 候補確認質問、⑦ 照会拡充 | 同上 + 新 intent の facility 分離テスト緑 |
| W4 | ③ IDF、⑧ リッチ化、⑨ クラスタリング、総合回帰 | 全テスト緑 + 手動 UX 検証 + バッチ本番登録 |

## 検証方針
- 各 Phase 完了時に `php artisan test` 全実行（現行テストの回帰を最優先）
- バッチ系（③⑨）は `php artisan schedule:test` による手動実行検証を含む
- ⑧ は表示確認を `scripts/render_pdf_to_images.py` のパターンを応用したスクリーンショットで担保（任意）
