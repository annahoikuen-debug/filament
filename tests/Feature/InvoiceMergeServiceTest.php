<?php

use App\Enums\InvoiceStatus;
use App\Enums\ServiceInvoiceStatus;
use App\Enums\ServiceType;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\ServiceInvoice;
use App\Services\InvoiceMergeService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->facility = Facility::create([
        'name' => 'テスト施設',
        'operator' => 'テスト運営',
        'postal_code' => '100-0001',
        'address' => '東京都千代田区',
        'invoice_registration_number' => 'T1234567890123',
    ]);

    $this->resident = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '101',
        'name' => 'テスト入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    $this->housingInvoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'facility_id' => $this->facility->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 5000,
        'total_amount' => 75000,
        'status' => InvoiceStatus::Billed,
    ]);

    $this->service = app(InvoiceMergeService::class);
});

test('介護サービスPDFなしの場合は住居費請求書のみ返すこと', function () {
    $content = $this->service->generateAndSaveMergedInvoice($this->housingInvoice);

    expect($content)->toBeString()
        ->and(strlen($content))->toBeGreaterThan(100); // PDFとして妥当なサイズ
});

test('介護サービスPDFありの場合は結合を試みること', function () {
    // ダミーPDFファイルを作成
    $dummyPdf = '%PDF-1.4\n%EOF\n';
    Storage::disk('local')->put('service-invoices/visiting_care.pdf', $dummyPdf);
    Storage::disk('local')->put('service-invoices/day_care.pdf', $dummyPdf);

    // ServiceInvoiceレコード作成
    ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => ServiceType::VisitingCare,
        'service_type_label' => '訪問介護',
        'amount' => 45000,
        'tax_amount' => 4500,
        'tax_rate' => 10.00,
        'pdf_path' => 'service-invoices/visiting_care.pdf',
        'pdf_original_name' => '訪問介護請求書.pdf',
        'status' => ServiceInvoiceStatus::Confirmed,
    ]);

    ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => ServiceType::DayCare,
        'service_type_label' => '通所介護',
        'amount' => 30000,
        'tax_amount' => 3000,
        'tax_rate' => 10.00,
        'pdf_path' => 'service-invoices/day_care.pdf',
        'pdf_original_name' => '通所介護請求書.pdf',
        'status' => ServiceInvoiceStatus::Confirmed,
    ]);

    $content = $this->service->generateAndSaveMergedInvoice($this->housingInvoice);

    expect($content)->toBeString()
        ->and(strlen($content))->toBeGreaterThan(100);

    // クリーンアップ
    Storage::disk('local')->delete('service-invoices/visiting_care.pdf');
    Storage::disk('local')->delete('service-invoices/day_care.pdf');
});

test('getCareServicePdfsが正しくPDFパスを取得すること', function () {
    Storage::disk('local')->put('service-invoices/test.pdf', '%PDF-1.4\n%EOF\n');

    ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => ServiceType::VisitingCare,
        'service_type_label' => '訪問介護',
        'amount' => 45000,
        'tax_amount' => 4500,
        'tax_rate' => 10.00,
        'pdf_path' => 'service-invoices/test.pdf',
        'pdf_original_name' => 'test.pdf',
        'status' => ServiceInvoiceStatus::Confirmed,
    ]);

    // 下書きは除外される
    ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => ServiceType::DayCare,
        'service_type_label' => '通所介護',
        'amount' => 30000,
        'tax_amount' => 3000,
        'tax_rate' => 10.00,
        'pdf_path' => 'service-invoices/test.pdf',
        'pdf_original_name' => 'test.pdf',
        'status' => ServiceInvoiceStatus::Draft,
    ]);

    $pdfs = $this->service->getCareServicePdfs($this->housingInvoice);

    expect($pdfs)->toHaveKey('visiting_care')
        ->and($pdfs)->not->toHaveKey('day_care')
        ->and(File::exists($pdfs['visiting_care']))->toBeTrue();

    Storage::disk('local')->delete('service-invoices/test.pdf');
});

test('異なる月のサービス請求は取得されないこと', function () {
    Storage::disk('local')->put('service-invoices/next_month.pdf', '%PDF-1.4\n%EOF\n');

    ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-11', // 異なる月
        'service_type' => ServiceType::VisitingCare,
        'service_type_label' => '訪問介護',
        'amount' => 45000,
        'tax_amount' => 4500,
        'tax_rate' => 10.00,
        'pdf_path' => 'service-invoices/next_month.pdf',
        'pdf_original_name' => 'next_month.pdf',
        'status' => ServiceInvoiceStatus::Confirmed,
    ]);

    $pdfs = $this->service->getCareServicePdfs($this->housingInvoice);

    expect($pdfs)->toBeEmpty();

    Storage::disk('local')->delete('service-invoices/next_month.pdf');
});
