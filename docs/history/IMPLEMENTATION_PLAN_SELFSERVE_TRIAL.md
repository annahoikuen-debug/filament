# セルフサーブトライアル 自動化実装計画書

## 概要
現状の営業主導のトライアルプロセスを完全自動化し、施設担当者がセルフサービスでトライアル環境を取得できるようにする

## 現状の課題
1. フォーム送信後、営業が手動で連絡・環境作成（2営業日遅延）
2. 環境構築に営業リソースが必要
3. トライアル期間管理が手動
4. 契約への移行が属人的

## 目標状態
1. フォーム送信→即座にトライアル環境発行（自動プロビジョニング）
2. セルフオンボーディングウィザードで初期設定完了
3. トライアル期限・利用状況の自動追跡
4. 契約移行時の自動環境アップグレード（データ・設定引き継ぎ）

## 実装範囲
- トライアル施設管理システム
- セルフサインアップフロー
- 自動環境プロビジョニング
- トライアル期間管理・通知
- トライアル→本契約自動移行
- 回帰防止テストスイート

---

## フェーズ1: トライアル施設管理基盤

### 1.1 データベーススキーマ追加

```php
// database/migrations/2026_10_08_000001_create_trials_table.php
Schema::create('trials', function (Blueprint $table) {
    $table->id();
    $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
    $table->string('company_name');
    $table->string('contact_name');
    $table->string('email')->unique();
    $table->string('phone')->nullable();
    $table->string('facility_type');
    $table->integer('resident_capacity');
    $table->string('subdomain')->unique()->nullable(); // trial-xxx.anshin.example.com
    $table->enum('status', ['pending', 'provisioning', 'active', 'expired', 'converted', 'cancelled'])
          ->default('pending');
    $table->timestamp('trial_started_at')->nullable();
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('converted_at')->nullable();
    $table->string('plan')->nullable(); // starter, standard, enterprise
    $table->json('trial_config')->nullable(); // 初期設定データ
    $table->timestamps();
    $table->softDeletes();
});

// インデックス追加
Schema::table('trials', function (Blueprint $table) {
    $table->index(['email', 'status']);
    $table->index(['subdomain']);
    $table->index(['trial_ends_at']);
});
```

### 1.2 Trialモデル作成

```php
// app/Models/Trial.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trial extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'company_name',
        'contact_name',
        'email',
        'phone',
        'facility_type',
        'resident_capacity',
        'subdomain',
        'status',
        'trial_started_at',
        'trial_ends_at',
        'converted_at',
        'plan',
        'trial_config',
        'facility_id'
    ];
    
    protected $casts = [
        'trial_config' => 'array',
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'converted_at' => 'datetime',
    ];
    
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }
    
    public function isActive()
    {
        return $this->status === 'active';
    }
    
    public function isExpired()
    {
        return $this->status === 'expired' || 
               ($this->trial_ends_at && $this->trial_ends_at->isPast());
    }
    
    public function daysUntilExpiry()
    {
        return $this->trial_ends_at?->diffInDays(now()) ?? 0;
    }
}
```

### 1.3 Trialプロビジョニングサービス

