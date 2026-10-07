<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    $request = Illuminate\Http\Request::capture()
);

// Basic functionality test
use App\Models\Facility;
use App\Models\Resident;
use App\Enums\ResidentStatus;

try {
    $facility = Facility::create([
        'name' => 'テスト施設',
        'operator' => 'テスト事業者',
        'postal_code' => '000-0000',
        'address' => 'テスト住所',
        'phone' => '03-1234-5678',
        'email' => 'test@example.com',
        'invoice_registration_number' => 'T' . rand(1000000000000, 9999999999999),
        'bank' => [],
        'billing' => [
            'direct_debit_day' => 27,
            'bank_transfer_due_days' => 30,
        ],
        'is_active' => true,
    ]);
    
    echo "Facility created: ID = " . $facility->id . PHP_EOL;
    
    $resident = Resident::create([
        'facility_id' => $facility->id,
        'room_number' => '101',
        'name' => 'テスト 太郎',
        'name_kana' => 'テスト タロウ',
        'base_rent' => 60000,
        'base_management_fee' => 25000,
        'status' => ResidentStatus::Active,
        'move_in_date' => now()->subMonth()->toDateString(),
    ]);
    
    echo "Resident created: ID = " . $resident->id . PHP_EOL;
    
    // Clean up
    $resident->delete();
    $facility->delete();
    
    echo "Test completed successfully" . PHP_EOL;
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}