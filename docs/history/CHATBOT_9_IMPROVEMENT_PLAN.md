# チャットボット発展改善 詳細実装計画書（サーバー低負荷順・全36ステップ）

**作成日**: 2026-10-10  
**対象**: チャットボットの利便性・正答率・業務直結性の高度化（改善案1〜9）  
**期間目安**: 2026-11-01 〜 2026-11-30（約4週間）  
**テスト方針**: 各改善テーマごとに単体・機能テストを作成し、最終ステップで総合回帰テスト（全665テスト超の継続通過）を実施  
**設計原則**: 
1. サーバー負荷の極小化（クライアント完結 → PHP内軽量変換 → インデックス活用DB → 深夜バッチ）
2. 厳格なマルチテナント・施設スコープ（`facility_id`）分離の堅持
3. 後方互換性の完全担保（既存のAPI形式・パラメータ・応答契約を一切壊さない）

---

## 改善テーマ一覧（サーバー負荷が少ない順）

| Phase | テーマ | サーバー負荷 | 対象コンポーネント |
|:---|:---|:---:|:---|
| **Phase 1** | ① クライアント側入力候補サジェスト（オートコンプリート） | ★☆☆☆☆ (ゼロ) | Blade / Alpine.js / HTML5 Datalist |
| **Phase 2** | ② 階層型クイックリプライ・定型メニュー | ★☆☆☆☆ (ゼロ) | config / ChatResponse / Blade |
| **Phase 3** | ③ 同義語（シノニム）辞書の組み込み | ★☆☆☆☆ (極小) | config / SynonymExpander / IntentRecognizer |
| **Phase 4** | ④ 直前文脈のセッション保持（2ターン対話機能） | ★★☆☆☆ (極小) | SessionCache / ChatbotService / Context |
| **Phase 5** | ⑤ 職員ワンクリック回答フィードバック（Good/Bad） | ★★☆☆☆ (小) | Migration / ChatLog / API / Blade |
| **Phase 6** | ⑥ Filament管理画面へのディープリンク連携 | ★★☆☆☆ (小) | ChatActionLinkGenerator / ChatResponse |
| **Phase 7** | ⑦ 入居者名・フリガナの事前正規化とインデックス化 | ★★★☆☆ (小/負荷減) | Migration / ResidentObserver / FuzzyMatcher |
| **Phase 8** | ⑧ FAQあいまい検索の「Bi-gram一致度」拡張 | ★★★☆☆ (中) | BigramTokenizer / FaqResponder |
| **Phase 9** | ⑨ 未回答質問の日次オフピーク自動クラスタリング | ★★★★☆ (中/深夜) | AnalyzeChatFaqMisses / Widget / 総合回帰 |

---

## 詳細ステップ一覧（1 〜 36）

### Phase 1: クライアント側入力候補サジェスト（オートコンプリート）
サーバーとの無駄な通信を発生させず、ブラウザ側のみで定型入力候補を提示して職員の誤字・迷いをゼロにします。

* **Step 1**: 入力サジェスト辞書データ設計
  - `chatbot-assistant.blade.php` 内の Alpine.js に定型フレーズ辞書（「〜の請求額」「〜の支払い状況」「〜の利用料」「エスカレーション」「振替日について」等）を静的配置。
* **Step 2**: HTML5 `<datalist>` およびカスタムサジェストUIの実装
  - 入力欄に `<datalist id="chat-suggestions">` を関連付け、キーボード操作（上下矢印での選択・確定）およびアクセシビリティ（`aria-autocomplete="list"`）に対応。
* **Step 3**: リアルタイム前方一致/部分一致フィルタリングロジックの実装
  - Alpine.js で `input` イベントを監視し、1文字以上入力時にマッチする候補を即座にポップオーバー表示する双方向バインディングを実装。
* **Step 4**: 【テスト/検証】フロントエンド動作検証 & API通信への無影響テスト
  - `tests/Feature/Chatbot/ChatbotApiTest.php` を実行し、サジェスト経由の送信でも従来の直接入力と同様に同一のJSONレスポンス（`reply`, `quick_replies`）が返ることを確認。

