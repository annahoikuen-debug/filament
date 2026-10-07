<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    $request = Illuminate\Http\Request::capture()
);

// Test the createOrGetTrialFacility method directly
use App\Models\Trial;
use App\Services\TrialProvisioningService;

// テストケース: createOrGetTrialFacilityメソッドを直接テスト
echo "テストケース: createOrGetTrialFacilityメソッドを直接テスト\n";

try {
    // トライアルインスタンスを作成（DBに保存しない）
    $trial = new Trial();
    $trial->company_name = 'テスト施設';
    $trial->contact_name = 'テスト 太郎';
    $trial->email = 'facility_test@example.com';
    $trial->phone = '03-1234-5678';
    $trial->facility_type = 'paid_elderly';
    $trial->resident_capacity = '50_100';
    $trial->status = 'pending';
    
    // サービスをインスタンス化
    $service = new TrialProvisioningService();
    
    // ファシリティ作成/取得を実行
    $facility = $service->createOrGetTrialFacility($trial);
    
    echo "ファシリティ作成/取得成功\n";
    echo "施設ID: " . ($facility->id ?? 'NULL') . "\n";
    echo "施設名: " . ($facility->name ?? 'NULL') . "\n";
    echo "施設のオペレーター: " . ($facility->operator ?? 'NULL') . "\n";
    echo "施設のメール: " . ($facility->email ?? 'NULL') . "\n";
    echo "施設のメモ: " . ($facility->notes ?? 'NULL') . "\n";
    
    // クリーンアップ
    $facility->delete();
    
} catch (\Exception $e) {
    echo "ファシリティ作成/取得中に例外が発生: " . $e->getMessage() . "\n";
    echo "トレース: " . $e->getTraceAsString() . "\n";
}

echo "\n";

// テストケース: トライアル更新メソッドをテスト
echo "テストケース: トライアル更新メソッドをテスト\n";
try {
    // トライアルインスタンスを作成してDBに保存
    $trial = Trial::create([
        'company_name' => 'テスト施設',
        'contact_name' => 'テスト 太郎',
        'email' => 'update_test@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'paid_elderly',
        'resident_capacity' => '50_100',
        'status' => 'pending'
    ]);
    
    echo "トライアル作成: ID = " . $trial->id . "\n";
    
    // サービスをインスタンス化
    $service = new TrialProvisioningService();
    
    // ファシリティ作成/取得を実行
    $facility = $service->createOrGetTrialFacility($trial);
    
    echo "ファシリティ作成/取得: ID = " . ($facility->id ?? 'NULL') . "\n";
    
    // トライアルを更新
    $trial->update([
        'facility_id' => $facility->id,
        'status' => 'active',
    ]);
    
    echo "トライアル更新後:\n";
    echo "  ファシリティID: " . ($trial->fresh()->facility_id ?? 'NULL') . "\n";
    echo "  ステータス: " . $trial->fresh()->status . "\n";
    
    // クリーンアップ
    if ($trial->facility_id) {
        $trial->facility->delete();
    }
    $trial->delete();
    
} catch (\Exception $e) {
    echo "トライアル更新中に例外が発生: " . $e->getMessage() . "\n";
    echo "トレース: " . $e->getTraceAsString() . "\n";
    
    // クリーンアップ
    try {
        if (isset($trial) && $trial->exists) {
            if (isset($trial->facility_id) && $trial->facility) {
                $trial->facility->delete();
            }
            $trial->delete();
        }
    } catch (\Exception $cleanupException) {
        // クリーンアップ中の例外は無視
    }
}

echo "\n";