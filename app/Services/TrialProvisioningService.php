<?php

namespace App\Services;

use App\Models\Trial;
use App\Models\Facility;
use App\Models\User;
use App\Models\Resident;
use App\Models\MonthlyInvoice;
use App\Enums\ResidentStatus;
use App\Mail\TrialProvisioningCompleteMail;
use App\Services\InvoiceCalculationService;
use App\Services\MailService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TrialProvisioningService
{
    public function __construct(
        private readonly MailService $mailService,
    ) {
    }
    /**
     * トライアル環境をプロビジョニング
     */
    public function provisionTrial(Trial $trial): bool
    {
        Log::info("Starting trial provisioning for trial {$trial->id}");
        
        try {
            // 1. トライアル施設の作成または取得
            $facility = $this->createOrGetTrialFacility($trial);
            Log::info("Facility created/retrieved: ID = " . ($facility->id ?? 'NULL') . ", Name = " . ($facility->name ?? 'NULL'));
            
            // 2. 管理者ユーザー作成
            $adminUser = $this->createAdminUser($trial, $facility);
            Log::info("Admin user created: ID = " . ($adminUser->id ?? 'NULL') . ", Email = " . ($adminUser->email ?? 'NULL'));
            
            // 3. サンプルデータ投入（設定に従って）
            Log::info("About to seed sample data with config: " . json_encode($trial->trial_config ?? []));
            $this->seedSampleData($facility, $trial->trial_config ?? []);
            Log::info("Finished seeding sample data");
            
            // 4. トライアル情報更新
            $updateData = [
                'facility_id' => $facility->id,
                'status' => 'active',
                'trial_started_at' => now(),
                'trial_ends_at' => now()->addDays(14), // 14日間トライアル
            ];
            Log::info("Updating trial with data: " . json_encode($updateData));
            $result = $trial->update($updateData);
            Log::info("Trial update result: " . ($result ? 'success' : 'failed'));
            
            // 5. 完了通知メール送信（プレースホルダー）
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
    
    /**
     * トライアル施設の作成または取得
     */
    public function createOrGetTrialFacilityForTesting(Trial $trial): Facility
    {
        return $this->createOrGetTrialFacility($trial);
    }
    
    private function createOrGetTrialFacility(Trial $trial): Facility
    {
        // トライアルIDを含む一意の施設名で分離（同一企業の複数申込でもデータが混在しない）
        $facilityName = "トライアル {$trial->company_name} ({$trial->id})";
        
        return Facility::firstOrCreate(
            ['name' => $facilityName],
            [
                'operator' => $trial->company_name,
                'postal_code' => '000-0000',
                'address' => 'トライアル環境のため住所は未設定',
                'phone' => $trial->phone ?? '000-0000-0000',
                'email' => $trial->email,
                'fax' => '000-0000-0000',
                'invoice_registration_number' => 'T' . str_pad($trial->id, 13, '0', STR_PAD_LEFT), // トライアル用仮番号（トライアルIDで一意）
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
    
    private function seedSampleData(Facility $facility, array $config): void
    {
        Log::info("seedSampleData called with config: " . json_encode($config));
        // サンプルデータ投入はオプション（設定で有効/無効切替可能）
        if (empty($config['seed_sample_data']) || !$config['seed_sample_data']) {
            Log::info("Sample data seeding skipped for facility {$facility->id}");
            return;
        }
        
        Log::info("Seeding sample data for facility {$facility->id}");
        
        // サンプル入居者データ作成
        $sampleResidents = [
            ['101', 'トライアル 太郎', 'トライアル タロウ', 60000, 25000],
            ['102', 'トライアル 花子', 'トライアル ハナコ', 55000, 25000],
        ];
        
        Log::info("Creating " . count($sampleResidents) . " sample residents");
        
        foreach ($sampleResidents as [$room, $name, $kana, $rent, $fee]) {
            Log::info("Creating resident: {$room}, {$name}");
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
        $invoiceService = app(InvoiceCalculationService::class);
        $currentMonth = now()->format('Y-m');
        Log::info("Generating invoices for month: {$currentMonth}");
        $invoiceService->generateForMonth($currentMonth, false, $facility->id);
        
        Log::info("Sample data seeding completed for facility {$facility->id}");
    }
    
    private function createAdminUser(Trial $trial, Facility $facility): User
    {
        $password = Str::random(12); // 自動生成パスワード
        
        // 既存ユーザー（同一メールアドレス）がいる場合はパスワードを変更せず流用
        $adminUser = User::firstOrCreate(
            ['email' => $trial->email],
            [
                'name' => $trial->contact_name,
                'password' => bcrypt($password),
                'is_admin' => true, // トライアルでは管理者権限付与
            ]
        );
        
        if ($adminUser->wasRecentlyCreated) {
            // メール送信が未実装のため、一時パスワードをtrial_configに退避
            // （本番ではメールで送信後、即座にクリアすること）
            $trial->update([
                'trial_config' => array_merge(
                    $trial->trial_config ?? [],
                    ['temp_password' => $password]
                ),
            ]);
        }
        
        return $adminUser;
    }
    
    private function sendProvisioningCompleteEmail(Trial $trial, User $adminUser): void
    {
        $tempPassword = $trial->trial_config['temp_password'] ?? null;

        $sent = $this->mailService->send(
            new TrialProvisioningCompleteMail($trial, $tempPassword ?? ''),
            $trial->email,
        );

        if ($sent && $tempPassword) {
            // 送信完了後は一時パスワードを削除
            $trial->update([
                'trial_config' => array_diff_key(
                    $trial->trial_config ?? [],
                    ['temp_password' => '']
                ),
            ]);
        }
    }
}