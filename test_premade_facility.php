<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    $request = Illuminate\Http\Request::capture()
);

// Test seedSampleData by calling provisionTrial on a trial with pre-created facility
use App\Models\Trial;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\MonthlyInvoice;
use App\Services\TrialProvisioningService;

// テストケース: 事前に施設を作成したトライアルでseedSampleDataをテスト
echo "テストケース: 事前に施設を作成したトライアルでseedSampleDataをテスト\n";

try {
    // ファシリティを作成
    $facility = Facility::create([
        'name' => 'テスト施設',
        'operator' => 'テスト事業者',
        'postal_code' => '000-0000',
        'address' => 'テスト住所',
        'phone' => '03-1234-5678',
        'email' => 'premade_facility_test@example.com',
        'invoice_registration_number' => 'T' . rand(1000000000000, 9999999999999),
        'bank' => [],
        'billing' => [
            'direct_debit_day' => 27,
            'bank_transfer_due_days' => 30,
        ],
        'is_active' => true,
    ]);
    
    echo "Facility created: ID = " . $facility->id . PHP_EOL;
    
    // トライアルを作成（施設IDを事前に設定）
    $uniqueEmail = 'premade_facility_test_' . uniqid() . '@example.com';
    $trial = Trial::create([
        'company_name' => 'テスト施設',
        'contact_name' => 'テスト 太郎',
        'email' => $uniqueEmail,
        'phone' => '03-1234-5678',
        'facility_type' => 'paid_elderly',
        'resident_capacity' => '50_100',
        'status' => 'pending',
        'trial_config' => [
            'seed_sample_data' => true,
        ],
        'facility_id' => $facility->id, // 事前に施設IDを設定
    ]);
    
    echo "Trial created: ID = " . $trial->id . ", Facility ID = " . $trial->facility_id . PHP_EOL;
    
    // サービスをインスタンス化
    $service = new TrialProvisioningService();
    
    // プロビジョニングを実行（施設作成ステップをスキップするはず）
    $result = $service->provisionTrial($trial);
    
    if ($result) {
        echo "プロビジョニング成功\n";
        
        // トライアルが更新されたことを確認
        $trial->refresh();
        echo "トライアルステータス: " . $trial->status . "\n";
        echo "施設ID: " . ($trial->facility_id ?? 'NULL') . "\n";
        
        // サンプルデータが作成されていることを確認
        $residentCount = Resident::where('facility_id', $trial->facility_id)->count();
        echo "作成された入居者数: " . $residentCount . " (期待値: 2)\n";
        
        if ($residentCount > 0) {
            $firstResident = Resident::where('facility_id', $trial->facility_id)->first();
            echo "最初の入居者: " . $firstResident->name . " (室号: " . $firstResident->room_number . ")" . PHP_EOL;
        }
        
        $invoiceCount = MonthlyInvoice::where('facility_id', $trial->facility_id)->count();
        echo "作成された請求書数: " . $invoiceCount . " (期待値: 1 か 2)" . PHP_EOL;
        
        // クリーンアップ
        $facility->delete();
        $trial->delete();
        echo "テストデータをクリーンアップしました\n";
    } else {
        echo "プロビジョニング失敗\n";
        
        // クリーンアップ
        $facility->delete();
        if ($trial->exists) {
            $trial->delete();
        }
    }
} catch (\Exception $e) {
    echo "プロビジョニング中に例外が発生: " . $e->getMessage() . PHP_EOL;
    // echo "Trace: " . $e->getTraceAsString() . PHP_EOL;
    
    // クリーンアップ
    try {
        if (isset($facility) && $facility->exists) {
            $facility->delete();
        }
        if (isset($trial) && $trial->exists) {
            $trial->delete();
        }
    } catch (\Exception $cleanupException) {
        // クリーンアップ中の例外は無視
    }
}

echo "\n";