```php
// app/Services/TrialProvisioningService.php
namespace App\Services;

use App\Models\Trial;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use App\Services\InvoiceCalculationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Log;

class TrialProvisioningService
{
    /**
     * トライアル環境をプロビジョニング
     */
    public function provisionTrial(Trial $trial): bool
    {
        Log::info("Starting trial provisioning for trial {$trial->id}");
        
        try {
            // 1. トライアル施設の作成または取得
            $facility = $this->createOrGetTrialFacility($trial);
            
            // 2. 管理者ユーザー作成
            $adminUser = $this->createAdminUser($trial, $facility);
            
            // 3. サンプルデータ投入（オプション）
            $this->seedSampleData($facility, $trial->trial_config ?? []);
            
            // 4. トライアル情報更新
            $trial->update([
                'facility_id' => $facility->id,
                'status' => 'active',
                'trial_started_at' => now(),
                'trial_ends_at' => now()->addDays(14), // 14日間トライアル
            ]);
            
            // 5. 完了通知メール送信
            $this->sendProvisioningCompleteEmail($trial, $adminUser);
            
            Log::info("Trial provisioning completed for trial {$trial->id}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Trial provisioning failed for trial {$trial->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $trial->update(['status' => 'pending']); // リトライ可能に戻す
            throw $e;
        }
    }
    
    private function createOrGetTrialFacility(Trial $trial): Facility
    {
        // サブドメインベースの施設名で施設を検索または作成
        $facilityName = "トライアル {$trial->company_name}";
        
        return Facility::firstOrCreate(
            ['name' => $facilityName],
            [
                'operator' => $trial->company_name,
                'postal_code' => '000-0000',
                'address' => 'トライアル環境のため住所は未設定',
                'phone' => $trial->phone ?? '000-0000-0000',
                'email' => $trial->email,
                'fax' => '000-0000-0000',
                'invoice_registration_number' => 'T' . str_repeat('0', 13), // トライアル用仮番号
                'bank' => [], // トライアルでは銀行情報不要
                'billing' => [
                    'direct_debit_day' => 27,
                    'bank_transfer_due_days' => 30,
                ],
                'is_active' => true,
                'notes' => "トライアル環境: {$trial->id}"
            ]
        );
    }
    
    private function createAdminUser(Trial $trial, Facility $facility): User
    {
        $password = Str::random(12); // 自動生成パスワード
        
        return User::create([
            'name' => $trial->contact_name,
            'email' => $trial->email,
            'password' => Hash::make($password),
            'is_admin' => true, // トライアルでは管理者権限付与
        ]);
    }
    
    private function seedSampleData(Facility $facility, array $config): void
    {
        // サンプルデータ投入はオプション（設定で有効/無効切替可能）
        if (empty($config['seed_sample_data']) || !$config['seed_sample_data']) {
            return;
        }
        
        // サンプル入居者データ作成
        $sampleResidents = [
            ['101', 'トライアル 太郎', 'トライアル タロウ', 60000, 25000],
            ['102', 'トライアル 花子', 'トライアル ハナコ', 55000, 25000],
        ];
        
        foreach ($sampleResidents as [$room, $name, $kana, $rent, $fee]) {
            Resident::create([
                'facility_id' => $facility->id,
                'room_number' => $room,
                'name' => $name,
                'name_kana' => $kana,
                'base_rent' => $rent,
                'base_management_fee' => $fee,
                'status' => ResidentStatus::Active,
                'move_in_date' => now()->subMonth()->toDateString(),
            ]);
        }
        
        // 当月の請求データ生成
        $invoiceService = new InvoiceCalculationService();
        $currentMonth = now()->format('Y-m');
        $invoiceService->generateForMonth($currentMonth, false, $facility->id);
    }
    
    private function sendProvisioningCompleteEmail(Trial $trial, User $adminUser): void
    {
        // 実際の実装ではメール送信サービスを使用
        Log::info("Would send provisioning complete email to {$trial->email}", [
            'trial_id' => $trial->id,
            'admin_email' => $adminUser->email,
            'login_url' => config('app.url') . '/admin',
            'password' => '[AUTO-GENERATED]' // 実際は別途安全に 전달
        ]);
    }
}
```

### 1.4 トライアル管理コントローラー

```php
// app/Http/Controllers/TrialController.php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Trial;
use App\Services\TrialProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class TrialController extends Controller
{
    protected $provisioningService;
    
    public function __construct(TrialProvisioningService $provisioningService)
    {
        $this->provisioningService = $provisioningService;
    }
    
    /**
     * トライアル申請受付
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:trials,email',
            'phone' => 'nullable|string|max:20',
            'facility_type' => [
                'required',
                Rule::in(['special_nursing', 'nursing_health', 'medical_care', 
                         'paid_elderly', 'group_home', 'home_care', 'other'])
            ],
            'resident_capacity' => [
                'required',
                Rule::in(['under_30', '30_50', '50_100', '100_200', 'over_200', 'unknown'])
            ],
            'seed_sample_data' => 'sometimes|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // トライアルレコード作成
        $trial = Trial::create([
            'company_name' => $request->company_name,
            'contact_name' => $request->contact_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'facility_type' => $request->facility_type,
            'resident_capacity' => $request->resident_capacity,
            'trial_config' => [
                'seed_sample_data' => $request->input('seed_sample_data', false),
            ],
        ]);
        
        // 非同期でプロビジョニング開始（キュージョブにするのが理想）
        // ここでは同期で実装（後でキューに移行可能）
        try {
            $this->provisioningService->provisionTrial($trial);
            
            return response()->json([
                'success' => true,
                'message' => 'トライアル環境の準備が開始されました。メールにてログイン情報をお送りします。',
                'trial_id' => $trial->id
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to provision trial {$trial->id}", [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'トライアル環境の準備中にエラーが発生しました。しばらく経ってからお試しください。'
            ], 500);
        }
    }
    
    /**
     * トライアル状態取得（メールリンク等からの確認用）
     */
    public function show(Trial $trial)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $trial->id,
                'status' => $trial->status,
                'trial_started_at' => $trial->trial_started_at,
                'trial_ends_at' => $trial->trial_ends_at,
                'days_until_expiry' => $trial->daysUntilExpiry(),
                'facility_name' => $trial->facility?->name ?? null,
            ]
        ]);
    }
}
```

