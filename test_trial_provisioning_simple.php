<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    $request = Illuminate\Http\Request::capture()
);

// Test the provisioning service - simplified
use App\Models\Trial;
use App\Services\TrialProvisioningService;

// テストケース: まずはトライアル作成だけをテスト
echo "テスト: トライアル作成だけをテスト\n";

try {
    $trial = Trial::create([
        'company_name' => 'テスト施設',
        'contact_name' => 'テスト 太郎',
        'email' => 'test@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'paid_elderly',
        'resident_capacity' => '50_100',
        'status' => 'pending'
    ]);
    
    echo "トライアル作成成功: ID = " . $trial->id . "\n";
    
    // クリーンアップ
    $trial->delete();
    echo "テストデータをクリーンアップしました\n";
} catch (\Exception $e) {
    echo "トライアル作成中に例外が発生: " . $e->getMessage() . "\n";
}

echo "\n";

// テストケース: サービスのインスタンス化だけをテスト
echo "テスト: サービスのインスタンス化だけをテスト\n";
try {
    $service = new TrialProvisioningService();
    echo "サービスのインスタンス化成功\n";
} catch (\Exception $e) {
    echo "サービスのインスタンス化中に例外が発生: " . $e->getMessage() . "\n";
}

echo "\n";

// テストケース: ファシリティ作成メソッドだけをテスト
echo "テスト: ファシリティ作成メソッドだけをテスト\n";
try {
    $trial = new Trial();
    $trial->company_name = 'テスト施設';
    $trial->contact_name = 'テスト 太郎';
    $trial->email = 'test2@example.com'; // 異なるメールを使う
    $trial->phone = '03-1234-5678';
    $trial->facility_type = 'paid_elderly';
    $trial->resident_capacity = '50_100';
    $trial->status = 'pending';
    
    $service = new TrialProvisioningService();
    $facility = $service->createOrGetTrialFacility($trial);
    
    echo "ファシリティ作成/取得成功: ID = " . $facility->id . ", 名前 = " . $facility->name . "\n";
    
    // クリーンアップ
    $facility->delete();
} catch (\Exception $e) {
    echo "ファシリティ作成中に例外が発生: " . $e->getMessage() . "\n";
}

echo "\n";