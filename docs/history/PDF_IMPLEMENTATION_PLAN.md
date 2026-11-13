# PDF生成システム改善 詳細実装計画書

## 目的
1. 銀行振込用QRコードデータのバグ修正（重要）
2. PDF二重生成によるパフォーマンス問題の解決
3. マージン・用紙サイズ設定の統一
4. 保守性と柔軟性の向上
5. リグレッション防止のためのテスト作成

## 対象ファイル
- `app/Services/InvoicePdfService.php`
- `resources/views/invoices/pdf.blade.php`
- `resources/views/invoices/receipt.blade.php`
- `resources/views/invoices/partials/header.blade.php`
- `resources/views/invoices/partials/footer.blade.php`
- 新規作成: `config/pdf.php`
- 新規作成: `tests/Unit/InvoicePdfServiceTest.php`

## 実装フェーズとタスク

### フェーズ1: 重要なバグ修正とパフォーマンス改善（見積もり: 2時間）
#### タスク1-1: 銀行振込用QRコードデータ修復
- ファイル: `app/Services/InvoicePdfService.php`
- メソッド: `getBankTransferQrCodeData`
- 変更点:
  - Bladeスタイルのプレースホルダー `{{ ... }}` を実際のPHP変数に置換
  - 正しい銀行振込QRコードデータフォーマット（JPCQR準拠）を生成
- テスト: 
  - メソッド単体テストでプレースホルダーが含まれていないことを確認
  - 生成されたデータが期待されるフォーマットに一致することを確認

#### タスク1-2: PDF二重生成の解消
- ファイル: `app/Services/InvoicePdfService.php`
- メソッド: `generatePdfFromInvoice`
- 変更点:
  - QRコードデータ生成をPDFオブジェクト作成前に移動
  - テンプレート設定にQRコードデータを追加してから一度だけPDFを生成
- テスト:
  - QRコード有効/無効両方でPDFが正常に生成されることを確認
  - QRコード有効時でもPDF生成が1回しか行われないことをログまたはモックで確認

#### タスク1-3: テスト作成（リグレッション防止）
- ファイル: `tests/Unit/InvoicePdfServiceTest.php`
- 作成するテスト:
  - `testGenerateInvoicePdfWithoutQrCode`: QRコード無効時の請求書PDF生成
  - `testGenerateInvoicePdfWithQrCode`: QRコード有効時の請求書PDF生成（サイズチェックとQRコード存在確認）
  - `testGenerateReceiptPdf`: 領収書PDF生成
  - `testBankTransferQrCodeDataFormat`: 銀行振込QRコードデータにプレースホルダーが含まれていないこと
  - `testQrCodeGeneration`: QRコード生成メソッドが有効なデータURIを返すこと
  - `testMonthlyZipGeneration`: 月間ZIP生成（既存のテストスクリプトベース）

### フェーズ2: 設定統一と最適化（見積もり: 3時間）
#### タスク2-1: マージン・用紙サイズ設定統一
- ファイル: 
  - `resources/views/invoices/pdf.blade.php`
  - `resources/views/invoices/receipt.blade.php`
- 変更点:
  - `@page` ルールからマージンと用紙サイズ設定を削除
  - コメントで「マージンと用紙サイズはInvoicePdfServiceで設定」と明記
- テスト:
  - 生成されるPDFの用紙サイズがA4であることを確認（可能であれば）
  - マージンがサービス設定通りであることを確認（困難な場合は目視確認のためのサンプルPDF生成）

#### タスク2-2: QRコードサイズ最適化
- ファイル: `app/Services/InvoicePdfService.php`
- メソッド: `generatePaymentQrCode`
- 変更点:
  - QRコードサイズを200x200から70x70ピクセルに変更（24mm表示に最適）
- テスト:
  - 生成されるQRコードデータURIのサイズが期待通りであること
  - テンプレートでの表示サイズ（24mmx24mm）と一致すること

#### タスク2-3: 冗長フォント設定削除
- ファイル: `app/Services/InvoicePdfService.php`
- メソッド: `applyJapaneseFontSettings`
- 変更点:
  - `$options` 配列から `'defaultFont' => 'YuMincho'` 行を削除
  - CSSのfont-familyに依存するようにする
- テスト:
  - PDF生成時にフォント関連のエラーが発生しないこと
  - 日本語テキストが正しく表示されること（目視確認）

