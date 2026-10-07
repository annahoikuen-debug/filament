<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->service = new InvoicePdfService;

    $this->resident = Resident::create([
        'room_number' => '101',
        'name' => 'PDFテスト入居者',
        'move_in_date' => '2026-01-01',
    ]);

    $this->invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 65000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 3000,
        'status' => InvoiceStatus::Unbilled,
    ]);
});

test('請求書PDFが正常にビルドできること', function () {
    $pdf = $this->service->generateInvoicePdf($this->invoice);

    expect($pdf)->not->toBeNull();
    $output = $pdf->output();
    expect(strlen($output))->toBeGreaterThan(100);
});

test('領収書PDFが入金済みデータから正常にビルドできること', function () {
    $this->invoice->markAsPaid(PaymentMethod::BankTransfer);

    $pdf = $this->service->generateReceiptPdf($this->invoice);

    expect($pdf)->not->toBeNull();
    $output = $pdf->output();
    expect(strlen($output))->toBeGreaterThan(100);
});

test('全入居者分の請求書PDF一括ZIPアーカイブが生成できること', function () {
    $zipPath = $this->service->generateMonthlyZip('2026-10');

    expect(File::exists($zipPath))->toBeTrue();

    // ZIPファイルを開いて検証
    $zip = new ZipArchive;
    expect($zip->open($zipPath))->toBeTrue();
    expect($zip->numFiles)->toBeGreaterThanOrEqual(1);

    $zip->close();
    File::delete($zipPath);
});