---

### Phase 2: 階層型クイックリプライ・アコーディオン型定型メニュー
フラットに並んでいた選択ボタンを「請求関連」「入居者照会」「操作方法」などのカテゴリ単位に分類し、視認性を向上させます。

* **Step 5**: `config/chatbot.php` の `quick_replies` 構造拡張
  - 従来のフラットな配列に加え、`categories` キーを持つ階層化設定（`'請求・入金' => [...]`, `'入居者・契約' => [...]`）を追加定義。
* **Step 6**: `ChatResponse` DTO のカテゴリ構造サポート
  - `ChatResponse.php` に `categoryQuickReplies` プロパティを追加。従来のフラット配列 `quickReplies` もそのまま維持して後方互換性を完全保証。
* **Step 7**: Blade / Alpine.js でのアコーディオン・タブUI実装
  - `chatbot-assistant.blade.php` の初期画面およびボット応答部に、カテゴリ別タブボタンとアコーディオン展開によるクイックリプライ選択UIを構築。
* **Step 8**: 【テスト/回帰】`QuickReplyTest.php` 拡張
  - `tests/Feature/Chatbot/QuickReplyTest.php` に階層型クイックリプライの返却テスト、および従来のフラット形式設定時でもエラーなく動作する互換性回帰テストを追加。

---

### Phase 3: 同義語（シノニム）辞書の組み込み
施設職員ごとに異なる表現（「口座引き落とし」「レセプト」「未払い」等）を、サーバー負荷ほぼゼロ（PHP配列置換）で正規表現にヒットさせます。

* **Step 9**: `config/chatbot.php` にシノニムマップ定義の追加
  - `'synonyms'` 設定（例: `'引き落とし' => '口座振替'`, `'未払い' => '未入金'`, `'レセプト' => '請求書'`, `'自費分' => '月額利用料'`）を追加。
* **Step 10**: `app/Services/Chatbot/SynonymExpander.php` の新設
  - 設定されたシノニム辞書に基づき、最長一致順で安全に入力テキストを正規化するサービスクラスを作成。
* **Step 11**: `IntentRecognizer` へのシノニム展開前処理の統合
  - `IntentRecognizer.detect()` の正規表現マッチング実行直前に `SynonymExpander.expand($message)` を通過させるパイプラインを構築。
* **Step 12**: 【テスト/回帰】`SynonymExpanderTest.php` 新設 & 回帰テスト
  - `tests/Unit/Chatbot/SynonymExpanderTest.php` を作成（単語置換・部分一致巻き込み防止・未登録語の無変更検証）。既存の `IntentRecognizerTest.php` が全てパスすることを確認。

---

### Phase 4: 直前文脈のセッション保持（2ターン対話機能）
「佐藤さんの今月の請求は？」「先月は？」「未払いはある？」といった連続質問に対し、入居者名を毎回入力し直す手間を省きます。

* **Step 13**: セッション文脈ストア（`ChatbotSessionContext`）の設計
  - 既存の `session_id` に紐づくキャッシュ（Laravel Cacheストア、有効期限15分）に `last_resident_id`, `facility_id` を軽量保存する仕組みを設計。
* **Step 14**: `IntentRecognizer` の主語省略検知ロジック追加
  - メッセージ内に人物名が含まれず、かつ期間（「先月は」「8月は」）や照会意図（「支払い状況は」「利用料は」）のみが含まれるパターンを認識。
* **Step 15**: `ChatbotService` での文脈引き継ぎと他施設分離ガードの実装
  - 直前の入居者が同一施設（`facility_id === current_user.facility_id`）に所属していることを厳格に検証した上で、前回の入居者インスタンスを再利用。
