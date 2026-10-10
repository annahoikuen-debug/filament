<?php

use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\FacilityConfigService;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->facility = Facility::factory()->create();
    $this->resident = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '401',
        'name' => 'ジョブ入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-03-01',
    ]);
    $this->invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-03',
        'resident_id' => $this->resident->id,
        'facility_id' => $this->facility->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => \App\Enums\InvoiceStatus::Billed,
    ]);
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/invoices/2026-03'));
});

test('存在しない請求書IDでは警告ログのみで処理が終わること', function () {
    $pdfService = Mockery::mock(InvoicePdfService::class);
    $pdfService->shouldNotReceive('generateInvoicePdf');
    $configService = Mockery::mock(FacilityConfigService::class);
    $configService->shouldNotReceive('getConfig');

    (new GenerateInvoicePdfJob('2026-03', 999999))->handle($pdfService, $configService);

    expect(true)->toBeTrue();
});

test('キャッシュ済みPDFがある場合は再生成しないこと', function () {
    $cachePath = storage_path("app/invoices/2026-03/invoice_{$this->invoice->id}.pdf");
    File::ensureDirectoryExists(dirname($cachePath));
    File::put($cachePath, 'cached');

    $pdfService = Mockery::mock(InvoicePdfService::class);
    $pdfService->shouldNotReceive('generateInvoicePdf');
    $configService = Mockery::mock(FacilityConfigService::class);
    $configService->shouldNotReceive('getConfig');

    (new GenerateInvoicePdfJob('2026-03', $this->invoice->id))->handle($pdfService, $configService);

    expect(File::get($cachePath))->toBe('cached');
});

test('PDFを生成してキャッシュに保存すること', function () {
    $pdfService = Mockery::mock(InvoicePdfService::class);
    $pdf = Mockery::mock(Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('output')->andReturn('%PDF-1.4 fake');
    $pdfService->shouldReceive('generateInvoicePdf')->once()->andReturn($pdf);

    $configService = Mockery::mock(FacilityConfigService::class);
    $configService->shouldReceive('getConfig')->once()->with($this->facility->id)->andReturn(['name' => 'テスト']);

    $job = new GenerateInvoicePdfJob('2026-03', $this->invoice->id, $this->facility->id);
    $job->handle($pdfService, $configService);

    $cachePath = storage_path("app/invoices/2026-03/invoice_{$this->invoice->id}.pdf");
    expect(File::exists($cachePath))->toBeTrue()
        ->and(File::get($cachePath))->toBe('%PDF-1.4 fake');
});

test('生成失敗時は例外を再スローすること', function () {
    $pdfService = Mockery::mock(InvoicePdfService::class);
    $pdfService->shouldReceive('generateInvoicePdf')->once()->andThrow(new \RuntimeException('PDF generation failed'));

    $configService = Mockery::mock(FacilityConfigService::class);
    $configService->shouldNotReceive('getConfig');

    $job = new GenerateInvoicePdfJob('2026-03', $this->invoice->id);

    expect(fn () => $job->handle($pdfService, $configService))
        ->toThrow(\RuntimeException::class, 'PDF generation failed');
});
