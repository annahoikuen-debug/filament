<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateMonthlyZipJob;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GenerateMonthlyZipJobTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->facility = Facility::factory()->create();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/temp'));
        parent::tearDown();
    }

    public function test_zero_invoices_completes_with_empty_result(): void
    {
        $job = new GenerateMonthlyZipJob('2099-01', null, 'test-job-zero');
        $job->handle(app(\App\Services\InvoicePdfService::class), app(\App\Services\FacilityConfigService::class));

        $progress = GenerateMonthlyZipJob::getProgress('test-job-zero');

        $this->assertNotNull($progress);
        $this->assertSame('completed', $progress['status']);
        $this->assertSame('対象データがありません', $progress['message']);
        $this->assertSame('', GenerateMonthlyZipJob::getResult('test-job-zero'));
    }

    public function test_generates_zip_with_invoices(): void
    {
        $resident = Resident::create([
            'facility_id' => $this->facility->id,
            'name' => 'ZIP入居者',
            'name_kana' => 'ジップジュウニンシャ',
            'room_number' => '501',
            'status' => 'active',
            'base_rent' => 50000,
            'base_management_fee' => 20000,
        ]);
        MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-03',
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 0,
            'total_amount' => 70000,
            'status' => 'billed',
        ]);

        $job = new GenerateMonthlyZipJob('2026-03', $this->facility->id, 'test-job-zip');
        $job->handle(app(\App\Services\InvoicePdfService::class), app(\App\Services\FacilityConfigService::class));

        $zipPath = storage_path('app/temp/請求書一括_2026-03.zip');
        $this->assertFileExists($zipPath);
        $this->assertGreaterThan(0, filesize($zipPath));

        $progress = GenerateMonthlyZipJob::getProgress('test-job-zip');
        $this->assertSame('completed', $progress['status']);
        $this->assertSame('ZIP生成完了', $progress['message']);
        $this->assertSame($zipPath, GenerateMonthlyZipJob::getResult('test-job-zip'));
    }

    public function test_failure_sets_failed_progress_and_rethrows(): void
    {
        // 存在しない年月だが、レコード自体を生成せずに失敗させるため
        // MonthlyInvoice のクエリは成功する → generator をモックして例外を投げさせる
        $resident = Resident::create([
            'facility_id' => $this->facility->id,
            'name' => '失敗入居者',
            'name_kana' => 'シッパイジュウニンシャ',
            'room_number' => '601',
            'status' => 'active',
            'base_rent' => 50000,
            'base_management_fee' => 20000,
        ]);
        MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-03',
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 0,
            'total_amount' => 70000,
            'status' => 'billed',
        ]);

        $generator = \Mockery::mock(\App\Services\Pdf\InvoicePdfGenerator::class);
        $generator->shouldReceive('generateMonthlyZipStream')
            ->once()
            ->andThrow(new \RuntimeException('ZIP書き込みエラー'));
        $this->app->instance(\App\Services\Pdf\InvoicePdfGenerator::class, $generator);

        $job = new GenerateMonthlyZipJob('2026-03', $this->facility->id, 'test-job-fail');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ZIP書き込みエラー');

        try {
            $job->handle(app(\App\Services\InvoicePdfService::class), app(\App\Services\FacilityConfigService::class));
        } finally {
            $progress = GenerateMonthlyZipJob::getProgress('test-job-fail');
            $this->assertSame('failed', $progress['status']);
            $this->assertSame('エラー: ZIP書き込みエラー', $progress['message']);
            $this->assertNull(GenerateMonthlyZipJob::getResult('test-job-fail'));
        }
    }

    public function test_static_clear_progress_removes_cache(): void
    {
        \Illuminate\Support\Facades\Cache::put('zip_progress_clear-test', ['status' => 'completed'], 60);
        \Illuminate\Support\Facades\Cache::put('zip_result_clear-test', '/tmp/x.zip', 60);

        GenerateMonthlyZipJob::clearProgress('clear-test');

        $this->assertNull(GenerateMonthlyZipJob::getProgress('clear-test'));
        $this->assertNull(GenerateMonthlyZipJob::getResult('clear-test'));
    }
}
