<?php

use App\Enums\InvoiceStatus;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCsvExportService;

beforeEach(function () {
    $this->service = new InvoiceCsvExportService;

    $this->resident = Resident::create([
        'room_number' => '101',
        'name' => 'CSVテスト入居者',
        'move_in_date' => '2026-01-01',
    ]);

    MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 5000,
        'total_amount' => 95000,
        'status' => InvoiceStatus::Billed, // 会計CSVはBilled/Paidのみ対象のため
    ]);
});

test('請求一覧台帳CSVがUTF-8 BOM付きで正しく出力されること', function () {
    $csv = $this->service->exportMonthlyListCsv('2026-10');

    // UTF-8 BOM の存在チェック
    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();

    // カラムヘッダーとデータの存在チェック（税情報を含む新形式）
    expect($csv)->toContain('請求年月,部屋番号,入居者氏名')
        ->and($csv)->toContain('2026-10,101,CSVテスト入居者')
        ->and($csv)->toContain('60000,30000,5000,35000,10%,3500,95000');
});

test('会計仕訳連携CSVが弥生・freee対応の借方貸方形式で出力されること', function () {
    $csv = $this->service->exportAccountingJournalCsv('2026-10');

    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();

    // 借方科目: 売掛金、貸方科目: 賃貸料収入、施設管理費収入、自費・立替金収入
    expect($csv)->toContain('取引日,借方科目,借方金額,貸方科目,貸方金額,税区分,摘要')
        ->and($csv)->toContain('売掛金,60000,賃貸料収入,60000,対象外(非課税)')
        ->and($csv)->toContain('売掛金,30000,施設管理費収入,30000,課税売上10%')
        ->and($csv)->toContain('売掛金,5000,自費・立替金収入,5000,課税売上10%');
});