* **Step 16**: 【テスト/回帰】`ChatbotContextTest.php` 新設
  - `tests/Feature/Chatbot/ChatbotContextTest.php` を作成:
    1. 1ターン目で入居者を特定後、2ターン目の「先月は？」で同一入居者の過去月請求が返ること
    2. 別の入居者名が明示された場合は文脈が正しく上書きされること
    3. 他施設の入居者コンテキストが絶対に流用されないこと

---

### Phase 5: 職員ワンクリック回答フィードバック（Good/Bad）
ボット回答の有用性を職員がその場で評価できる仕組みを導入し、エスカレーションに至らない不満や改善点を最小負荷で記録します。

* **Step 17**: `chat_logs` テーブルへのフィードバックカラム追加マイグレーション
  - `add_feedback_to_chat_logs_table.php` を作成（`feedback` tinyint/enum['helpful', 'unhelpful'], `feedback_at` timestamp を nullable で追加）。
* **Step 18**: フィードバック受信用 API エンドポイントの実装
  - `POST /chatbot/feedback` ルートおよび `ChatbotController.feedback` アクションを追加。ログインユーザーの `facility_id` とログの整合性を検証して1行更新。
* **Step 19**: Blade / Alpine.js での評価アイコン・送信UI実装
  - ボット回答吹き出しの右下に 👍 / 👎 ボタンを配置。クリック時に非同期送信し、押下済み状態（「フィードバックありがとうございます」）へ切り替え。
* **Step 20**: 【テスト/回帰】`ChatFeedbackTest.php` 新設
  - `tests/Feature/Chatbot/ChatFeedbackTest.php` を作成（正当な評価記録、同一ログへの二重送信ガード、他施設ログへの不正アクセス拒絶をテスト）。

---

### Phase 6: Filament管理画面へのディープリンク（ワンクリック画面遷移）
ボットで金額や状況を確認した後、すぐに請求書編集や入金登録画面へジャンプできるリンクを提供し、画面を探し直す手間をゼロにします。

* **Step 21**: `app/Services/Chatbot/ChatActionLinkGenerator.php` の新設
  - 対象の入居者IDや請求書IDから、FilamentリソースURL（`/admin/invoices/{id}/edit` や `/admin/residents/{id}`）を安全に組み立てるヘルパーを作成。
* **Step 22**: `ChatResponse` DTO の `actionLinks` プロパティ拡張
  - `ChatResponse.php` に `actionLinks: array<int, array{label: string, url: string, color: string}>` を追加し、JSONシリアライズ対応。
* **Step 23**: `ResidentQueryService` へのリンク生成組み込み & Blade描画
  - 請求照会結果に「請求書詳細を開く」、入居者照会結果に「入居者台帳を開く」リンクを付加し、Blade上でスタイリッシュなボタンスタイルで表示。
* **Step 24**: 【テスト/回帰】`ChatActionLinkTest.php` 新設
  - `tests/Feature/Chatbot/ChatActionLinkTest.php` を作成（生成URLの形式検証、該当データなし時のリンク非表示、リンク非対応クライアントへの後方互換性テスト）。

---

### Phase 7: 入居者名・フリガナの事前正規化とインデックス化
あいまい検索（FuzzyMatcher）によるPHP全件メモリ走査を解消し、データベースのインデックスで高速絞り込みを行えるように最適化します。

* **Step 25**: `residents` テーブルへの正規化カラム追加マイグレーション
  - `normalized_name`, `normalized_kana` カラム（小文字・ひらがな・長音・清音化済みテキスト）を追加し、インデックスを設定。
* **Step 26**: `Resident` モデルの自動正規化 Observer / Mutator 実装
  - 入居者の登録・更新時に、自動でフリガナを正規化して上記カラムに保存するイベントハンドラを実装。
* **Step 27**: `FuzzyMatcher` のクエリ事前フィルタリング最適化
  - 施設内全件を取得するのではなく、正規化カラムに対して前方・部分一致する上位候補（最大20件程度）に絞り込んでからレーベンシュタイン距離を計算するように改善。
