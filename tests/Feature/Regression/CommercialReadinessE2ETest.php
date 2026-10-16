<?php

namespace Tests\Feature\Regression;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceInvoiceStatus;
use App\Enums\ServiceType;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\ResidentBankAccount;
use App\Models\ServiceInvoice;
use App\Models\User;
use App\Policies\MonthlyInvoicePolicy;
use App\Services\Audit\AuditLogExportService;
use App\Services\Auth\TwoFactorAuthService;
use App\Services\Banking\ZenginFormatService;
use App\Services\Billing\OverdueClassificationService;
use App\Services\Denchoho\ElectronicRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CommercialReadinessE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_full_commercial_workflow_end_to_end(): void
    {
        // 1. 施設・ユーザー（複数ロール）作成
        $facility = Facility::factory()->create(['name' => '商用実務総合ケアホーム']);

        $corporateAdmin = User::factory()->create([
            'role' => 'corporate_admin',
            'facility_id' => null,
            'is_admin' => true,
        ]);

        $careStaff = User::factory()->create([
            'role' => 'care_staff',
            'facility_id' => $facility->id,
            'is_admin' => true,
        ]);

        $accountant = User::factory()->create([
            'role' => 'accountant',
            'facility_id' => $facility->id,
            'is_admin' => true,
        ]);

        // 2. 入居者と暗号化口座登録（Step 1-4）
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'name' => '実務総合太郎',
            'name_kana' => 'ジツムソウゴウタロウ',
        ]);

        $bankAccount = ResidentBankAccount::create([
            'resident_id' => $resident->id,
            'bank_code' => '0005',
            'branch_code' => '012',
            'account_type' => ResidentBankAccount::ACCOUNT_TYPE_ORDINARY,
            'account_number' => '1234567',
            'account_holder_kana' => 'ジツムソウゴウタロウ',
            'is_active' => true,
        ]);

        $this->assertNotEquals('1234567', \DB::table('resident_bank_accounts')->where('id', $bankAccount->id)->value('account_number'));
        $this->assertEquals('1234567', $bankAccount->fresh()->account_number);

        // 3. 介護保険請求の取込（Step 29-32）
        $serviceInvoice = ServiceInvoice::create([
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'billing_year_month' => now()->format('Y-m'),
            'service_type' => ServiceType::VisitingCare,
            'service_type_label' => ServiceType::VisitingCare->getLabel(),
            'amount' => 9500,
            'tax_amount' => 0,
            'tax_rate' => 0,
            'status' => ServiceInvoiceStatus::Confirmed,
        ]);

        // 4. 月次請求書作成と権限ガード（Step 9-12）
        $monthlyInvoice = MonthlyInvoice::factory()->create([
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'billing_year_month' => now()->format('Y-m'),
            'rent_subtotal' => 60000,
            'management_fee_subtotal' => 25000,
            'service_subtotal' => 9500,
            'status' => InvoiceStatus::Unbilled,
        ]);

        $policy = new MonthlyInvoicePolicy();
        // 介護スタッフは確定不可、経理は確定可能
        $this->assertFalse($policy->finalize($careStaff, $monthlyInvoice));
        $this->assertTrue($policy->finalize($accountant, $monthlyInvoice));

        // 請求確定
        $monthlyInvoice->status = InvoiceStatus::Billed;
        $monthlyInvoice->save();

        // 5. 全銀振替ファイル生成（Step 4）
        $zenginService = app(ZenginFormatService::class);
        $header = [
            'delegate_code' => '1234567890',
            'delegate_name_kana' => 'ケアホーム',
            'transfer_date' => now()->format('Ymd'),
            'bank_code' => '0005',
            'bank_name_kana' => 'ミツビシ',
            'branch_code' => '012',
            'branch_name_kana' => 'シテン',
            'account_type' => '1',
            'account_number' => '9999999',
        ];
        $zenginOutput = $zenginService->generate($header, [
            ['invoice' => $monthlyInvoice, 'account' => $bankAccount],
        ]);
        $this->assertStringContainsString('ジツムソウゴウタロウ', mb_convert_encoding($zenginOutput, 'UTF-8', 'CP932'));

        // 6. 電帳法メタデータ・改ざん防止ハッシュ記録（Step 13-16）
        $dummyPdf = storage_path('app/dummy_commercial.pdf');
        File::put($dummyPdf, '%PDF-1.4 commercial dummy content');

        $denchohoService = new ElectronicRecordService();
        $record = $denchohoService->recordInvoicePdf($monthlyInvoice, $dummyPdf);
        $this->assertTrue($record->verifyIntegrity());
        File::delete($dummyPdf);

        // 7. 監査ログ記録 & CSVエクスポート（Step 17-20）
        activity('commercial_e2e')
            ->causedBy($accountant)
            ->log('請求確定および全銀出力完了');

        $auditService = new AuditLogExportService();
        $csv = $auditService->exportCsv(logName: 'commercial_e2e');
        $this->assertStringContainsString('請求確定および全銀出力完了', $csv);

        // 8. 2FA認証検証（Step 21-24）
        $twoFactorService = new TwoFactorAuthService();
        $secret = $twoFactorService->generateSecretKey();
        $this->assertEquals(32, strlen($secret));

        // 9. 滞納判定ロジック検証（Step 25-28: 期日から45日経過で reminder）
        $overdueService = new OverdueClassificationService();
        $due = \Carbon\Carbon::parse($monthlyInvoice->billing_year_month . '-27');
        $overdueRes = $overdueService->classify($monthlyInvoice, (clone $due)->addDays(45));
        $this->assertEquals(OverdueClassificationService::STAGE_REMINDER, $overdueRes['stage']);
    }
}
