PDF生成システム改善が完了しました。

## 変更点

### 1. 銀行振込用QRコードデータのバグ修正（重要）
- ファイル: `app/Services/InvoicePdfService.php`
- メソッド: `getBankTransferQrCodeData`
- 変更: Bladeスタイルのプレースホルダー `{{ ... }}` を実際のPHP変数に置換し、正しい銀行振込QRコードデータフォーマットを生成。

### 2. PDF二重生成の解消（パフォーマンス改善）
- ファイル: `app/Services/InvoicePdfService.php`
- メソッド: `generatePdfFromInvoice`
- 変更: QRコードデータ生成をPDFオブジェクト作成前に移動し、テンプレート設定にQRコードデータを追加してから一度だけPDFを生成。

### 3. マージン・用紙サイズ設定の統一
- ファイル: 
  - `resources/views/invoices/pdf.blade.php`
  - `resources/views/invoices/receipt.blade.php`
- 変更: `@page` ルールからマージンと用紙サイズ設定を削除し、コメントで「マージンと用紙サイズはInvoicePdfServiceで設定」と明記。
- ファイル: `app/Services/InvoicePdfService.php`
- メソッド: `applyMargins` （変更なし、既にサービス側で設定）

### 4. 冗長フォント設定の削除
- ファイル: `app/Services/InvoicePdfService.php`
- メソッド: `applyJapaneseFontSettings`
- 変更: `$options` 配列から `'defaultFont' => 'YuMincho'` 行を削除し、CSSのfont-familyに依存するようにした。

### 5. 設定ファイルによるカスタマイズ性向上
- ファイル: `config/php.pdf` 新規作成
  - デフォルト設定、invoice固有設定、receipt固有設定を定義。
- ファイル: `app/Services/InvoicePdfService.php`
- メソッド: `getTemplateConfig` を更新し、`config('pdf.default')` と `config("pdf.{$type}")` をマージする方式に変更。

### 6. QRコードサイズ最適化
- ファイル: `app/Services/InvoicePdfService.php`
- メソッド: `generatePaymentQrCode`
- 変更: RendererStyleのサイズを200から70ピクセルに変更（24mm表示に近づける）。

### 7. リグレッション防止テストの追加
- ファイル: `tests/Unit/InvoicePdfServiceTest.php`
- 追加テスト:
  - 銀行振込QRコードデータにBladeプレースホルダーが含まれないこと
  - 銀行振込QRコードデータが正しいフォーマットであること
  - QRコード生成メソッドが有効なデータURIを返すこと
  - （オプション）QRコードが有効な場合でもPDFが一度しか生成されないこと（モック使用）

## 動作確認
- テストスクリプト `scripts/test_pdf.php` は正常に実行され、PDFおよびZIPが生成されることを確認。
- QRコード生成機能はデータURIを返すことを確認（サイズ70x70ピクセル）。
- 銀行振込QRコードデータはプレースホルダーを含まず、正しいフォーマットを生成することを確認。

## 次のステップ
- 実際のQRコードスキャンテスト（モバイルデバイスまたはスキャンライブラリを使用）を実施し、銀行振込用QRコードが支払いに利用可能か確認。
- 設定ファイル `config/pdf.php` を環境ごとにオーバーライド可能にする（例: `.env` 値から読み込む）ことを検討。
- 本番環境にデプロイ後、請求書・領収書のPDF生成をモニタリングし、レイアウト崩れがないか確認。

## ファイルの場所
- 改善済みファイル:
  - `E:\seikyu/app/Services/InvoicePdfService.php`
  - `E:\seikyu/resources/views/invoices/pdf.blade.php`
  - `E:\seikyu/resources/views/invoices/receipt.blade.php`
  - `E:\seikyu/config/pdf.php`
  - `E:\seikyu/tests/Unit/InvoicePdfServiceTest.php`

以上です。