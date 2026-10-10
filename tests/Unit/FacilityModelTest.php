<?php

use App\Models\Facility;

test('fillableフィールドのみが一括代入で設定されること', function () {
    $facility = Facility::create([
        'name' => 'テスト施設',
        'operator' => 'テスト運営',
        'postal_code' => '123-4567',
        'address' => '東京都テスト区1-2-3',
        'phone' => '03-1234-5678',
        'fax' => '03-1234-5679',
        'email' => 'test@example.com',
        'invoice_registration_number' => 'T1234567890123',
        'bank' => [
            'name' => 'テスト銀行',
            'branch_name' => 'テスト支店',
            'account_type' => '普通',
            'account_number' => '1234567',
            'account_holder' => 'テスト施設',
        ],
        'billing' => [
            'direct_debit_day' => 27,
            'bank_transfer_due_days' => 30,
        ],
        'is_active' => true,
        'notes' => 'テスト用メモ',
        'seal_path' => '/seals/test.png',
    ]);

    expect($facility->exists)->toBeTrue()
        ->and($facility->name)->toBe('テスト施設')
        ->and($facility->postal_code)->toBe('123-4567')
        ->and($facility->invoice_registration_number)->toBe('T1234567890123');
});

test('fillable外のフィールド（id）が一括代入で無視されること', function () {
    $facility = Facility::create([
        'id' => 99999,
        'name' => 'ID無視テスト',
        'operator' => 'テスト運営',
        'postal_code' => '123-4567',
        'address' => 'テスト住所',
        'invoice_registration_number' => 'T1234567890123',
    ]);

    expect($facility->id)->not->toBe(99999);
});

test('不正なフィールド名での一括代入は無視されること', function () {
    $facility = Facility::create([
        'name' => '不正フィールドテスト',
        'operator' => 'テスト運営',
        'postal_code' => '123-4567',
        'address' => 'テスト住所',
        'invoice_registration_number' => 'T1234567890123',
        'unknown_field' => 'テスト',
    ]);

    expect($facility->exists)->toBeTrue()
        ->and($facility->getAttribute('unknown_field'))->toBeNull();
});

test('キャストが正しく動作すること', function () {
    $facility = Facility::create([
        'name' => 'キャストテスト',
        'operator' => 'テスト運営',
        'postal_code' => '123-4567',
        'address' => 'テスト住所',
        'invoice_registration_number' => 'T1234567890123',
        'bank' => [
            [
                'name' => 'テスト銀行',
                'branch_name' => 'テスト支店',
                'account_type' => '普通',
                'account_number' => '1234567',
                'account_holder' => 'テスト施設',
            ],
        ],
        'billing' => [
            [
                'direct_debit_day' => 27,
                'bank_transfer_due_days' => 30,
            ],
        ],
        'is_active' => true,
    ]);

    expect($facility->bank)->toBeArray()
        ->and($facility->billing)->toBeArray()
        ->and($facility->is_active)->toBeTrue();
});

test('residentsリレーションが正しく動作すること', function () {
    $facility = Facility::factory()->create([
        'operator' => 'テスト運営',
    ]);
    $facilityWithResidents = Facility::factory()->hasResidents(1)->create([
        'operator' => 'テスト運営',
    ]);

    // 通常の施設には居住者がいないこと
    expect($facility->residents->isEmpty())->toBeTrue();
    // hasResidents(1)を使用した施設には1人の居住者がいること
    expect($facilityWithResidents->residents)->toHaveCount(1);
    expect($facilityWithResidents->residents->first()->id)->toBeGreaterThan(0);
});

test('monthlyInvoicesリレーションが正しく動作すること', function () {
    $facility = Facility::factory()->create([
        'operator' => 'テスト運営',
    ]);
    $facilityWithInvoices = Facility::factory()->hasMonthlyInvoices(1)->create([
        'operator' => 'テスト運営',
    ]);

    // 通常の施設には月次請求がないこと
    expect($facility->monthlyInvoices->isEmpty())->toBeTrue();
    // hasMonthlyInvoices(1)を使用した施設には1つの月次請求があること
    expect($facilityWithInvoices->monthlyInvoices)->toHaveCount(1);
    expect($facilityWithInvoices->monthlyInvoices->first()->id)->toBeGreaterThan(0);
});

test('dailyChargesリレーションが正しく動作すること', function () {
    $facility = Facility::factory()->create([
        'operator' => 'テスト運営',
    ]);

    // 日付を固定して在籍期間との不整合を防ぐ
    $facilityWithCharges = Facility::factory()->create([
        'operator' => 'テスト運営',
    ]);
    $resident = \App\Models\Resident::factory()->create([
        'facility_id' => $facilityWithCharges->id,
        'move_in_date' => '2020-01-01',
    ]);
    \App\Models\DailyCharge::factory()->create([
        'facility_id' => $facilityWithCharges->id,
        'resident_id' => $resident->id,
        'date' => '2026-03-10',
    ]);

    // 通常の施設には日々の自費利用明細がないこと
    expect($facility->dailyCharges->isEmpty())->toBeTrue();
    // 明細を作成した施設には1つの日々の自費利用明細があること
    expect($facilityWithCharges->dailyCharges)->toHaveCount(1);
    expect($facilityWithCharges->dailyCharges->first()->id)->toBeGreaterThan(0);
});