### フェーズ3: 設定ファイルによるカスタマイズ性向上（見積もり: 2時間）
#### タスク3-1: 設定ファイル作成
- ファイル: `config/pdf.php`
- 内容:
  - `default`: 共通設定（用紙サイズ、マージン、フォント、色など）
  - `invoice`: 請求書固有設定
  - `receipt`: 領収書固有設定
- テスト:
  - 設定ファイルが正しく読み込まれること
  - デフォルト設定がサービスに適用されること

#### タスク3-2: テンプレート設定取得メソッド更新
- ファイル: `app/Services/InvoicePdfService.php`
- メソッド: `getTemplateConfig`
- 変更点:
  - `config('pdf.default')` と `config("pdf.{$type}")` をマージ
  - 既存のベース設定をフォールバックとして残すか、完全に置き換えるかを決定
- テスト:
  - 請求書と領収書で異なる設定が適用されること
  - 設定ファイルの値が正しく反映されること

### フェーズ4: フォントロード信頼性向上（見積もり: 1時間、オプション）
#### タスク4-1: 自動フォントインストールチェック
- ファイル: `app/Services/InvoicePdfService.php`
- 新規メソッド: `ensureNotoSansJpFontsAvailable`
- 変更点:
  - フォントディレクトリの存在確認
  - 不足しているフォントがある場合は自動ダウンロードを試みる
  - 失敗した場合はログ出力とフォールバックフォントの使用
- テスト:
  - フォントが存在する場合は何もしないこと
  - フォントが存在しない場合はダウンロードを試みること（ネットワークテストはモックを使用）

## 実装順序と依存関係
1. フェーズ1（重要バグ修正）を最初に実装
   - タスク1-1と1-2は独立して実装可能
   - タスク1-3（テスト）はタスク1-1と1-2の後に実装
2. フェーズ2（設定統一）を次に実装
   - タスク2-1、2-2、2-3は独立して実装可能
3. フェーズ3（設定ファイル）を次に実装
   - タスク3-1と3-2は順番に実装
4. フェーズ4（フォント信頼性）は最後に実装（オプション）

## リグレッション防止テスト戦略
1. ユニットテスト:
   - 文字列または配列を返すメソッドに焦点を当てる
   - `getBankTransferQrCodeData`, `generatePaymentQrCode`, `getTemplateConfig` など
2. 統合テスト:
   - PDF生成メソッドをテストし、ファイルが生成されサイズが期待範囲内であることを確認
   - 可能であれば、簡単なテキスト抽出やQRコード存在確認を追加
3. 既存テストの活用:
   - `scripts/test_pdf.php` を PHPUnit テストに変換または補完
   - 既存の機能テスト（`tests/Feature/InvoicePdfServiceZipTest.php` など）を確認し、必要に応じて更新
4. 自動テスト実行:
   - CI/CD パイプラインにテストを組み込むことを推奨
   - ローカル開発時には `phpunit` コマンドでテスト実行

## リスクと mitigation
1. リスク: QRコードデータ修正により既存のQRコードスキャンが動作しなくなる
   - mitigation: テストで実際にQRコードを生成し、スキャン可能か確認する（モバイルデバイスまたはスキャンライブラリを使用）
2. リスク: 設定変更によりPDFレイアウトが崩れる
   - mitigation: 複数の請求書・領収書パターンで生成したPDFを目視確認する
3. リスク: フォントが見つからない場合のフォールバックが効かない
   - mitigation: フォント設定のフォールバックチェーンを複数用意し、最終的にsans-serifにフォールバックする

## 完了基準
1. すべての重要バグが修正されていること（特に銀行振込QRコード）
2. PDF生成が1回のみ行われていること（パフォーマンス改善）
3. マージンと用紙サイズがサービス設定に統一されていること
4. 新規テストが80%以上のカバレッジで実装されていること
5. 既存の機能がすべて動作すること（リグレッションなし）
6. 設定ファイルによるカスタマイズが可能であること

## 次のステップ
1. この計画書をレビューし、優先順位や範囲を調整する
2. フェーズ1から実装を開始する
3. 各タスク完了後にテストを実行し、リグレッションがないことを確認する
4. 完了後にデモまたはサンプルPDFの生成を行い、結果を確認する