### 1.5 ルート定義

```php
// routes/api.php
use App\Http\Controllers\TrialController;

Route::post('/trials', [TrialController::class, 'store'])
     ->name('trials.store')
     ->middleware(['web']); // CSRF保護等のため

Route::get('/trials/{trial}', [TrialController::class, 'show'])
     ->name('trials.show')
     ->middleware(['web']);
```

### 1.6 既存の問い合わせフォームとの連携

既なる `/request/inquiry.html` のフォームを修正し、トライアル申請にも対応

```javascript
// website/request/inquiry.html のスクリプト部分を修正
form.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // バリデーション...
    if (!validateStep3()) {
        return;
    }
    
    // トライアル希望の場合は別エンドポイントへ
    const isTrialRequest = document.getElementById('timeline').value === 'immediate';
    
    if (isTrialRequest) {
        // トライアル申請フロー
        submitTrialRequest(formData);
    } else {
        // 通常の問い合わせフロー
        submitRegularInquiry(formData);
    }
});

function submitTrialRequest(formData) {
    fetch('/api/trials', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            company_name: formData.company,
            contact_name: formData.name,
            email: formData.email,
            phone: formData.phone,
            facility_type: formData.facility_type,
            resident_capacity: formData.capacity,
            seed_sample_data: formData.seed_sample_data ?? false
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showTrialSuccessMessage(data.trial_id);
        } else {
            showErrorMessage(data.message);
        }
    })
    .catch(error => {
        showErrorMessage('通信エラーが発生しました。');
    });
}
```

---

## フェーズ2: セルフオンボーディングウィザード

### 2.1 トライアルダッシュボード（簡易版）

Filamentパネルにトライアル用ダッシュボードを追加

```php
// app/Filament/Widgets/TrialOverview.php
namespace App\Filament\Widgets;

use App\Models\Trial;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TrialOverview extends BaseWidget
{
    protected static ?string $heading = 'トライアル情報';
    
    protected function getStats(): array
    {
        // 現在のユーザーのトライアルを取得（メールベースで検索）
        $trial = Trial::where('email', Auth::user()?->email)
                     ->where('status', 'active')
                     ->first();
                     
        if (!$trial) {
            return [];
        }
        
        return [
            Stat::make('トライアル期間', $trial->daysUntilExpiry() . '日残')
                ->description($trial->trial_ends_at?->format('Y/m/d'))
                ->color($trial->daysUntilExpiry() < 3 ? 'danger' : 'success'),
            Stat::make('登録入居者数', $trial->facility?->residents->count() ?? 0)
                ->description('施設総入居者数')
                ->color('info'),
            Stat::make('今月の請求書数', 
                $trial->facility?->monthlyInvoices
                      ->where('billing_year_month', now()->format('Y-m'))
                      ->count() ?? 0)
                ->description('作成済み請求書')
                ->color('warning'),
        ];
    }
}
```

### 2.2 初期設定ウィザード

トライアル施設に初めてアクセスした際の設定フロー

