#!/bin/sh
# docker/entrypoint.sh
# コンテナ起動時に実行時環境変数で設定キャッシュを行い、
# スケジューラ・キューワーカーをバックグラウンド起動してから
# フロント(FrankenPHP/Caddy)を起動する。

set -e

echo "==> 設定キャッシュを作成中..."
php artisan config:cache

echo "==> スケジューラ(start) ..."
php artisan schedule:work &
SCHEDULE_PID=$!

echo "==> キューワーカー(start) ..."
php artisan queue:work --tries=3 --backoff=60 --max-time=3600 --sleep=5 &
QUEUE_PID=$!

# サブプロセス終了を監視し、いずれかが落ちたらコンテナごと終了させる
shutdown() {
    echo "==> シャットダウンシグナルを受信しました"
    kill "$SCHEDULE_PID" 2>/dev/null || true
    kill "$QUEUE_PID" 2>/dev/null || true
    wait "$SCHEDULE_PID" 2>/dev/null || true
    wait "$QUEUE_PID" 2>/dev/null || true
    exit 0
}

trap shutdown TERM INT

# フロントをフォアグラウンドで起動 (PID 1 として)
exec frankenphp run --config /etc/frankenphp/Caddyfile
