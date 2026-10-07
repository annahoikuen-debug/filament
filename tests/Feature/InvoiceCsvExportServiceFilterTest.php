<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCsvExportService;

beforeEach(function () {
    $this->service = new InvoiceCsvExportService;

    $this->resident = Resident::create([
        'room_number' => '701',
        'name' => 'CSVフィルタテスト',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);
});

test('Unbilled請求書が会計CSVに含まれないこと', function () {
    MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $csv = $this->service->exportAccountingJournalCsv('2026-10');

    // ヘッダー行のみでデータ行がないこと
    $lines = array_filter(explode("\n", $csv));
    expect(count($lines))->toBe(1);
});

test('Billed請求書が会計CSVに含まれること', function () {
    MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => InvoiceStatus::Billed,
    ]);

    $csv = $this->service->exportAccountingJournalCsv('2026-10');

    expect(str_contains($csv, '賃貸料収入'))->toBeTrue()
        ->and(str_contains($csv, '50000'))->toBeTrue();
});

test('Paid請求書が会計CSVに含まれること', function () {
    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $invoice->markAsPaid(PaymentMethod::BankTransfer, '2026-11-05');

    $csv = $this->service->exportAccountingJournalCsv('2026-10');

    expect(str_contains($csv, '50000'))->toBeTrue();
});

test('金額と仕訳が正しく出力されること', function () {
    MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 3000,
        'total_amount' => 73000,
        'status' => InvoiceStatus::Billed,
    ]);

    $csv = $this->service->exportAccountingJournalCsv('2026-10');

    expect(str_contains($csv, '50000'))->toBeTrue()
        ->and(str_contains($csv, '20000'))->toBeTrue()
        ->and(str_contains($csv, '3000'))->toBeTrue()
        ->and(str_contains($csv, '売掛金'))->toBeTrue()
        ->and(str_contains($csv, '701'))->toBeTrue();
});