```php
// app/Http/Middleware/RedirectIfTrialNotConfigured.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Trial;

class RedirectIfTrialNotConfigured
{
    public function handle(Request $request, Closure $next)
    {
        // ログイン済みで、トライアル施設に所属している場合
        if (Auth::check() && Auth::user()->is_admin) {
            $trial = Trial::where('email', Auth::user()?->email)
                         ->whereIn('status', ['active', 'expired'])
                         ->first();
                         
            if ($trial && !$trial->facility?->bank) {
                // 銀行情報未設定の場合は設定ページへリダイレクト
                if (!$request->is('facility/*/edit') && !$request->is('facility/*/bank')) {
                    return redirect()->route('facility.edit', [
                        'facility' => $trial->facility->id,
                        'tab' => 'bank'
                    ])->with('warning', 'トライアルを継続するため、銀行口座情報を設定してください。');
                }
            }
        }
        
        return $next($request);
    }
}
```

カーネルにミドルウェアを追加
```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ...
        \App\Http\Middleware\RedirectIfTrialNotConfigured::class,
    ],
];
```

---

## フェーズ3: トライアル期間管理・通知

### 3.1 トライアル期限通知ジョブ

```php
// app/Jobs/SendTrialExpiryNotification.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Trial;
use Log;

class SendTrialExpiryNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    protected $trial;
    
    public function __construct(Trial $trial)
    {
        $this->trial = $trial;
    }
    
    public function handle()
    {
        Log::info("Sending trial expiry notification for trial {$this->trial->id}");
        
        $daysLeft = $this->trial->daysUntilExpiry();
        
        // 7日前、3日前、1日前、当日に通知
        if (in_array($daysLeft, [7, 3, 1, 0])) {
            // 実際の実装ではメール送信
            Log::info("Would send expiry notification to {$this->trial->email}", [
                'trial_id' => $this->trial->id,
                'days_left' => $daysLeft,
                'expires_at' => $this->trial->trial_ends_at
            ]);
        }
        
        // 期限切れの場合は自動でstatus更新
        if ($this->trial->isExpired() && $this->trial->status === 'active') {
            $this->trial->update(['status' => 'expired']);
            Log::warning("Trial {$this->trial->id} has expired");
        }
    }
}
```

### 3.2 スケジューラー設定

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // 毎日午前9時にトライアル期限チェック
    $schedule->job(new \App\Jobs\CheckAndNotifyTrialExpiries())
             ->dailyAt('09:00')
             ->withoutOverlapping();
}

// app/Jobs/CheckAndNotifyTrialExpiries.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Trial;
use Illuminate\Support\Facades\Bus;

class CheckAndNotifyTrialExpiries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModel;
    
    public function handle()
    {
        // 期限が近いトライアルを取得（7日以内）
        $expiringSoon = Trial::where('status', 'active')
                            ->whereNotNull('trial_ends_at')
                            ->where('trial_ends_at', '<=', now()->addDays(7))
                            ->where('trial_ends_at', '>=', now())
                            ->get();
        
        foreach ($expiringSoon as $trial) {
            Bus::dispatch(new \App\Jobs\SendTrialExpiryNotification($trial));
        }
        
        // 既に期限切れになったトライアルを処理
        $expired = Trial::where('status', 'active')
                       ->whereNotNull('trial_ends_at')
                       ->where('trial_ends_at', '<', now())
                       ->get();
                       
        foreach ($expired as $trial) {
            $trial->update(['status' => 'expired']);
            Bus::dispatch(new \App\Jobs\SendTrialExpiryNotification($trial));
        }
    }
}
```

---

## フェーズ4: トライアル→本契約自動移行

### 4.1 プラン選択・アップグレードフロー

```php
// app/Http/Controllers/TrialConversionController.php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Trial;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Hash;

