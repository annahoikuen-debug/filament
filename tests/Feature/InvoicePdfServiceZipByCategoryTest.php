<?php

use App\Enums\InvoiceStatus;
use App\Enums\ServiceInvoiceStatus;
use App\Enums\ServiceType;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\ServiceInvoice;
use App\Services\InvoicePdfService;
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

    $this->service = app(InvoicePdfService::class);
});

test('カテゴリ別ZIPが生成されること', function () {
    $zipPath = $this->service->generateMonthlyZipByCategory('2026-10');

    expect(File::exists($zipPath))->toBeTrue()
        ->and(File::size($zipPath))->toBeGreaterThan(0);

    // ZIPの中身を確認
    $zip = new \ZipArchive;
    $zip->open($zipPath);
    expect($zip->numFiles)->toBeGreaterThan(0);

    // 住居費フォルダがあること
    $hasHousing = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_starts_with($name, '住居費/')) {
            $hasHousing = true;
            break;
        }
    }
    expect($hasHousing)->toBeTrue();

    // サマリーCSVがあること
    $hasSummary = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_starts_with($name, 'サマリー/')) {
            $hasSummary = true;
            break;
        }
    }
    expect($hasSummary)->toBeTrue();

    $zip->close();

    // クリーンアップ
    if (File::exists($zipPath)) {
        File::delete($zipPath);
    }
});

test('介護サービスPDFがある場合は種別フォルダに入ること', function () {
    // ダミーPDF作成
    Storage::disk('local')->put('service-invoices/visiting_care.pdf', '%PDF-1.4\n%EOF\n');
    Storage::disk('local')->put('service-invoices/day_care.pdf', '%PDF-1.4\n%EOF\n');

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

    $zipPath = $this->service->generateMonthlyZipByCategory('2026-10');

    expect(File::exists($zipPath))->toBeTrue();

    // ZIPの中身を確認
    $zip = new \ZipArchive;
    $zip->open($zipPath);

    $hasVisitingCare = false;
    $hasDayCare = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_contains($name, '訪問介護/')) {
            $hasVisitingCare = true;
        }
        if (str_contains($name, '通所介護/')) {
            $hasDayCare = true;
        }
    }
    expect($hasVisitingCare)->toBeTrue()
        ->and($hasDayCare)->toBeTrue();

    $zip->close();

    // クリーンアップ
    if (File::exists($zipPath)) {
        File::delete($zipPath);
    }
    Storage::disk('local')->delete('service-invoices/visiting_care.pdf');
    Storage::disk('local')->delete('service-invoices/day_care.pdf');
});

test('下書きステータスの介護サービスは除外されること', function () {
    Storage::disk('local')->put('service-invoices/draft.pdf', '%PDF-1.4\n%EOF\n');

    ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => ServiceType::VisitingCare,
        'service_type_label' => '訪問介護',
        'amount' => 45000,
        'tax_amount' => 4500,
        'tax_rate' => 10.00,
        'pdf_path' => 'service-invoices/draft.pdf',
        'pdf_original_name' => 'draft.pdf',
        'status' => ServiceInvoiceStatus::Draft,
    ]);

    $zipPath = $this->service->generateMonthlyZipByCategory('2026-10');

    $zip = new \ZipArchive;
    $zip->open($zipPath);

    $hasVisitingCare = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_contains($name, '訪問介護/')) {
            $hasVisitingCare = true;
            break;
        }
    }
    expect($hasVisitingCare)->toBeFalse();

    $zip->close();

    if (File::exists($zipPath)) {
        File::delete($zipPath);
    }
    Storage::disk('local')->delete('service-invoices/draft.pdf');
});

test('異なる月の介護サービスは除外されること', function () {
    Storage::disk('local')->put('service-invoices/next_month.pdf', '%PDF-1.4\n%EOF\n');

    ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-11',
        'service_type' => ServiceType::VisitingCare,
        'service_type_label' => '訪問介護',
        'amount' => 45000,
        'tax_amount' => 4500,
        'tax_rate' => 10.00,
        'pdf_path' => 'service-invoices/next_month.pdf',
        'pdf_original_name' => 'next_month.pdf',
        'status' => ServiceInvoiceStatus::Confirmed,
    ]);

    $zipPath = $this->service->generateMonthlyZipByCategory('2026-10');

    $zip = new \ZipArchive;
    $zip->open($zipPath);

    $hasVisitingCare = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_contains($name, '訪問介護/')) {
            $hasVisitingCare = true;
            break;
        }
    }
    expect($hasVisitingCare)->toBeFalse();

    $zip->close();

    if (File::exists($zipPath)) {
        File::delete($zipPath);
    }
    Storage::disk('local')->delete('service-invoices/next_month.pdf');
});

test('includeMerged=falseで統合請求書が含まれないこと', function () {
    Storage::disk('local')->put('service-invoices/visiting_care.pdf', '%PDF-1.4\n%EOF\n');

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

    // includeMerged=false
    $zipPath = $this->service->generateMonthlyZipByCategory('2026-10', null, false);

    $zip = new \ZipArchive;
    $zip->open($zipPath);

    $hasMerged = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_starts_with($name, '統合請求書/')) {
            $hasMerged = true;
            break;
        }
    }
    expect($hasMerged)->toBeFalse();

    $zip->close();

    if (File::exists($zipPath)) {
        File::delete($zipPath);
    }
    Storage::disk('local')->delete('service-invoices/visiting_care.pdf');
});

test('サマリーCSVに正しいデータが含まれること', function () {
    Storage::disk('local')->put('service-invoices/visiting_care.pdf', '%PDF-1.4\n%EOF\n');

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

    $zipPath = $this->service->generateMonthlyZipByCategory('2026-10');

    $zip = new \ZipArchive;
    $zip->open($zipPath);

    $csvContent = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_starts_with($name, 'サマリー/')) {
            $csvContent = $zip->getFromIndex($i);
            break;
        }
    }
    expect($csvContent)->not->toBeEmpty()
        ->and($csvContent)->toContain('2026-10')
        ->and($csvContent)->toContain('101')
        ->and($csvContent)->toContain('テスト入居者')
        ->and($csvContent)->toContain('住居費')
        ->and($csvContent)->toContain('家賃')
        ->and($csvContent)->toContain('管理費')
        ->and($csvContent)->toContain('介護保険')
        ->and($csvContent)->toContain('訪問介護');

    $zip->close();

    if (File::exists($zipPath)) {
        File::delete($zipPath);
    }
    Storage::disk('local')->delete('service-invoices/visiting_care.pdf');
});