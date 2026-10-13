<?php

namespace Tests\Unit\Services;

use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\BillingAnomalyDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingAnomalyDetectorTest extends TestCase
{
    use RefreshDatabase;

    protected BillingAnomalyDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new BillingAnomalyDetector;
    }

    protected function createResident(Facility $facility, array $overrides = []): Resident
    {
        return Resident::create(array_merge([
            'facility_id' => $facility->id,
            'room_number' => '101',
            'name' => '山田太郎',
            'name_kana' => 'ヤマダタロウ',
            'base_rent' => 100000,
            'base_management_fee' => 20000,
            'status' => 'active',
            'move_in_date' => '2020-01-01',
        ], $overrides));
    }

    protected function createInvoice(Resident $resident, string $yearMonth, array $overrides = []): MonthlyInvoice
    {
        return MonthlyInvoice::create(array_merge([
            'resident_id' => $resident->id,
            'facility_id' => $resident->facility_id,
            'billing_year_month' => $yearMonth,
            'rent_subtotal' => 100000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 0,
            'status' => 'unbilled',
            'payment_method' => 'bank_transfer',
            'tax_rate' => 10,
        ], $overrides));
    }

    public function test_在籍中なのに請求額0円の入居者をError検知する(): void
    {
        $facility = Facility::factory()->create();
        $resident = $this->createResident($facility);

        $result = $this->detector->detect('2026-10', $facility->id);

        $this->assertSame(1, $result['error_count']);
        $this->assertSame('zero_amount', $result['errors'][0]['rule']);
        $this->assertSame($resident->id, $result['errors'][0]['resident_id']);
        $this->assertFalse($this->detector->canProceedToClosing($result));
    }

    public function test_正常な請求はErrorなし(): void
    {
        $facility = Facility::factory()->create();
        $resident = $this->createResident($facility);
        $this->createInvoice($resident, '2026-10', ['service_subtotal' => 5000]);

        // 自費記録も追加（no_daily_charges 警告を回避）
        $item = ChargeItem::create(['name' => '昼食', 'default_price' => 500, 'is_active' => true, 'tax_type' => 'standard']);
        DailyCharge::create([
            'resident_id' => $resident->id,
            'facility_id' => $facility->id,
            'charge_item_id' => $item->id,
            'date' => '2026-10-05',
            'unit_price' => 500,
            'quantity' => 1,
        ]);

        $result = $this->detector->detect('2026-10', $facility->id);

        $this->assertSame(0, $result['error_count']);
        $this->assertSame(0, $result['warning_count']);
        $this->assertTrue($this->detector->canProceedToClosing($result));
    }

    public function test_月中入居なのに満額請求をError検知する(): void
    {
        $facility = Facility::factory()->create();
        // 月中入居（10/15入居）
        $resident = $this->createResident($facility, ['move_in_date' => '2026-10-15']);
        // 満額請求（日割りされていない）
        $this->createInvoice($resident, '2026-10', ['rent_subtotal' => 100000, 'management_fee_subtotal' => 20000]);

        $result = $this->detector->detect('2026-10', $facility->id);

        $periodErrors = array_filter($result['errors'], fn ($e) => $e['rule'] === 'period_mismatch');
        $this->assertNotEmpty($periodErrors);
    }

    public function test_自費記録0件の入居者をWarning検知する(): void
    {
        $facility = Facility::factory()->create();
        $resident = $this->createResident($facility);
        $this->createInvoice($resident, '2026-10');

        $result = $this->detector->detect('2026-10', $facility->id);

        $noChargeWarnings = array_filter($result['warnings'], fn ($w) => $w['rule'] === 'no_daily_charges');
        $this->assertNotEmpty($noChargeWarnings);

        // 自費記録があれば検知されない
        $item = ChargeItem::create(['name' => '昼食', 'default_price' => 500, 'is_active' => true, 'tax_type' => 'standard']);
        DailyCharge::create([
            'resident_id' => $resident->id,
            'facility_id' => $facility->id,
            'charge_item_id' => $item->id,
            'date' => '2026-10-05',
            'unit_price' => 500,
            'quantity' => 1,
        ]);

        $result2 = $this->detector->detect('2026-10', $facility->id);
        $noChargeWarnings2 = array_filter($result2['warnings'], fn ($w) => $w['rule'] === 'no_daily_charges');
        $this->assertEmpty($noChargeWarnings2);
    }

    public function test_前月比30パーセント以上の乖離をWarning検知する(): void
    {
        $facility = Facility::factory()->create();
        $resident = $this->createResident($facility);

        // 前月: 120,000円 → 当月: 200,000円（+66%）
        $this->createInvoice($resident, '2026-09', ['service_subtotal' => 0]);
        $this->createInvoice($resident, '2026-10', ['service_subtotal' => 80000]);

        $result = $this->detector->detect('2026-10', $facility->id);

        $deviations = array_filter($result['warnings'], fn ($w) => $w['rule'] === 'month_over_month_deviation');
        $this->assertNotEmpty($deviations);

        // 30%未満なら検知されない（前月120,000 → 当月140,000 = +16.7%）
        MonthlyInvoice::where('resident_id', $resident->id)->where('billing_year_month', '2026-10')->update(['service_subtotal' => 20000]);
        $result2 = $this->detector->detect('2026-10', $facility->id);
        $deviations2 = array_filter($result2['warnings'], fn ($w) => $w['rule'] === 'month_over_month_deviation');
        $this->assertEmpty($deviations2);
    }

    public function test_未確定の介護サービス請求をWarning検知する(): void
    {
        $facility = Facility::factory()->create();
        $resident = $this->createResident($facility);
        $this->createInvoice($resident, '2026-10', ['service_subtotal' => 10000]);

        // 未確定（下書き）の介護サービス請求を作成
        \App\Models\ServiceInvoice::create([
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-10',
            'service_type' => 'day_care',
            'service_type_label' => '通所介護',
            'external_system_name' => 'テストシステム',
            'external_invoice_number' => 'INV-001',
            'amount' => 50000,
            'tax_amount' => 5000,
            'tax_rate' => 10,
            'status' => 'draft',
        ]);

        $result = $this->detector->detect('2026-10', $facility->id);

        $draftWarnings = array_filter($result['warnings'], fn ($w) => $w['rule'] === 'draft_care_invoice');
        $this->assertNotEmpty($draftWarnings);
    }

    public function test_サマリー文字列が正しく生成される(): void
    {
        $facility = Facility::factory()->create();
        $resident = $this->createResident($facility);

        $result = $this->detector->detect('2026-10', $facility->id);
        $summary = $this->detector->summarize($result);

        $this->assertStringContainsString('Error 1件', $summary);
    }
}