class TrialConversionController extends Controller
{
    protected $stripe;
    
    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }
    
    /**
     * トライアルを本契約に変換
     */
    public function convert(Request $request, Trial $trial)
    {
        $request->validate([
            'plan' => ['required', Rule::in(['starter', 'standard', 'enterprise'])],
            'payment_method_id' => 'required|string',
            'billing_cycle' => ['required', Rule::in(['monthly', 'annually'])],
        ]);
        
        try {
            // 1. Stripeで顧客作成または取得
            $customer = $this->getOrCreateStripeCustomer($trial, $request->payment_method_id);
            
            // 2. サブスクリプション作成
            $subscription = $this->createSubscription(
                $customer, 
                $request->plan,
                $request->billing_cycle
            );
            
            // 3. トライアル施設を本契約施設にアップグレード
            $this->upgradeTrialFacility($trial->facility, $request->plan);
            
            // 4. トライアル記録更新
            $trial->update([
                'status' => 'converted',
                'converted_at' => now(),
                'plan' => $request->plan,
            ]);
            
            // 5. 完了メール送信
            $this->sendConversionCompleteEmail($trial, $subscription);
            
            return response()->json([
                'success' => true,
                'message' => 'トライアルから本契約への移行が完了しました。',
                'subscription_id' => $subscription->id
            ]);
            
        } catch (\Exception $e) {
            Log::error("Trial conversion failed for trial {$trial->id}", [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '契約手続き中にエラーが発生しました。'
            ], 500);
        }
    }
    
    private function getOrCreateStripeCustomer(Trial $trial, $paymentMethodId)
    {
        // 既存のStripe顧客を検索
        $customers = $this->stripe->customers->search([
            'query' => "email:'{$trial->email}'"
        ]);
        
        if ($customers->data[0] ?? null) {
            $customer = $customers->data[0];
            // 支払い方法を追加
            $this->stripe->paymentMethods->attach(
                $paymentMethodId,
                ['customer' => $customer->id]
            );
        } else {
            // 新規顧客作成
            $customer = $this->stripe->customers->create([
                'email' => $trial->email,
                'name' => $trial->contact_name,
                'payment_method' => $paymentMethodId,
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId
                ]
            ]);
        }
        
        return $customer;
    }
    
    private function createSubscription($customer, $plan, $billingCycle)
    {
        $priceIds = [
            'starter' => $billingCycle === 'monthly' 
                ? config('services.stripe.prices.starter_monthly') 
                : config('services.stripe.prices.starter_annual'),
            'standard' => $billingCycle === 'monthly' 
                ? config('services.stripe.prices.standard_monthly') 
                : config('services.stripe.prices.standard_annual'),
            'enterprise' => null // エンタープライズは見積もりベースのため別フロー
        ];
        
        if ($plan === 'enterprise') {
            throw new \Exception('Enterprise plan requires manual quote and contract');
        }
        
        return $this->stripe->subscriptions->create([
            'customer' => $customer->id,
            'items' => [[
                'price' => $priceIds[$plan],
            ]],
            'trial_period_days' => null, // トライアルは別途実装済み
            'metadata' => [
                'trial_id' => $trial->id,
                'facility_id' => $trial->facility?->id ?? null
            ]
        ]);
    }
    
    private function upgradeTrialFacility(Facility $facility, $plan)
    {
        // トライアル施設の制限を解除または機能を追加
        $updates = [
            'notes' => "本契約施設: {$plan}プラン",
        ];
        
        // エンタープライズ以外は機能制限を解除
        if ($plan !== 'enterprise') {
            $updates['is_active'] = true;
            // 実際の機能フラグは別途設定テーブルなどで管理
        }
        
        $facility->update($updates);
    }
    
    private function sendConversionCompleteEmail(Trial $trial, $subscription)
    {
        Log::info("Would send conversion complete email to {$trial->email}", [
            'trial_id' => $trial->id,
            'subscription_id' => $subscription->id,
            'plan' => $trial->plan
        ]);
    }
}
```

### 4.2 ルート定義

```php
// routes/api.php
use App\Http\Controllers\TrialConversionController;

Route::post('/trials/{trial}/convert', [TrialConversionController::class, 'convert'])
     ->name('trials.convert')
     ->middleware(['web', 'auth']); // 認証必要
