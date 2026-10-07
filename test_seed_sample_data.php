<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    $request = Illuminate\Http\Request::capture()
);

// Test the seed_sample_data functionality
use App\Models\Trial;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\MonthlyInvoice;
use App\Services\TrialProvisioningService;

// テストケース1: seed_sample_data = false の場合（サンプルデータなし）
echo "テストケース1: seed_sample_data = false\n";

try {
    // ユニークなメールアドレスを使用
    $uniqueEmail = 'no_seed_test_' . uniqid() . '@example.com';
    
    // トライアルインスタンスを作成してDBに保存
    $trial = Trial::create([
        'company_name' => 'テスト施設',
        'contact_name' => 'テスト 太郎',
        'email' => $uniqueEmail,
        'phone' => '03-1234-5678',
        'facility_type' => 'paid_elderly',
        'resident_capacity' => '50_100',
        'status' => 'pending',
        'trial_config' => [
            'seed_sample_data' => false,
        ]
    ]);
    
    echo "トライアル作成: ID = " . $trial->id . "\n";
    
    // サービスをインスタンス化
    $service = new TrialProvisioningService();
    
    // プロビジョニングを実行
    $result = $service->provisionTrial($trial);
    
    if ($result) {
        echo "プロビジョニング成功\n";
        
        // トライアルが更新されたことを確認
        $trial->refresh();
        echo "トライアルステータス: " . $trial->status . "\n";
        
        // サンプルデータが作成されていないことを確認
        $residentCount = Resident::where('facility_id', $trial->facility_id)->count();
        echo "作成された入居者数: " . $residentCount . " (期待値: 0)\n";
        
        $invoiceCount = MonthlyInvoice::where('facility_id', $trial->facility_id)->count();
        echo "作成された請求書数: " . $invoiceCount . " (期待値: 0)\n";
        
        // クリーンアップ
        if ($trial->facility_id) {
            $trial->facility->delete();
        }
        $trial->delete();
        echo "テストデータをクリーンアップしました\n";
    } else {
        echo "プロビジョニング失敗\n";
    }
} catch (\Exception $e) {
    echo "プロビジョニング中に例外が発生: " . $e->getMessage() . "\n";
}

echo "\n";

// テストケース2: seed_sample_data = true の場合（サンプルデータあり）
echo "テストケース2: seed_sample_data = true\n";

try {
    // ユニークなメールアドレスを使用
    $uniqueEmail = 'with_seed_test_' . uniqid() . '@example.com';
    
    // トライアルインスタンスを作成してDBに保存
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
        ]
    ]);
    
    echo "トライアル作成: ID = " . $trial->id . "\n";
    
    // サービスをインスタンス化
    $service = new TrialProvisioningService();
    
    // プロビジョニングを実行
    $result = $service->provisionTrial($trial);
    
    if ($result) {
        echo "プロビジョニング成功\n";
        
        // トライアルが更新されたことを確認
        $trial->refresh();
        echo "トライアルステータス: " . $trial->status . "\n";
        
        // サンプルデータが作成されていることを確認
        $residentCount = Resident::where('facility_id', $trial->facility_id)->count();
        echo "作成された入居者数: " . $residentCount . " (期待値: 2)\n";
        
        if ($residentCount > 0) {
            $firstResident = Resident::where('facility_id', $trial->facility_id)->first();
            echo "最初の入居者: " . $firstResident->name . " (室号: " . $firstResident->room_number . ")\n";
        }
        
        $invoiceCount = MonthlyInvoice::where('facility_id', $trial->facility_id)->count();
        echo "作成された請求書数: " . $invoiceCount . " (期待値: 1 か 2)\n";
        
        // クリーンアップ
        if ($trial->facility_id) {
            $trial->facility->delete();
        }
        $trial->delete();
        echo "テストデータをクリーンアップしました\n";
    } else {
        echo "プロビジョニング失敗\n";
    }
} catch (\Exception $e) {
    echo "プロビジョニング中に例外が発生: " . $e->getMessage() . "\n";
}

echo "\n";