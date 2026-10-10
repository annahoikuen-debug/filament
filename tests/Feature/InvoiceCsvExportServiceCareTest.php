<?php

use App\Enums\InvoiceStatus;
use App\Enums\ServiceInvoiceStatus;
use App\Models\AccountingExportProfile;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\ServiceInvoice;
use App\Services\InvoiceCsvExportService;

beforeEach(function () {
    $this->service = new InvoiceCsvExportService;

    $this->facility = Facility::create([
        'name' => '介護CSVテスト施設',
        'operator' => 'テスト運営',
        'postal_code' => '100-0001',
        'address' => '東京都千代田区',
        'phone' => '03-1234-5678',
        'invoice_registration_number' => 'T1234567890123',
        'is_active' => true,
    ]);

    $this->resident = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '201',
        'name' => '介護CSV入居者',
        'move_in_date' => '2026-01-01',
    ]);

    MonthlyInvoice::create([
        'billing_year_month' => '2026-04',
        'resident_id' => $this->resident->id,
        'facility_id' => $this->facility->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => InvoiceStatus::Billed,
    ]);
});

function careCsvServiceInvoice(array $overrides = []): ServiceInvoice
{
    return ServiceInvoice::create(array_merge([
        'facility_id' => 1,
        'resident_id' => 1,
        'billing_year_month' => '2026-04',
        'service_type' => 'visiting_care',
        'service_type_label' => '訪問介護',
        'amount' => 30000,
        'tax_amount' => 3000,
        'tax_rate' => 10,
        'status' => ServiceInvoiceStatus::Confirmed,
    ], $overrides), );
}

test('会計仕訳CSVに介護サービス仕訳が含まれること', function () {
    careCsvServiceInvoice();

    $csv = $this->service->exportAccountingJournalCsv('2026-04', $this->facility->id);

    // 介護サービス: 借方=売掛金(1100)、貸方=介護サービス収入(4210)、税区分=1(課税10%)
    expect($csv)->toContain('4210,介護サービス収入')
        ->and($csv)->toContain('1100,売掛金,,,,,,4210,介護サービス収入,,,,,,30000,1,');
});

test('介護サービスの消費税は仮受消費税として別行で出力されること', function () {
    careCsvServiceInvoice(['tax_amount' => 3000]);

    $csv = $this->service->exportAccountingJournalCsv('2026-04', $this->facility->id);

    // 消費税行: 借方=売掛金(1100)、貸方=仮受消費税(2220)、税区分=対象外
    expect($csv)->toContain('2220,仮受消費税')
        ->and($csv)->toContain('1100,売掛金,,,,,,2220,仮受消費税,,,,,,3000,0,');
});

test('消費税0円の介護サービスは税別行が出力されないこと', function () {
    careCsvServiceInvoice(['tax_amount' => 0]);

    $csv = $this->service->exportAccountingJournalCsv('2026-04', $this->facility->id);

    expect($csv)->toContain('4210,介護サービス収入')
        ->and($csv)->not->toContain('2220,仮受消費税');
});

test('金額0円以下の介護サービスはスキップされること', function () {
    careCsvServiceInvoice(['amount' => 0]);

    $csv = $this->service->exportAccountingJournalCsv('2026-04', $this->facility->id);

    // 住居費のみ
    expect($csv)->toContain('4110,賃貸料収入')
        ->and($csv)->not->toContain('4210,介護サービス収入');
});

test('draft状態の介護サービスは仕訳に含まれないこと', function () {
    careCsvServiceInvoice(['status' => ServiceInvoiceStatus::Draft]);

    $csv = $this->service->exportAccountingJournalCsv('2026-04', $this->facility->id);

    expect($csv)->not->toContain('4210,介護サービス収入');
});

test('sent状態の介護サービスも仕訳に含まれること', function () {
    careCsvServiceInvoice(['status' => ServiceInvoiceStatus::Sent]);

    $csv = $this->service->exportAccountingJournalCsv('2026-04', $this->facility->id);

    expect($csv)->toContain('4210,介護サービス収入');
});

test('includeCareServices=falseの場合は介護サービスを除外すること', function () {
    careCsvServiceInvoice();

    $csv = $this->service->exportAccountingJournalCsv('2026-04', $this->facility->id, null, null, false);

    expect($csv)->toContain('4110,賃貸料収入')
        ->and($csv)->not->toContain('4210,介護サービス収入');
});

test('プレビューにも介護サービス仕訳が反映されること', function () {
    careCsvServiceInvoice(['amount' => 30000, 'tax_amount' => 3000]);

    $preview = $this->service->previewAccountingJournal('2026-04', $this->facility->id);

    // 住居費2行(家賃+管理費) + 介護サービス1行 + 消費税1行 = 4行
    expect($preview['entries'])->toHaveCount(4)
        ->and($preview['totals']['debit'])->toBe(103000)
        ->and($preview['totals']['count'])->toBe(4);

    $careEntry = collect($preview['entries'])->first(fn ($e) => $e['credit_account_code'] === '4210');
    expect($careEntry)->not->toBeNull()
        ->and($careEntry['amount'])->toBe(30000);
});

test('データがない月のプレビューは空エントリを返すこと', function () {
    $preview = $this->service->previewAccountingJournal('2099-01', $this->facility->id);

    expect($preview)->toHaveKeys(['entries', 'headers', 'totals', 'profile'])
        ->and($preview['entries'])->toHaveCount(0)
        ->and($preview['totals'])->toBe(['debit' => 0, 'credit' => 0, 'count' => 0])
        ->and($preview['profile'])->toBeArray();
});

test('利用可能なプロファイル一覧を取得できること', function () {
    AccountingExportProfile::createDefaultsForFacility($this->facility->id);

    $profiles = $this->service->getAvailableProfiles($this->facility->id);

    expect($profiles)->toBeArray()
        ->and(count($profiles))->toBeGreaterThan(0)
        ->and($profiles[0])->toHaveKey('software_type');
});