```

---

## 回帰防止テスト計画

### 単体テスト

```php
// tests/Unit/TrialProvisioningServiceTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Trial;
use App\Services\TrialProvisioningService;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TrialProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_provisions_trial_facility_and_user()
    {
        $trial = Trial::factory()->create([
            'company_name' => 'テスト施設',
            'contact_name' => 'テスト 太郎',
            'email' => 'test@example.com',
        ]);
        
        $service = new TrialProvisioningService();
        $result = $service->provisionTrial($trial);
        
        $this->assertTrue($result);
        
        // トライアル施設が作成されること
        $this->assertDatabaseHas('facilities', [
            'name' => 'トライアル テスト施設',
            'email' => 'test@example.com'
        ]);
        
        // 管理者ユーザーが作成されること
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'テスト 太郎',
            'is_admin' => true
        ]);
        
        // トライアルステータスがアクティブになること
        $this->assertDatabaseHas('trials', [
            'id' => $trial->id,
            'status' => 'active',
            'facility_id' => !null
        ]);
    }
    
    public function test_seeds_sample_data_when_configured()
    {
        $trial = Trial::factory()->create([
            'trial_config' => ['seed_sample_data' => true]
        ]);
        
        $service = new TrialProvisioningService();
        $service->provisionTrial($trial);
        
        // サンプルデータが投入されること
        $this->assertDatabaseHas('residents', [
            'facility_id' => !null,
            'room_number' => '101',
            'name' => 'トライアル 太郎'
        ]);
        
        $this->assertDatabaseHas('monthly_invoices', [
            'facility_id' => !null,
            'billing_year_month' => now()->format('Y-m')
        ]);
    }
}

// tests/Unit/TrialConversionControllerTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Trial;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\StripeClient;

class TrialConversionControllerTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_converts_trial_to_paid_plan()
    {
        // Stripe APIをモック
        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->shouldReceive('customers->search')->andReturn(
            (object)['data' => []] // 顧客が存在しないケース
        );
        $stripeMock->shouldReceive('customers->create')->andReturn(
            (object)['id' => 'cus_test123']
        );
        $stripeMock->shouldReceive('subscriptions->create')->andReturn(
            (object)['id' => 'sub_test123']
        );
        
        $this->instance(StripeClient::class, $stripeMock);
        
        $facility = Facility::factory()->create();
        $trial = Trial::factory()->create([
            'facility_id' => $facility->id,
            'status' => 'active',
            'email' => 'test@example.com'
        ]);
        
        $user = User::factory()->create([
            'email' => 'test@example.com'
        ]);
        
        $this->actingAs($user);
        
        $response = $this->postJson("/api/trials/{$trial->id}/convert", [
            'plan' => 'standard',
            'payment_method_id' => 'pm_test_visa',
            'billing_cycle' => 'monthly'
        ]);
        
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);
        
        // トライアルがコンバート状態になること
        $this->assertDatabaseHas('trials', [
            'id' => $trial->id,
            'status' => 'converted',
            'plan' => 'standard'
        ]);
        
        // ファシリティがアップグレードされること
        $this->assertDatabaseHas('facilities', [
            'id' => $facility->id,
            'notes' => '本契約施設: standardプラン'
        ]);
    }
}
```

### フィーチャーテスト

```php
// tests/Feature/TrialFlowTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Trial;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;

class TrialFlowTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_complete_self_serve_trial_flow()
    {
        // 1. トライアル申請
        $response = $this->postJson('/api/trials', [
            'company_name' => 'テストケア施設',
            'contact_name' => '山田 施設長',
            'email' => 'facility@test-care.example.jp',
            'phone' => '03-1234-5678',
            'facility_type' => 'paid_elderly',
            'resident_capacity' => '50_100',
            'seed_sample_data' => true
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $trialId = json_decode($response->getContent())->trial_id;
        
        // 2. トライアルがプロビジョニングされること
        $this->assertDatabaseHas('trials', [
            'id' => $trialId,
            'status' => 'active'
        ]);
        
        $this->assertDatabaseHas('facilities', [
            'name' => 'トライアル テストケア施設'
        ]);
        
        // 3. 自動生成された管理者でログインできること
        $trial = Trial::find($trialId);
        $user = User::where('email', $trial->email)->first();
        
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('temporary_password', $user->password)); // 実際はパスワードリセットフロー
        
        // 4. サンプルデータが投入されていること
        $this->assertDatabaseHas('residents', [
            'facility_id' => $trial->facility_id,
            'room_number' => '101'
        ]);
        
        $this->assertDatabaseHas('monthly_invoices', [
            'facility_id' => $trial->facility_id,
            'billing_year_month' => now()->format('Y-m')
        ]);
        
        // 5. トライアル期限通知がスケジュールされること
        // （実際のテストではジョブのディスパッチをモック）
        
        // 6. トライアル→本契約変換フロー
        // （Stripeモックを使用した別のテストで確認）
    }
    
    public function test_trial_expiry_handling()
    {
        // 期限切れ直後のトライアルを作成
        $trial = Trial::factory()->create([
            'status' => 'active',
            'trial_ends_at' => now()->subHour(), // 1時間前に期限切れ
        ]);
        
        // スケジューラージョブを実行
        $this->artisan('trials:check-expiries');
        
        // トライアルが期限切れ状態になること
        $this->assertDatabaseHas('trials', [
            'id' => $trial->id,
            'status' => 'expired'
        ]);
    }
}
```

### 機能テスト（ブラウザテスト）

```php
// tests/Browser/TrialSignupTest.php
namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\Trial;

