<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    $request = Illuminate\Http\Request::capture()
);

// Test the provisioning service after fixing Trial model
use App\Models\Trial;
use App\Services\TrialProvisioningService;

// テストケース: 正常なプロビジョニングフロー
echo "テストケース: 正常なプロビジョニングフロー\n";

try {
    // ユニークなメールアドレスを使用してソフトデリートとの競合を避ける
    $uniqueEmail = 'fixed_test_' . uniqid() . '@example.com';
    
    // トライアルインスタンスを作成してDBに保存
    $trial = Trial::create([
        'company_name' => 'テスト施設',
        'contact_name' => 'テスト 太郎',
        'email' => $uniqueEmail,
        'phone' => '03-1234-5678',
        'facility_type' => 'paid_elderly',
        'resident_capacity' => '50_100',
        'status' => 'pending'
    ]);
    
    echo "トライアル作成: ID = " . $trial->id . ", Email = " . $uniqueEmail . "\n";
    
    // サービスをインスタンス化
    $service = new TrialProvisioningService();
    
    // プロビジョニングを実行
    $result = $service->provisionTrial($trial);
    
    if ($result) {
        echo "プロビジョニング成功\n";
        
        // トライアルが更新されたことを確認
        $trial->refresh(); // 最新の状態を取得
        echo "トライアルステータス: " . $trial->status . "\n";
        echo "施設ID: " . ($trial->facility_id ?? 'NULL') . "\n";
        if ($trial->facility_id) {
            echo "関連する施設名: " . $trial->facility->name . "\n";
        }
        echo "トライアル開始日: " . $trial->trial_started_at . "\n";
        echo "トライアル終了日: " . $trial->trial_ends_at . "\n";
        
        // ユーザーが作成されたことを確認
        $user = \App\Models\User::where('email', $uniqueEmail)->first();
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
}

echo "\n";