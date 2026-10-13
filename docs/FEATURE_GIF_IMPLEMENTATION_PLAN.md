# 機能説明GIF 24個 詳細実装計画書

## 1. 概要
- 対象: サンプルデータ（定員25名施設「ひまわりケア施設」・2026年10月分請求・税込合計約¥305万）に基づく機能説明アニメーションGIF 24個
- 生成方式: PHP GD拡張によるフレーム動的レンダリング → アニメーションGIF書き出し
- 出力先: `website/assets/gifs/`
- スクリプト: `scripts/generate_feature_gifs.php`（単一ファイル、再実行可能）

## 2. 技術仕様

### 2.1 共通パラメータ
```php
const W = 480;            // 幅
const H = 360;            // 高さ
const FPS_DELAY = 50;     // フレーム遅延(1/100秒) = 0.5秒/フレーム
const COLORS = 128;       // パレット色数
const FONT = 'resources/fonts/ipaexg.ttf'; // 日本語ゴシック
```

### 2.2 描画ライブラリ関数（スクリプト内ヘルパー）
| 関数 | 役割 |
|------|------|
| `createFrame()` | 白背景480x360パレット画像生成 |
| `drawChrome($im,$title)` | ウィンドウ風枠・タイトルバー・上部バッジ描画 |
| `text($im,$str,$x,$y,$size,$color)` | imagettftextラッパー（UTF-8日本語） |
| `progressBar($im,$ratio)` | 下部プログレスバー |
| `stepBadge($im,$n,$total)` | 「手順 n/N」バッジ |
| `addGifFrames()` | フレームをGIFに追記（imagewebp中間なし、imagegif連結） |

### 2.3 アニメーションGIF書き出し方式
GDの `imagegif()` は1枚ずつしか出力しないため、**GIF89a連結方式**を採用:
1. 各フレームを `ob_start(); imagegif($im); $data = ob_get_clean();`
2. 最初のフレームから **グローバルカラーテーブル + Logical Screen Descriptor** を取得
3. 2フレーム目以降は **Graphic Control Extension (GCE) + Image Descriptor + LZWデータ** 部分のみ抽出して連結
4. 末尾にトレーラー `0x3B` を付与
5. ヘッダのループ/遅延はGCE(0x21 0xF9)ごとに遅延値を書き換え、`NETSCAPE2.0` アプリ拡張をヘッダ後に挿入して無限ループ

> 実装簡略化: フレーム間でパレットを共通化（同一128色パレットを全フレームで使用）することでカラーテーブル連結問題を回避。

## 3. 24機能とシナリオ定義

