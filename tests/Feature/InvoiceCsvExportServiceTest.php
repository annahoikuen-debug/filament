<?php

use App\Enums\InvoiceStatus;
use App\Models\AccountingExportProfile;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCsvExportService;

beforeEach(function () {
    $this->service = new InvoiceCsvExportService;

    $this->facility = Facility::create([
        'name' => 'テスト施設',
        'operator' => 'テスト運営',
        'postal_code' => '100-0001',
        'address' => '東京都千代田区',
        'phone' => '03-1234-5678',
        'invoice_registration_number' => 'T1234567890123',
        'is_active' => true,
    ]);

    $this->resident = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '101',
        'name' => 'CSVテスト入居者',
        'move_in_date' => '2026-01-01',
    ]);

    MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'facility_id' => $this->facility->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 5000,
        'total_amount' => 95000,
        'status' => InvoiceStatus::Billed,
    ]);
});

test('請求一覧台帳CSVがUTF-8 BOM付きで正しく出力されること', function () {
    $csv = $this->service->exportMonthlyListCsv('2026-10');

    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();

    expect($csv)->toContain('請求年月,部屋番号,入居者氏名')
        ->and($csv)->toContain('2026-10,101,CSVテスト入居者')
        ->and($csv)->toContain('60000,30000,5000,35000,10%,3500,95000');
});

test('会計仕訳連携CSVがfreee標準プロファイル形式で出力されること', function () {
    $csv = $this->service->exportAccountingJournalCsv('2026-10', $this->facility->id);

    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();

    // freee標準プロファイルのヘッダー（tag_codes含む18フィールド）
    expect($csv)->toContain('取引日,借方科目コード,借方科目名,借方補助科目コード,借方補助科目名,借方部門コード,借方部門名,借方タグコード,貸方科目コード,貸方科目名,貸方補助科目コード,貸方補助科目名,貸方部門コード,貸方部門名,貸方タグコード,金額,税区分コード,摘要')
        // 家賃: 借方=売掛金(1100)、貸方=賃貸料収入(4110)、税区分=0(対象外)
        ->and($csv)->toContain('1100,売掛金,,,,,,4110,賃貸料収入,,,,,,60000,0,')
        // 管理費: 借方=売掛金(1100)、貸方=施設管理費収入(4120)、税区分=1(課税10%)
        ->and($csv)->toContain('1100,売掛金,,,,,,4120,施設管理費収入,,,,,,30000,1,')
        // 自費: 借方=売掛金(1100)、貸方=自費・立替金収入(4130)、税区分=1(課税10%)
        ->and($csv)->toContain('1100,売掛金,,,,,,4130,自費・立替金収入,,,,,,5000,1,');
});

test('会計仕訳連携CSVがプレビュー機能で正しくデータを返すこと', function () {
    $preview = $this->service->previewAccountingJournal('2026-10', $this->facility->id);

    expect($preview)->toHaveKeys(['entries', 'headers', 'totals', 'profile'])
        ->and($preview['entries'])->toHaveCount(3)
        ->and($preview['totals']['debit'])->toBe(95000)
        ->and($preview['totals']['credit'])->toBe(95000)
        ->and($preview['totals']['count'])->toBe(3)
        ->and($preview['profile']['software_type'])->toBe('freee');

    $entries = $preview['entries'];
    expect($entries[0]['debit_account_code'])->toBe('1100')
        ->and($entries[0]['credit_account_code'])->toBe('4110')
        ->and($entries[0]['amount'])->toBe(60000)
        ->and($entries[0]['tax_code'])->toBe('0');
});

test('会計仕訳連携CSVがMFクラウド会計プロファイルで出力されること', function () {
    $csv = $this->service->exportAccountingJournalCsv(
        '2026-10',
        $this->facility->id,
        AccountingExportProfile::SOFTWARE_MF
    );

    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();

    // MFクラウド会計のヘッダー（tag_codesなし、16フィールド）
    expect($csv)->toContain('日付,借方勘定科目コード,借方勘定科目名,借方補助科目コード,借方補助科目名,借方部門コード,借方部門名,貸方勘定科目コード,貸方勘定科目名,貸方補助科目コード,貸方補助科目名,貸方部門コード,貸方部門名,金額,税区分,摘要')
        // 家賃: 税区分=対象外
        ->and($csv)->toContain('1100,売掛金,,,,,4110,賃貸料収入,,,,,60000,対象外,')
        // 管理費: 税区分=課税10%
        ->and($csv)->toContain('1100,売掛金,,,,,4120,施設管理費収入,,,,,30000,課税10%,')
        // 自費: 税区分=課税10%
        ->and($csv)->toContain('1100,売掛金,,,,,4130,自費・立替金収入,,,,,5000,課税10%,');
});

test('会計仕訳連携CSVが弥生会計プロファイルで出力されること', function () {
    $csv = $this->service->exportAccountingJournalCsv(
        '2026-10',
        $this->facility->id,
        AccountingExportProfile::SOFTWARE_YAYOI
    );

    // 弥生会計はSJISエンコーディングだがテストではUTF-8で比較（変換後の文字列）
    // ヘッダー確認
    expect($csv)->toContain('取引日,借方科目コード,借方科目名,借方補助科目コード,借方補助科目名,借方部門コード,借方部門名,貸方科目コード,貸方科目名,貸方補助科目コード,貸方補助科目名,貸方部門コード,貸方部門名,金額,税区分,摘要')
        ->and($csv)->toContain('1100,売掛金,,,,,4110,賃貸料収入,,,,,60000,対象外,')
        ->and($csv)->toContain('1100,売掛金,,,,,4120,施設管理費収入,,,,,30000,課税仕入10%,')
        ->and($csv)->toContain('1100,売掛金,,,,,4130,自費・立替金収入,,,,,5000,課税仕入10%,');
});

test('会計仕訳連携CSVが勘定奉行プロファイルで出力されること', function () {
    $csv = $this->service->exportAccountingJournalCsv(
        '2026-10',
        $this->facility->id,
        AccountingExportProfile::SOFTWARE_KANJOBUGYO
    );

    // 勘定奉行はシンプルな形式（補助科目・部門のみ、科目名は出さない）
    expect($csv)->toContain('伝票日付,借方科目コード,借方補助科目コード,借方部門コード,貸方科目コード,貸方補助科目コード,貸方部門コード,金額,税区分,摘要')
        ->and($csv)->toContain('1100,,,4110,,,60000,0,')
        ->and($csv)->toContain('1100,,,4120,,,30000,1,')
        ->and($csv)->toContain('1100,,,4130,,,5000,1,');
});
