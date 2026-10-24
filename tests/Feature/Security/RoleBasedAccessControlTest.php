<?php

namespace Tests\Feature\Security;

use App\Enums\InvoiceStatus;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Policies\MonthlyInvoicePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facilityA;

    private Facility $facilityB;

    private User $corporateAdmin;

    private User $facilityAdmin;

    private User $accountant;

    private User $careStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facilityA = Facility::factory()->create(['name' => '施設A']);
        $this->facilityB = Facility::factory()->create(['name' => '施設B']);

        $this->corporateAdmin = User::factory()->create([
            'role' => 'corporate_admin',
            'facility_id' => null,
            'is_admin' => true,
        ]);

        $this->facilityAdmin = User::factory()->create([
            'role' => 'facility_admin',
            'facility_id' => $this->facilityA->id,
            'is_admin' => true,
        ]);

        $this->accountant = User::factory()->create([
            'role' => 'accountant',
            'facility_id' => $this->facilityA->id,
            'is_admin' => true,
        ]);

        $this->careStaff = User::factory()->create([
            'role' => 'care_staff',
            'facility_id' => $this->facilityA->id,
            'is_admin' => true,
        ]);
    }

    public function test_care_staff_cannot_finalize_invoices(): void
    {
        $policy = new MonthlyInvoicePolicy;
        $resident = Resident::factory()->create(['facility_id' => $this->facilityA->id]);
        $invoice = MonthlyInvoice::factory()->create([
            'facility_id' => $this->facilityA->id,
            'resident_id' => $resident->id,
            'status' => InvoiceStatus::Unbilled,
        ]);

        // 現場介護職は確定権限なし
        $this->assertFalse($policy->finalize($this->careStaff, $invoice));

        // 施設管理者、経理、法人管理者は確定可能
        $this->assertTrue($policy->finalize($this->facilityAdmin, $invoice));
        $this->assertTrue($policy->finalize($this->accountant, $invoice));
        $this->assertTrue($policy->finalize($this->corporateAdmin, $invoice));
    }

    public function test_care_staff_cannot_export_zengin_or_mark_paid(): void
    {
        $policy = new MonthlyInvoicePolicy;

        $this->assertFalse($policy->exportZengin($this->careStaff));
        $this->assertFalse($policy->markAsPaid($this->careStaff));

        $this->assertTrue($policy->exportZengin($this->accountant));
        $this->assertTrue($policy->markAsPaid($this->accountant));
    }

    public function test_facility_admin_cannot_finalize_other_facility_invoice(): void
    {
        $policy = new MonthlyInvoicePolicy;
        $residentB = Resident::factory()->create(['facility_id' => $this->facilityB->id]);
        $invoiceB = MonthlyInvoice::factory()->create([
            'facility_id' => $this->facilityB->id,
            'resident_id' => $residentB->id,
            'status' => InvoiceStatus::Unbilled,
        ]);

        // 施設A管理者は施設Bの請求書を確定できない
        $this->assertFalse($policy->finalize($this->facilityAdmin, $invoiceB));

        // 法人管理者は全施設確定可能
        $this->assertTrue($policy->finalize($this->corporateAdmin, $invoiceB));
    }
}