class TrialSignupTest extends DuskTestCase
{
    public function test_user_can_sign_up_for_trial_and_access_dashboard()
    {
        $this->browse(function (Browser $browser) {
            // トライアル申請ページへアクセス
            $browser->visit('/request/inquiry')
                   ->type('company', 'テストケア施設')
                   ->type('name', '山田 施設長')
                   ->type('email', 'test@example.com')
                   ->type('phone', '03-1234-5678')
                   ->select('facility_type', 'paid_elderly')
                   ->select('capacity', '50_100')
                   ->select('priority', 'efficiency')
                   ->select('budget', '15_35')
                   ->select('timeline', 'immediate') // トライアル希望
                   ->press('次へ')
                   ->press('お問い合わせを送信する')
                   ->assertSee('ありがとうございます'); // 成功メッセージ
                   
            // メールを確認してトライアル環境のURLを取得（実際はメールテスト用の仕組みが必要）
            // ここではデータベースから直接確認
            $this->assertDatabaseHas('trials', [
                'email' => 'test@example.com',
                'status' => 'active'
            ]);
            
            // トライアル環境にログインできることを確認
            $trial = Trial::where('email', 'test@example.com')->first();
            $browser->visit('/admin')
                   ->type('email', $trial->email)
                   ->type('password', 'temporary_password') // 実際はパスワードリセットフローをテスト
                   ->press('ログイン')
                   ->assertPathIs('/admin')
                   ->assertSee('トライアル情報'); // トライアルダッシュボードが表示される
        });
    }
}
```

---

## 実装スケジュールとリスク評価

| フェーズ | 期間 | 主なタスク | リスク | 緩和策 |
|----------|------|------------|--------|---------|
| フェーズ1 | 3-4日 | トライアルDB・サービス・コントローラー | データ整合性 | マイグレーションロールバックテスト |
| フェーズ2 | 2-3日 | オンボーディングウィザード・ミドルウェア | ユーザー体験の破綻 | 段階的ロールアウト（機能フラグ） |
| フェーズ3 | 2日 | 期限通知ジョブ・スケジューラー | ジョブ失敗による見逃し | 死レターキューとアラート |
| フェーズ4 | 3-4日 | 契約変換フロー・Stripe連携 | 支払い失敗 | サンドボックステスト・段階的本番移行 |
| テスト実装 | 全期間にわたる | ユニット・フィーチャー・ブラウザテスト | テストカバレッジ不足 | TDDアプローチで実装と並行 |

**総工数見積もり**: 2-3週間（1人月相当）

**依存関係**: 
- Stripeアカウントの設定（本番連携のため）
- メール送信サービス（SendGrid/Mailgun等）
- キューシステム（Redis/データベース）

**運用考慮点**:
- トライアル環境のリソース監視（不要なコスト発生防止）
- セキュリティ：トライアル環境から本番データへのアクセス遮断
- バックアップ：トライアルデータの定期的エクスポート（契約時引き継ぎ用）

---

## 成功指標（KPI）

1. **トライアル申請から環境利用までの時間**: 目標 <5分（現状 2営業日）
2. **トライアル登録完了率**: 目標 >70%（フォーム離脱率改善）
3. **トライアル→本契約転換率**: 目標 >25%（現状の営業プロセス比較）
4. **営業工数削減**: トライアル対応工数 80%削減
5. **顧客満足度（NPS）**: トライアル体験での向上目標 +15ポイント

この実装により、管理者はトライアル環境の自動プロビジョニング・期限管理によって、
本来の価値提供（機能改善・カスタム対応）に集中できるようになります。