test('currentメソッドが施設ID指定時にその施設を返すこと', function () {
    $facility1 = Facility::create([
        'name' => '施設1',
        'operator' => 'テスト運営1',
        'postal_code' => '123-4567',
        'address' => '東京都テスト区1-2-3',
        'invoice_registration_number' => 'T1234567890123',
        'is_active' => true,
    ]);
    $facility2 = Facility::create([
        'name' => '施設2',
        'operator' => 'テスト運営2',
        'postal_code' => '234-5678',
        'address' => '大阪府テスト市4-5-6',
        'invoice_registration_number' => 'T0987654321098',
        'is_active' => true,
    ]);

    $result = Facility::current($facility2->id);
    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($facility2->id);
});

test('currentメソッドが施設ID指定時に非アクティブな施設はnullを返すこと', function () {
    $facility1 = Facility::create([
        'name' => '施設1',
        'operator' => 'テスト運営1',
        'postal_code' => '123-4567',
        'address' => '東京都テスト区1-2-3',
        'invoice_registration_number' => 'T1234567890123',
        'is_active' => true,
    ]);
    $facility2 = Facility::create([
        'name' => '施設2',
        'operator' => 'テスト運営2',
        'postal_code' => '234-5678',
        'address' => '大阪府テスト市4-5-6',
        'invoice_registration_number' => 'T0987654321098',
        'is_active' => false,
    ]);

    $result = Facility::current($facility2->id);
    expect($result)->toBeNull();
});

test('currentメソッドが施設ID未指定時は最初の有効施設を返すこと', function () {
    $facility1 = Facility::create([
        'name' => '施設1',
        'operator' => 'テスト運営1',
        'postal_code' => '123-4567',
        'address' => '東京都テスト区1-2-3',
        'invoice_registration_number' => 'T1234567890123',
        'is_active' => false,
    ]);
    $facility2 = Facility::create([
        'name' => '施設2',
        'operator' => 'テスト運営2',
        'postal_code' => '234-5678',
        'address' => '大阪府テスト市4-5-6',
        'invoice_registration_number' => 'T0987654321098',
        'is_active' => true,
    ]);
    $facility3 = Facility::create([
        'name' => '施設3',
        'operator' => 'テスト運営3',
        'postal_code' => '345-6789',
        'address' => '名古屋市テスト区7-8-9',
        'invoice_registration_number' => 'T1111111111111',
        'is_active' => true,
    ]);

    $result = Facility::current();
    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($facility2->id); // 最初の有効施設
});

test('currentメソッドが有効な施設がない場合はnullを返すこと', function () {
    Facility::create([
        'name' => '施設1',
        'operator' => 'テスト運営1',
        'postal_code' => '123-4567',
        'address' => '東京都テスト区1-2-3',
        'invoice_registration_number' => 'T1234567890123',
        'is_active' => false,
    ]);
    Facility::create([
        'name' => '施設2',
        'operator' => 'テスト運営2',
        'postal_code' => '234-5678',
        'address' => '大阪府テスト市4-5-6',
        'invoice_registration_number' => 'T0987654321098',
        'is_active' => false,
    ]);

    $result = Facility::current();
    expect($result)->toBeNull();
});

test('toConfigArrayメソッドが正しい配列を返すこと', function () {
    $facility = Facility::create([
        'name' => 'テスト施設',
        'operator' => 'テスト運営',
        'postal_code' => '123-4567',
        'address' => '東京都テスト区1-2-3',
        'phone' => '03-1234-5678',
        'fax' => '03-1234-5679',
        'email' => 'test@example.com',
        'invoice_registration_number' => 'T1234567890123',
        'bank' => [
            'name' => 'テスト銀行',
            'branch_name' => 'テスト支店',
            'account_type' => '普通',
            'account_number' => '1234567',
            'account_holder' => 'テスト施設',
        ],
        'billing' => [
            'direct_debit_day' => 27,
            'bank_transfer_due_days' => 30,
        ],
        'is_active' => true,
        'notes' => 'テスト用メモ',
        'seal_path' => '/seals/test.png',
    ]);

    $config = $facility->toConfigArray();
    expect($config)->toBeArray()
        ->and($config['name'])->toBe('テスト施設')
        ->and($config['postal_code'])->toBe('123-4567')
        ->and($config['invoice_registration_number'])->toBe('T1234567890123')
        ->and($config['bank'])->toBeArray()
        ->and($config['billing'])->toBeArray()
        ->and($config['seal_path'])->toBe('/seals/test.png');
});

test('validateInvoiceNumberメソッドが適格請求書登録番号をバリデーションすること', function () {
    expect(Facility::validateInvoiceNumber('T1234567890123'))->toBeTrue()
        ->and(Facility::validateInvoiceNumber('T0000000000000'))->toBeTrue()
        ->and(Facility::validateInvoiceNumber('T9999999999999'))->toBeTrue();

    // 不正な形式
    expect(Facility::validateInvoiceNumber('1234567890123'))->toBeFalse(); // Tで始まらない
    expect(Facility::validateInvoiceNumber('TX1234567890123'))->toBeFalse(); // 14桁
    expect(Facility::validateInvoiceNumber('T123456789012'))->toBeFalse(); // 12桁
    expect(Facility::validateInvoiceNumber('TL234567890123'))->toBeFalse(); // 英字混在
    expect(Facility::validateInvoiceNumber('T12345678901234'))->toBeFalse(); // 14桁
    expect(Facility::validateInvoiceNumber(''))->toBeFalse(); // 空文字
});
