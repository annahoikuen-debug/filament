# トライアル期限管理 - ステップ7.1: トライアル期限フィールド追加の確認

すでに trial_ends_at フィールドは作成済みのため、インデックスを確認/追加する必要はありません。
以前のステップで trial_ends_at フィールドは trials テーブルに追加済みです。

確認:
- `database/migrations/2026_10_08_000001_create_trials_table.php` で trial_ends_at フィールドが定義済み
- `php artisan migrate:status` でマイグレーションが [1] Ran と表示されている

次のステップに進みます：ステップ7.2 - 期限通知ジョブ作成