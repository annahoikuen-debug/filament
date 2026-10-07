<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    $request = Illuminate\Http\Request::capture()
);

// Test the provisioning service - simplified
use App\Models\Trial;
use App\Services\TrialProvisioningService;

// テストケース: まずはトライアル作成だけをテスト（必須フィールドすべてを含む）
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

// テストケース: ユーザー作成メソッドだけをテスト
echo "テスト: ユーザー作成メソッドだけをテスト\n";
try {
    $trial = new Trial();
    $trial->company_name = 'テスト施設';
    $trial->contact_name = 'テスト 太郎';
    $trial->email = 'test3@example.com';
    $trial->phone = '03-1234-5678';
    $trial->facility_type = 'paid_elderly';
    $trial->resident_capacity = '50_100';
    $trial->status = 'pending';
    
    $facility = \App\Models\Facility::create([
        'name' => 'テスト施設',
        'operator' => 'テスト事業者',
        'postal_code' => '000-0000',
        'address' => 'テスト住所',
        'phone' => '03-1234-5678',
        'email' => 'test@example.com',
        'invoice_registration_number' => 'T1234567890123',
        'bank' => [],
        'billing' => ['direct_debit_day' => 27, 'bank_transfer_due_days' => 30],
        'is_active' => true
    ]);
    
    $service = new TrialProvisioningService();
    $user = $service->createAdminUser($trial, $facility);
    
    echo "ユーザー作成成功: ID = " . $user->id . ", 名前 = " . $user->name . ", メール = " . $user->email . "\n";
    
    // クリーンアップ
    $user->delete();
    $facility->delete();
} catch (\Exception $e) {
    echo "ユーザー作成中に例外が発生: " . $e->getMessage() . "\n";
}

echo "\n";

// テストケース: 正常なプロビジョニングフロー
echo "テストケース: 正常なプロビジョニングフロー\n";

try {
    // トライアルインスタンスを作成してDBに保存
    $trial = Trial::create([
        'company_name' => 'テスト施設',
        'contact_name' => 'テスト 太郎',
        'email' => 'test4@example.com',
        'phone' => '03-1234-5678',
        'facility_type' => 'paid_elderly',
        'resident_capacity' => '50_100',
        'status' => 'pending'
    ]);
    
    echo "トライアル作成: ID = " . $trial->id . "\n";
    
    // サービスをインスタンス化
    $service = new TrialProvisioningService();
    
    // プロビジョニングを実行
    $result = $service->provisionTrial($trial);
    
    if ($result) {
        echo "プロビジョニング成功\n";
        
        // トライアルが更新されたことを確認
        $trial->refresh(); // 最新の状態を取得
        echo "トライアルステータス: " . $trial->status . "\n";
        echo "施設IDが設定された: " . ($trial->facility_id ? 'はい' : 'いいえ') . "\n";
        if ($trial->facility_id) {
            echo "関連する施設名: " . $trial->facility->name . "\n";
        }
        echo "トライアル開始日: " . $trial->trial_started_at . "\n";
        echo "トライアル終了日: " . $trial->trial_ends_at . "\n";
        
        // ユーザーが作成されたことを確認
        $user = \App\Models\User::where('email', $trial->email)->first();
        if ($user) {
            echo "管理者ユーザーが作成された: " . $user->name . " (" . $user->email . ")\n";
            echo "管理者権限: " . ($user->is_admin ? 'はい' : 'いいえ') . "\n";
        } else {
            echo "管理者ユーザーが見つかりません\n";
        }
        
        // クリーンアップ
        if ($trial->facility_id) {
            $trial->facility->delete();
        }
        if ($user ?? null) {
            $user->delete();
        }
        $trial->delete();
        echo "テストデータをクリーンアップしました\n";
    } else {
        echo "プロビジョニング失敗\n";
    }
} catch (\Exception $e) {
    echo "プロビジョニング中に例外が発生: " . $e->getMessage() . "\n";
    
    // クリーンアップ（可能な限り）
    try {
        if (isset($trial) && $trial->exists) {
            if (isset($trial->facility_id) && $trial->facility) {
                $trial->facility->delete();
            }
            $trial->delete();
        }
        if (isset($user) && $user->exists) {
            $user->delete();
        }
        if (isset($facility) && $facility->exists) {
            $facility->delete();
        }
    } catch (\Exception $cleanupException) {
        // クリーンアップ中の例外は無視
    }
}

echo "\n";