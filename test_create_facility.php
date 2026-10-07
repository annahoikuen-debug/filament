<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $facility = App\Models\Facility::create([
        'name' => 'テスト施設',
        'operator' => 'テスト運営',
        'postal_code' => '123-4567',
        'address' => '東京都',
        'phone' => '03-1234-5678',
        'invoice_registration_number' => 'T9876543210987', // Different number to avoid unique constraint
        'bank' => [
            'name' => 'テスト銀行',
            'branch_name' => 'テスト支店',
            'account_type' => '普通',
            'account_number' => '1234567',
            'account_holder' => 'カ）テスト',
        ],
        'billing' => [
            'direct_debit_day' => 27,
            'bank_transfer_due_days' => 30,
        ],
        'is_active' => true,
    ]);
    echo 'Facility created successfully with ID: ' . $facility->id . PHP_EOL;
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    echo 'Trace: ' . $e->getTraceAsString() . PHP_EOL;
}
?>