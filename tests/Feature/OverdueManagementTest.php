<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\Billing\OverdueClassificationService;
use App\Services\Pdf\OverdueNoticePdfService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OverdueManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_classification_stages_by_days(): void
    {
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create(['facility_id' => $facility->id]);

        $invoice = MonthlyInvoice::factory()->create([
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-08',
            'status' => InvoiceStatus::Billed,
        ]);

        $service = new OverdueClassificationService;

        // 1. 期日当日（2026-08-27） -> 期日内
        $asOfDate1 = Carbon::parse('2026-08-27');
        $res1 = $service->classify($invoice, $asOfDate1);
        $this->assertEquals(OverdueClassificationService::STAGE_NORMAL, $res1['stage']);

        // 2. 15日経過（2026-09-11） -> 注意 (notice)
        $asOfDate2 = Carbon::parse('2026-09-11');
        $res2 = $service->classify($invoice, $asOfDate2);
        $this->assertEquals(OverdueClassificationService::STAGE_NOTICE, $res2['stage']);
        $this->assertEquals(15, $res2['days_overdue']);

        // 3. 45日経過（2026-10-11） -> 要督促 (reminder)
        $asOfDate3 = Carbon::parse('2026-10-11');
        $res3 = $service->classify($invoice, $asOfDate3);
        $this->assertEquals(OverdueClassificationService::STAGE_REMINDER, $res3['stage']);

        // 4. 70日経過（2026-11-05） -> 警告 (warning)
        $asOfDate4 = Carbon::parse('2026-11-05');
        $res4 = $service->classify($invoice, $asOfDate4);
        $this->assertEquals(OverdueClassificationService::STAGE_WARNING, $res4['stage']);
    }

    public function test_overdue_notice_pdf_generation(): void
    {
        $facility = Facility::factory()->create(['name' => 'サンシャインケア施設']);
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'name' => '督促対象太郎',
        ]);

        $invoice = MonthlyInvoice::factory()->create([
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-08',
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 0,
            'status' => InvoiceStatus::Billed,
        ]);

        $service = new OverdueNoticePdfService;
        $pdfPath = $service->generateNoticePdf($invoice, 'reminder');

        $this->assertTrue(File::exists($pdfPath));
        $this->assertGreaterThan(100, File::size($pdfPath));

        // 生成後クリーンアップ
        File::delete($pdfPath);
    }
}