| # | ファイル名 | 機能 | パターン | フレーム構成 |
|---|-----------|------|---------|-------------|
| 01 | gif-01-resident-registration.gif | 入居者マスタ登録 | form | 0)空フォーム→1)氏名入力→2)部屋番号→3)契約日→4)保存完了✓ |
| 02 | gif-02-daily-charge-mobile.gif | 消耗品費用スマホ入力 | form | 0)スマホ枠→1)項目選択(紙おむつ)→2)数量30→3)日付→4)登録✓→5)一覧反映 |
| 03 | gif-03-care-insurance-charge.gif | 介護保険費用入力 | form | 0)介護項目一覧→1)選択→2)単価×数量→3)給付率計算→4)登録✓ |
| 04 | gif-04-recurring-charge.gif | 定期課金設定 | form | 0)空設定→1)項目名→2)金額→3)頻度「毎月」→4)有効化✓ |
| 05 | gif-05-price-history.gif | 料金履歴管理 | list | 0)料金表→1)新行追加→2)旧価格グレー化→3)有効日付ハイライト |
| 06 | gif-06-csv-import.gif | CSV一括取込 | flow | 0)CSVアイコン→1)プレビュー→2)検証→3)取込完了 件数表示 |
| 07 | gif-07-bulk-invoice-generation.gif | 月次請求一括生成 | progress | 0)ボタン→1)実行中(1/25→)カウントアップ→25/25→完了✓ |
| 08 | gif-08-tax-calculation.gif | 税率自動判別 | list | 0)3行(家賃/管理費/食費)→1)非課税10%8%バッジ点灯→2)税額集計 |
| 09 | gif-09-proration.gif | 月途中入退居の日割り | tutorial | 0)カレンダー→1)入居日マーク→2)日数12/31→3)按分金額表示 |
| 10 | gif-10-facility-filter.gif | 施設別フィルタ | list | 0)全施設25件→1)施設A選択→2)行がフィルタ→3)件数更新 |
| 11 | gif-11-invoice-recalculate.gif | 請求書再計算 | form | 0)請求書→1)自費行追加→2)再計算ボタン→3)合計更新✓ |
| 12 | gif-12-unpaid-alert.gif | 未収金アラート | dashboard | 0)ダッシュ→1)未収ウィジェット点滅→2)対象3件強調→3)通知バッジ |
| 13 | gif-13-invoice-pdf.gif | 個別請求書PDF | flow | 0)一覧→1)PDFボタン→2)レンダリング→3)PDFプレビュー |
| 14 | gif-14-receipt-pdf.gif | 領収書PDF | flow | 0)請求書→1)領収書ボタン→2)印刷アニメ→3)領収書プレビュー |
| 15 | gif-15-bulk-zip.gif | 一括ZIP出力 | progress | 0)ボタン→1)50枚中n枚カウント→2)ZIP完成→3)ダウンロード✓ |
| 16 | gif-16-category-zip.gif | カテゴリ別ZIP | flow | 0)チェックボックス群→1)3カテゴリ選択→2)分割→3)3つのZIP表示 |
| 17 | gif-17-password-pdf.gif | パスワード保護PDF | tutorial | 0)設定画面→1)生年月日入力→2)鍵アイコン→3)保護PDF✓ |
| 18 | gif-18-bank-qr.gif | 振込QRコード | form | 0)請求書→1)QR生成→2)QRがドット単位で描画→3)完成 |
| 19 | gif-19-dashboard.gif | ダッシュボード | dashboard | 0)白画面→1)カード順次出現(件数/金額/未収)→2)数値カウントアップ |
| 20 | gif-20-accounting-csv.gif | 会計連携CSV | flow | 0)エクスポート設定→1)仕訳プレビュー→2)CSV生成→3)freee/MFバッジ |
| 21 | gif-21-invoice-merge.gif | 同居家族請求統合 | flow | 0)2請求書→1)統合ボタン→2)1枚に合成→3)内訳表示 |
| 22 | gif-22-chatbot.gif | チャットボット照会 | form | 0)チャット枠→1)「山田さんの10月請求は?」→2)検索→3)回答表示 |
| 23 | gif-23-multi-facility.gif | 複数施設管理 | list | 0)施設1→1)切替タブ→2)施設2の25名一覧→3)合計比較 |
| 24 | gif-24-template-settings.gif | PDFテンプレート設定 | form | 0)設定画面→1)ロゴ選択→2)色変更→3)プレビュー反映✓ |

### 3.1 サンプルデータ反映ルール
GIF内に表示する数値は `simulation_10x.php` の検証結果と整合させる:
- 入居者数: 25名
- 月次請求件数: 25件
- 税込合計: ¥3,056,750（Run#1の実測値）
- 自費記録: 42件
- ZIP: 15.3KB / 個別PDF: 8.3KB

## 4. 実装手順
1. `scripts/generate_feature_gifs.php` にヘルパー群 + GifBuilderクラス実装
2. 24シナリオを連想配列 `SCENARIOS` で定義（name/title/frames callable）
3. `php scripts/generate_feature_gifs.php` 実行
4. 検証スクリプトで全24個の存在・サイズ・GIFヘッダ(`GIF89a`)確認
5. 1MB超過時はフレーム数・色数を削減して最適化
6. `website/product/features/*.html` の該当セクションに `<img src="../assets/gifs/gif-XX-*.gif" alt="...">` を追記

## 5. 完了条件
- [ ] 24個のGIFが `website/assets/gifs/` に生成
- [ ] すべて `GIF89a` ヘッダかつアニメーション（フレーム≥6）
- [ ] 各ファイル < 1MB
- [ ] websiteの機能ページに埋め込み完了
