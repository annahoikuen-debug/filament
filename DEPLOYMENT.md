# デプロイメントチェックリスト

## 前提条件
- PHP 8.2+
- Composer 2.0+
- MySQL 5.7+ または PostgreSQL 10+（本番環境）
- Node.js 18+（アセットコンパイル用）

## 手順
1. コードを取得: `git pull origin main`
2. 依存関係インストール: `composer install --no-dev -o && npm install && npm run build`
3. 環境設定: `.env` を編集し、必要な値を設定
   - `FACILITY_*` 環境変数（施設名・登録番号・銀行情報）
   - `FACILITY_INVOICE_NUMBER` は `T` + 13桁数字の形式であること
4. マイグレーション実行: `php artisan migrate --force`
5. キャッシュクリア: `php artisan cache:clear && php artisan config:clear && php artisan view:clear`
6. 設定キャッシュ: `php artisan config:cache && php artisan route:cache`
7. サービス再起動: システムサービスまたはウェブサーバーを再起動
8. スモークテスト: 管理画面（/admin）へログインし、月次請求データ一覧が表示されることを確認

## ロールバック手順
1. 前のバージョンのコードを取得
2. 同上の手順 2-7 を実行
3. データベースロールバックが必要な場合はバックアップからリストア
   - `php artisan migrate:rollback` は最後の手段（データ損失に注意）

## テスト実行
```bash
vendor/bin/pest              # 全テスト実行
vendor/bin/pest --parallel   # 並列実行
```

## 注意事項
- 設定バリデーション（AppServiceProvider）により、不正な `FACILITY_INVOICE_NUMBER` や不足のある銀行情報があると起動時に例外が発生します
- 月途中入居・退去の住民も対象月の請求生成に含まれます（`InvoiceCalculationService`）
- Filament管理画面へのアクセスは `is_admin === true` のユーザーのみ許可されます
