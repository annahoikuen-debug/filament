<?php

use App\Enums\ServiceInvoiceStatus;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\ServiceInvoice;

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

    // ダミーPDF
    $this->dummyPdfBase64 = base64_encode('%PDF-1.4\n%EOF\n');
});

test('一括取り込みAPIで複数請求書を作成できること', function () {
    $response = $this->postJson('/api/external-invoices', [
        'facility_id' => $this->facility->id,
        'billing_year_month' => '2026-10',
        'external_system_name' => 'ケアプランシステム',
        'invoices' => [
            [
                'resident_id' => $this->resident->id,
                'service_type' => 'visiting_care',
                'external_invoice_number' => 'VC-202610-001',
                'amount' => 45000,
                'tax_amount' => 4500,
                'tax_rate' => 10.00,
                'pdf_base64' => $this->dummyPdfBase64,
                'pdf_filename' => '訪問介護請求書.pdf',
                'status' => 'confirmed',
            ],
            [
                'resident_id' => $this->resident->id,
                'service_type' => 'day_care',
                'external_invoice_number' => 'DC-202610-001',
                'amount' => 30000,
                'tax_amount' => 3000,
                'tax_rate' => 10.00,
                'status' => 'confirmed',
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'created' => 2,
                'updated' => 0,
            ],
        ]);

    // DB確認
    expect(ServiceInvoice::count())->toBe(2);
    $vc = ServiceInvoice::where('service_type', 'visiting_care')->first();
    expect($vc->external_system_name)->toBe('ケアプランシステム')
        ->and($vc->external_invoice_number)->toBe('VC-202610-001')
        ->and($vc->status)->toBe(ServiceInvoiceStatus::Confirmed)
        ->and($vc->hasPdf())->toBeTrue();

    $dc = ServiceInvoice::where('service_type', 'day_care')->first();
    expect($dc->status)->toBe(ServiceInvoiceStatus::Confirmed)
        ->and($dc->pdf_path)->toBeNull(); // PDFなし
});

test('既存レコードは更新されること', function () {
    // 事前作成
    ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => 'visiting_care',
        'service_type_label' => '訪問介護',
        'external_invoice_number' => 'VC-202610-001',
        'amount' => 40000,
        'tax_amount' => 4000,
        'tax_rate' => 10.00,
        'status' => ServiceInvoiceStatus::Draft,
    ]);

    $response = $this->postJson('/api/external-invoices', [
        'facility_id' => $this->facility->id,
        'billing_year_month' => '2026-10',
        'invoices' => [
            [
                'resident_id' => $this->resident->id,
                'service_type' => 'visiting_care',
                'external_invoice_number' => 'VC-202610-001',
                'amount' => 50000, // 変更
                'tax_amount' => 5000,
                'tax_rate' => 10.00,
                'status' => 'confirmed', // ステータス変更
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson(['data' => ['created' => 0, 'updated' => 1]]);

    $vc = ServiceInvoice::where('service_type', 'visiting_care')->first();
    expect($vc->amount)->toBe(50000)
        ->and($vc->status)->toBe(ServiceInvoiceStatus::Confirmed);
});

test('異なる施設の入居者はエラーになること', function () {
    $otherFacility = Facility::create([
        'name' => '他施設',
        'operator' => '他運営',
        'postal_code' => '200-0001',
        'address' => '大阪府大阪市',
        'invoice_registration_number' => 'T9876543210987',
    ]);
    $otherResident = Resident::create([
        'facility_id' => $otherFacility->id,
        'room_number' => '201',
        'name' => '他入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    $response = $this->postJson('/api/external-invoices', [
        'facility_id' => $this->facility->id, // 施設A
        'billing_year_month' => '2026-10',
        'invoices' => [
            [
                'resident_id' => $otherResident->id, // 施設Bの入居者
                'service_type' => 'visiting_care',
                'amount' => 45000,
                'tax_amount' => 4500,
                'tax_rate' => 10.00,
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'created' => 0,
                'errors' => [
                    [
                        'resident_id' => $otherResident->id,
                        'error' => '入居者が見つからないか、施設が一致しません',
                    ],
                ],
            ],
        ]);
});

test('無効なサービス種別はバリデーションエラーになること', function () {
    $response = $this->postJson('/api/external-invoices', [
        'facility_id' => $this->facility->id,
        'billing_year_month' => '2026-10',
        'invoices' => [
            [
                'resident_id' => $this->resident->id,
                'service_type' => 'invalid_type',
                'amount' => 45000,
                'tax_amount' => 4500,
                'tax_rate' => 10.00,
            ],
        ],
    ]);

    $response->assertStatus(422);
});

test('無効な請求年月フォーマットはバリデーションエラーになること', function () {
    $response = $this->postJson('/api/external-invoices', [
        'facility_id' => $this->facility->id,
        'billing_year_month' => '2026/10', // ハイフンでない
        'invoices' => [
            [
                'resident_id' => $this->resident->id,
                'service_type' => 'visiting_care',
                'amount' => 45000,
                'tax_amount' => 4500,
                'tax_rate' => 10.00,
            ],
        ],
    ]);

    $response->assertStatus(422);
});

test('単一取り込みAPIが動作すること', function () {
    $response = $this->postJson('/api/external-invoices/single', [
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => 'visiting_care',
        'external_invoice_number' => 'VC-202610-001',
        'amount' => 45000,
        'tax_amount' => 4500,
        'tax_rate' => 10.00,
        'pdf_base64' => $this->dummyPdfBase64,
        'status' => 'confirmed',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'status' => 'confirmed',
            ],
        ]);

    expect(ServiceInvoice::count())->toBe(1);
    $vc = ServiceInvoice::first();
    expect($vc->amount)->toBe(45000)
        ->and($vc->hasPdf())->toBeTrue();
});

test('単一APIでupdateOrCreateが動作すること', function () {
    // 既存作成
    ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => 'visiting_care',
        'service_type_label' => '訪問介護',
        'external_invoice_number' => 'VC-202610-001',
        'amount' => 40000,
        'tax_amount' => 4000,
        'tax_rate' => 10.00,
        'status' => ServiceInvoiceStatus::Draft,
    ]);

    // 同じキーで再送信
    $response = $this->postJson('/api/external-invoices/single', [
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => 'visiting_care',
        'external_invoice_number' => 'VC-202610-001',
        'amount' => 50000,
        'tax_amount' => 5000,
        'tax_rate' => 10.00,
        'status' => 'confirmed',
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => '更新しました']);

    expect(ServiceInvoice::count())->toBe(1);
    $vc = ServiceInvoice::first();
    expect($vc->amount)->toBe(50000)
        ->and($vc->status)->toBe(ServiceInvoiceStatus::Confirmed);
});

test('一覧取得APIが動作すること', function () {
    ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'service_type' => 'visiting_care',
        'service_type_label' => '訪問介護',
        'amount' => 45000,
        'tax_amount' => 4500,
        'tax_rate' => 10.00,
        'status' => ServiceInvoiceStatus::Confirmed,
    ]);

    ServiceInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-11',
        'service_type' => 'day_care',
        'service_type_label' => '通所介護',
        'amount' => 30000,
        'tax_amount' => 3000,
        'tax_rate' => 10.00,
        'status' => ServiceInvoiceStatus::Draft,
    ]);

    // 全件取得
    $response = $this->getJson('/api/external-invoices');
    $response->assertStatus(200)
        ->assertJson(['success' => true, 'meta' => ['total' => 2]]);

    // フィルタ: 請求年月
    $response = $this->getJson('/api/external-invoices?billing_year_month=2026-10');
    $response->assertStatus(200)
        ->assertJson(['meta' => ['total' => 1]]);

    // フィルタ: ステータス
    $response = $this->getJson('/api/external-invoices?status=confirmed');
    $response->assertStatus(200)
        ->assertJson(['meta' => ['total' => 1]]);

    // フィルタ: サービス種別
    $response = $this->getJson('/api/external-invoices?service_type=visiting_care');
    $response->assertStatus(200)
        ->assertJson(['meta' => ['total' => 1]]);
});

test('不正なBase64 PDFはエラーになること', function () {
    $response = $this->postJson('/api/external-invoices', [
        'facility_id' => $this->facility->id,
        'billing_year_month' => '2026-10',
        'invoices' => [
            [
                'resident_id' => $this->resident->id,
                'service_type' => 'visiting_care',
                'amount' => 45000,
                'tax_amount' => 4500,
                'tax_rate' => 10.00,
                'pdf_base64' => 'invalid_base64!!!',
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'created' => 0,
                'errors' => [
                    [
                        'error' => 'Invalid base64 PDF data',
                    ],
                ],
            ],
        ]);
});
