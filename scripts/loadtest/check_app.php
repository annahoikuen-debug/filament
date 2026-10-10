<?php
// アプリケーション起動確認 + データ件数確認
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "APP_ENV: " . app()->environment() . PHP_EOL;
echo "DB_CONNECTION: " . config('database.default') . PHP_EOL;
echo "QUEUE_CONNECTION: " . config('queue.default') . PHP_EOL;
echo "CACHE_STORE: " . config('cache.default') . PHP_EOL;
echo "SESSION_DRIVER: " . config('session.driver') . PHP_EOL;
echo "MAIL_MAILER: " . config('mail.default') . PHP_EOL;
echo "---" . PHP_EOL;

foreach (['users', 'facilities', 'residents', 'charge_items', 'daily_charges', 'monthly_invoices', 'chatbot_faqs', 'chat_logs', 'trials', 'bookings', 'form_submissions', 'service_invoices', 'tax_settings'] as $table) {
    echo str_pad($table, 22) . ': ' . DB::table($table)->count() . PHP_EOL;
}
