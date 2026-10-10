<?php

use App\Enums\ServiceInvoiceStatus;
use App\Enums\ServiceType;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\ServiceInvoice;
use Illuminate\Database\QueryException;

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
});

test('ServiceInvoiceモデルが作成できること', function () {
    $invoice = ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => ServiceType::VisitingCare,
        'service_type_label' => '訪問介護',
        'amount' => 50000,
        'tax_amount' => 5000,
        'tax_rate' => 10.00,
    ]);

    expect($invoice->id)->not->toBeNull()
        ->and($invoice->service_type)->toBe(ServiceType::VisitingCare)
        ->and($invoice->service_type->getLabel())->toBe('訪問介護')
        ->and($invoice->total_with_tax)->toBe(55000)
        ->and($invoice->status)->toBe(ServiceInvoiceStatus::Draft);
});

test('サービス種別enumが正しく動作すること', function () {
    expect(ServiceType::VisitingCare->getLabel())->toBe('訪問介護')
        ->and(ServiceType::DayCare->getLabel())->toBe('通所介護')
        ->and(ServiceType::CarePlanning->getLabel())->toBe('居宅介護支援')
        ->and(ServiceType::HomeNursing->getLabel())->toBe('訪問看護')
        ->and(ServiceType::ShortStay->getLabel())->toBe('短期入所生活介護')
        ->and(ServiceType::WelfareEquipment->getLabel())->toBe('福祉用具貸与')
        ->and(ServiceType::HomeModification->getLabel())->toBe('居宅介護住宅改修')
        ->and(ServiceType::Other->getLabel())->toBe('その他');

    // 介護保険サービス判定
    expect(ServiceType::VisitingCare->isInsuranceService())->toBeTrue()
        ->and(ServiceType::Other->isInsuranceService())->toBeFalse();

    // 表示順序
    $ordered = ServiceType::getOrderedCases();
    expect($ordered[0])->toBe(ServiceType::VisitingCare)
        ->and($ordered[1])->toBe(ServiceType::DayCare)
        ->and($ordered[2])->toBe(ServiceType::CarePlanning);
});

test('ステータスenumが正しく動作すること', function () {
    expect(ServiceInvoiceStatus::Draft->getLabel())->toBe('下書き')
        ->and(ServiceInvoiceStatus::Confirmed->getLabel())->toBe('確定済み')
        ->and(ServiceInvoiceStatus::Sent->getLabel())->toBe('送信済み');

    expect(ServiceInvoiceStatus::Draft->getColor())->toBe('gray')
        ->and(ServiceInvoiceStatus::Confirmed->getColor())->toBe('info')
        ->and(ServiceInvoiceStatus::Sent->getColor())->toBe('success');
});

test('ステータス遷移メソッドが動作すること', function () {
    $invoice = ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => ServiceType::VisitingCare,
        'service_type_label' => '訪問介護',
        'amount' => 50000,
        'tax_amount' => 5000,
        'tax_rate' => 10.00,
    ]);

    // 確定
    $invoice->markAsConfirmed();
    $invoice->refresh();
    expect($invoice->status)->toBe(ServiceInvoiceStatus::Confirmed);

    // 送信済み
    $invoice->markAsSent('email');
    $invoice->refresh();
    expect($invoice->status)->toBe(ServiceInvoiceStatus::Sent)
        ->and($invoice->sent_via)->toBe('email')
        ->and($invoice->sent_at)->not->toBeNull();

    // 下書きに戻す
    $invoice->markAsDraft();
    $invoice->refresh();
    expect($invoice->status)->toBe(ServiceInvoiceStatus::Draft)
        ->and($invoice->sent_at)->toBeNull()
        ->and($invoice->sent_via)->toBeNull();
});

test('スコープが正しく動作すること', function () {
    ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => ServiceType::VisitingCare,
        'service_type_label' => '訪問介護',
        'amount' => 50000,
        'tax_amount' => 5000,
        'tax_rate' => 10.00,
        'status' => ServiceInvoiceStatus::Draft,
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
        'status' => ServiceInvoiceStatus::Confirmed,
    ]);

    ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-11',
        'service_type' => ServiceType::VisitingCare,
        'service_type_label' => '訪問介護',
        'amount' => 50000,
        'tax_amount' => 5000,
        'tax_rate' => 10.00,
        'status' => ServiceInvoiceStatus::Sent,
    ]);

    // 請求年月スコープ
    expect(ServiceInvoice::forYearMonth('2026-10')->count())->toBe(2)
        ->and(ServiceInvoice::forYearMonth('2026-11')->count())->toBe(1);

    // 施設スコープ
    expect(ServiceInvoice::forFacility($this->facility->id)->count())->toBe(3);

    // 入居者スコープ
    expect(ServiceInvoice::forResident($this->resident->id)->count())->toBe(3);

    // サービス種別スコープ
    expect(ServiceInvoice::forServiceType('visiting_care')->count())->toBe(2)
        ->and(ServiceInvoice::forServiceType('day_care')->count())->toBe(1);

    // ステータススコープ
    expect(ServiceInvoice::withStatus('draft')->count())->toBe(1)
        ->and(ServiceInvoice::withStatus('confirmed')->count())->toBe(1)
        ->and(ServiceInvoice::withStatus('sent')->count())->toBe(1);

    // 確定済みスコープ
    expect(ServiceInvoice::confirmed()->count())->toBe(1);

    // 送信済みスコープ
    expect(ServiceInvoice::sent()->count())->toBe(1);
});

test('リレーションが正しく動作すること', function () {
    $invoice = ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => ServiceType::VisitingCare,
        'service_type_label' => '訪問介護',
        'amount' => 50000,
        'tax_amount' => 5000,
        'tax_rate' => 10.00,
    ]);

    expect($invoice->facility->id)->toBe($this->facility->id)
        ->and($invoice->resident->id)->toBe($this->resident->id);
});

test('ユニーク制約が正しく動作すること', function () {
    ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => ServiceType::VisitingCare,
        'service_type_label' => '訪問介護',
        'amount' => 50000,
        'tax_amount' => 5000,
        'tax_rate' => 10.00,
        'external_invoice_number' => 'VC-202610-001',
    ]);

    // 同一入居者・同年月・同一サービス種別・同一外部請求番号は重複エラー
    expect(fn () => ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => ServiceType::VisitingCare,
        'service_type_label' => '訪問介護',
        'amount' => 30000,
        'tax_amount' => 3000,
        'tax_rate' => 10.00,
        'external_invoice_number' => 'VC-202610-001',
    ]))->toThrow(QueryException::class);
});
