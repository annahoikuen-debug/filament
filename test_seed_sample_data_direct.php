<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    $request = Illuminate\Http\Request::capture()
);

// Test the seedSampleData method directly
use App\Models\Facility;
use App\Models\Resident;
use App\Models\MonthlyInvoice;
use App\Services\TrialProvisioningService;
use App\Enums\ResidentStatus;
use Illuminate\Support\Facades\Log;

// テストケース: seedSampleDataメソッドを直接テスト
echo "テストケース: seedSampleDataメソッドを直接テスト\n";

try {
    // ファシリティを作成
    $facility = Facility::create([
        'name' => 'テスト施設',
        'operator' => 'テスト事業者',
        'postal_code' => '000-0000',
        'address' => 'テスト住所',
        'phone' => '03-1234-5678',
        'email' => 'seed_test@example.com',
        'invoice_registration_number' => 'T' . rand(1000000000000, 9999999999999),
        'bank' => [],
        'billing' => [
            'direct_debit_day' => 27,
            'bank_transfer_due_days' => 30,
        ],
        'is_active' => true,
    ]);
    
    echo "Facility created: ID = " . $facility->id . PHP_EOL;
    
    // サービスをインスタンス化
    $service = new TrialProvisioningService();
    
    // サンプルデータ設定
    $config = [
        'seed_sample_data' => true,
    ];
    
    // seedSampleDataメソッドを直接呼び出し
    echo "Calling seedSampleData...\n";
    $service->seedSampleData($facility, $config);
    
    // 結果を確認
    $residentCount = Resident::where('facility_id', $facility->id)->count();
    echo "作成された入居者数: " . $residentCount . PHP_EOL;
    
    if ($residentCount > 0) {
        $firstResident = Resident::where('facility_id', $facility->id)->first();
        echo "最初の入居者: " . $firstResident->name . " (室号: " . $firstResident->room_number . ")" . PHP_EOL;
    }
    
    $invoiceCount = MonthlyInvoice::where('facility_id', $facility->id)->count();
    echo "作成された請求書数: " . $invoiceCount . PHP_EOL;
    
    // クリーンアップ
    $facility->delete();
    echo "Test completed successfully" . PHP_EOL;
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo "Trace: " . $e->getTraceAsString() . PHP_EOL;
}