* **Step 28**: 【テスト/回帰】`ResidentNormalizedSearchTest.php` 新設 & 回帰検証
  - カナ・誤字検索が従来通り（あるいはそれ以上の精度で）ヒットし、かつクエリ実行回数・メモリ消費が大幅に低減されていることを検証。

---

### Phase 8: FAQあいまい検索の「Bi-gram（2文字組）一致度」拡張
単語の順序や助詞の違い（「請求書の再発行」と「再発行 請求書」）によるFAQ検索漏れを、形態素解析なし（純粋なPHP軽量処理）で救済します。

* **Step 29**: `app/Services/Chatbot/BigramTokenizer.php` の新設
  - 文字列を2文字ごとのN-gram配列へ分解する軽量ユーティリティを作成（例: "請求書" → `["請求", "求書"]`）。
* **Step 30**: `FaqResponder` への Bi-gram Jaccard類似度計算の組み込み
  - 質問文とFAQタイトル・質問例との Bi-gram 重複率を算出し、スコアリングするマッチングエンジンを実装。
* **Step 31**: `config/chatbot.php` に Bi-gram マッチング閾値の設定追加
  - `'faq.bigram_threshold' => 0.4` を定義し、既存の完全一致・前方一致を優先したフォールバックとして動作する優先度順序を確定。
* **Step 32**: 【テスト/回帰】`FaqBigramTest.php` 新設 & 既存FAQテスト回帰
  - `tests/Unit/Chatbot/FaqBigramTest.php` を作成（助詞違い・語順入れ替えでのヒット検証）。既存の `FaqResponderTest.php` の完全一致が最優先されることを再確認。

---

### Phase 9: 未回答質問の日次オフピーク自動クラスタリングバッチ
日中のオンライン処理には一切負荷をかけず、深夜バッチで未回答ログを自動グルーピングしてFAQ改善の優先度を可視化します。

* **Step 33**: 未回答質問の類似クラスタリングアルゴリズム設計
  - 過去7日間の未回答 `chat_logs` から、Bi-gram類似度の高い質問同士をグラフ連結成分または貪欲法でクラスタリングするロジックを設計。
* **Step 34**: コマンド `AnalyzeChatFaqMisses` のクラスタリング機能拡張
  - `php artisan chatbot:analyze-misses` 実行時に、頻出クラスタ上位5件（代表質問文と件数）を集計結果テーブルに保存。
* **Step 35**: Filamentウィジェット `ChatbotFaqMissStatsWidget` の拡張
  - ダッシュボード上に「未登録の頻出質問グループ」として表示し、管理者がワンクリックで新規FAQ登録画面にテキストを転記できる動線を構築。
* **Step 36**: 【総合リグレッションテスト】総合回帰テストスイートの実行 & 完了検証
  - `tests/Feature/Regression/ChatbotAdvancedRegressionTest.php` を新設し、Phase 1〜9の全機能が協調動作すること、および既存全665テストが100%通過することを確認。

---

## リグレッション防止マトリクス

| 既存機能 | 影響を受ける可能性のあるフェーズ | リグレッション防止策・ガード |
|:---|:---|:------|
| **施設スコープの強制** | Phase 4 (文脈保持), Phase 5 (評価), Phase 6 (リンク) | セッションIDやリンク生成時にも必ず `auth()->user()->facility_id` を直接参照・再検証し、クロス施設アクセスを物理遮断。 |
| **PII マスキング** | Phase 5 (評価保存), Phase 9 (未回答分析) | ログ保存時およびクラスタリング集計時に入居者名正規表現による伏字化（`mask_names`）をバイパスさせない。 |
| **API 応答フォーマット** | Phase 1 (サジェスト), Phase 2 (クイック), Phase 6 (リンク) | レスポンスJSONの最上位キー（`reply`, `quick_replies`）はそのまま維持し、新キーはオプショナルとして追加。 |
| **検索パフォーマンス** | Phase 7 (正規化検索), Phase 8 (Bi-gram) | メモリ上限・実行時間制限（最大スキャン件数制限）を設け、意図しない全表走査やループ爆発をガード。 |
