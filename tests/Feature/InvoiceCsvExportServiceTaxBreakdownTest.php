<?php

use App\Enums\InvoiceStatus;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCsvExportService;

beforeEach(function () {
    $this->facility = Facility::factory()->create();
    $this->resident = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '501',
        'name' => 'インボイスCSVテスト太郎',
        'name_kana' => 'インボイスシーエスブイテストタロウ',
        'base_rent' => 70000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
    ]);
    $this->service = new InvoiceCsvExportService;
});

test('新CSVヘッダーが19列でUTF-8 BOM付き出力されること', function () {
    $invoice = MonthlyInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 70000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 10000,
        'total_amount' => 114000,
        'tax_amount' => 4000,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $csv = $this->service->exportMonthlyListCsv('2026-10');

    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();

    $cleanCsv = str_replace("\xEF\xBB\xBF", '', $csv);
    $lines = explode("\n", trim($cleanCsv));
    $headers = str_getcsv($lines[0]);
    expect(count($headers))->toBe(20) // 領収書番号まで20項目
        ->and($headers[0])->toBe('請求年月')
        ->and($headers[5])->toBe('管理費(標準税率10%対象)')
        ->and($headers[6])->toBe('自費(標準税率10%対象)')
        ->and($headers[7])->toBe('自費(軽減税率8%対象)')
        ->and($headers[8])->toBe('自費(非課税)')
        ->and($headers[9])->toBe('標準税率10%対象額合計')
        ->and($headers[10])->toBe('軽減税率8%対象額合計')
        ->and($headers[11])->toBe('非課税額合計')
        ->and($headers[12])->toBe('消費税額(10%)')
        ->and($headers[13])->toBe('消費税額(8%)')
        ->and($headers[14])->toBe('消費税額合計');
});

test('tax_breakdownにstandardとreducedがある場合に税率ごとの金額が正しく展開されること', function () {
    MonthlyInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 70000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 15000,
        'total_amount' => 120400,
        'tax_amount' => 5400,
        'status' => InvoiceStatus::Billed,
        'tax_breakdown' => [
            'standard' => [
                'taxable_amount' => 40000, // 管理費30000 + 自費(10%)10000
                'rate' => 10,
                'tax_amount' => 4000,
            ],
            'reduced' => [
                'taxable_amount' => 5000, // 自費(8%)5000
                'rate' => 8,
                'tax_amount' => 400,
            ],
            'non_taxable' => [
                'amount' => 70000,
            ],
        ],
    ]);

    $csv = $this->service->exportMonthlyListCsv('2026-10');
    $lines = explode("\n", trim($csv));
    $row = str_getcsv($lines[1]);

    // 0:年月, 1:部屋, 2:氏名, 3:カナ, 4:家賃(70000), 5:管理費(30000)
    // 6:自費10%(10000), 7:自費8%(5000), 8:自費非課税(0), 9:10%計(40000), 10:8%計(5000), 11:非課税計(70000)
    // 12:税10%(4000), 13:税8%(400), 14:税計(4000+400=5400)
    expect($row[4])->toBe('70000')
        ->and($row[5])->toBe('30000')
        ->and($row[6])->toBe('10000')
        ->and($row[7])->toBe('5000')
        ->and($row[8])->toBe('0')
        ->and($row[9])->toBe('40000')
        ->and($row[10])->toBe('5000')
        ->and($row[11])->toBe('70000')
        ->and($row[12])->toBe('4000')
        ->and($row[13])->toBe('400');
});

test('tax_breakdownがnullの旧データでもフォールバックで正常出力されること', function () {
    MonthlyInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 5000,
        'taxable_amount' => 25000,
        'tax_amount' => 2500,
        'total_amount' => 77500,
        'status' => InvoiceStatus::Unbilled,
        'tax_breakdown' => null,
    ]);

    $csv = $this->service->exportMonthlyListCsv('2026-10');
    $lines = explode("\n", trim($csv));
    $row = str_getcsv($lines[1]);

    expect($row[4])->toBe('50000')
        ->and($row[5])->toBe('20000')
        ->and($row[6])->toBe('5000')
        ->and($row[7])->toBe('0')
        ->and($row[8])->toBe('0')
        ->and($row[9])->toBe('25000')
        ->and($row[10])->toBe('0')
        ->and($row[11])->toBe('50000')
        ->and($row[12])->toBe('2500')
        ->and($row[13])->toBe('0');
});
