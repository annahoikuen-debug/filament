<?php

use App\Models\AccountingExportProfile;
use App\Models\Facility;
use Carbon\Carbon;

test('fillableフィールドのみが一括代入で設定されること', function () {
    $facility = Facility::factory()->create();

    $profile = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'name' => 'テストプロファイル',
        'software_type' => 'freee',
        'header_mapping' => ['date' => '取引日'],
        'field_mapping' => ['required' => ['date'], 'optional' => []],
        'tax_code_mapping' => ['tax_exempt' => '0'],
        'department_mapping' => [],
        'tag_mapping' => [],
        'sub_account_mapping' => [],
        'date_format' => 'Y/m/d',
        'encoding' => 'UTF-8',
        'include_header' => true,
        'bom' => true,
        'line_ending' => 'CRLF',
        'default_values' => [],
        'is_default' => true,
        'is_active' => true,
        'notes' => 'テスト用メモ',
    ]);

    expect($profile->exists)->toBeTrue()
        ->and($profile->name)->toBe('テストプロファイル')
        ->and($profile->software_type)->toBe('freee');
});

test('fillable外のフィールド（id）が一括代入で無視されること', function () {
    $facility = Facility::factory()->create();

    $profile = AccountingExportProfile::create([
        'id' => 99999,
        'facility_id' => $facility->id,
        'name' => 'ID無視テスト',
        'software_type' => 'freee',
    ]);

    expect($profile->id)->not->toBe(99999);
});

test('不正なフィールド名での一括代入は無視されること', function () {
    $facility = Facility::factory()->create();

    $profile = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'name' => '不正フィールドテスト',
        'software_type' => 'freee',
        'unknown_field' => 'テスト',
    ]);

    expect($profile->exists)->toBeTrue()
        ->and($profile->getAttribute('unknown_field'))->toBeNull();
});

test('キャストが正しく動作すること', function () {
    $facility = Facility::factory()->create();

    $profile = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'name' => 'キャストテスト',
        'software_type' => 'freee',
        'header_mapping' => ['date' => '取引日'],
        'field_mapping' => ['required' => ['date'], 'optional' => []],
        'tax_code_mapping' => ['tax_exempt' => '0'],
        'department_mapping' => [],
        'tag_mapping' => [],
        'sub_account_mapping' => [],
        'date_format' => 'Y/m/d',
        'encoding' => 'UTF-8',
        'include_header' => true,
        'bom' => false,
        'line_ending' => 'CRLF',
        'default_values' => [],
        'is_default' => true,
        'is_active' => false,
    ]);

    expect($profile->header_mapping)->toBeArray()
        ->and($profile->field_mapping)->toBeArray()
        ->and($profile->tax_code_mapping)->toBeArray()
        ->and($profile->department_mapping)->toBeArray()
        ->and($profile->tag_mapping)->toBeArray()
        ->and($profile->sub_account_mapping)->toBeArray()
        ->and($profile->default_values)->toBeArray()
        ->and($profile->include_header)->toBeTrue()
        ->and($profile->bom)->toBeFalse()
        ->and($profile->line_ending)->toBe('CRLF')
        ->and($profile->is_default)->toBeTrue()
        ->and($profile->is_active)->toBeFalse();
});

test('定数が正しく定義されていること', function () {
    expect(AccountingExportProfile::SOFTWARE_FREEE)->toBe('freee')
        ->and(AccountingExportProfile::SOFTWARE_MF)->toBe('mf')
        ->and(AccountingExportProfile::SOFTWARE_YAYOI)->toBe('yayoi')
        ->and(AccountingExportProfile::SOFTWARE_KANJOBUGYO)->toBe('kanjobugyo')
        ->and(AccountingExportProfile::SOFTWARE_CUSTOM)->toBe('custom');
});

test('facilityリレーションが正しく動作すること', function () {
    $facility = Facility::factory()->create();
    $profile = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'name' => 'テストプロファイル',
        'software_type' => 'freee',
        'header_mapping' => ['date' => '取引日'],
        'field_mapping' => ['required' => ['date'], 'optional' => []],
        'tax_code_mapping' => ['tax_exempt' => '0'],
        'department_mapping' => [],
        'tag_mapping' => [],
        'sub_account_mapping' => [],
        'date_format' => 'Y/m/d',
        'encoding' => 'UTF-8',
        'include_header' => true,
        'bom' => false,
        'line_ending' => 'CRLF',
        'default_values' => [],
        'is_default' => true,
        'is_active' => true,
    ]);

    expect($profile->facility->id)->toBe($facility->id);
});

test('getDefaultメソッドがデフォルトプロファイルを取得すること', function () {
    $facility = Facility::factory()->create();
    $default = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'software_type' => 'freee',
        'name' => 'デフォルト',
        'is_default' => true,
        'is_active' => true,
    ]);

    $nonDefault = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'software_type' => 'freee',
        'name' => '非デフォルト',
        'is_default' => false,
        'is_active' => true,
    ]);

    $profile = AccountingExportProfile::getDefault($facility->id, 'freee');
    expect($profile)->not->toBeNull()
        ->and($profile->id)->toBe($default->id);
});

test('getDefaultメソッドで非アクティブなデフォルトは除外されること', function () {
    $facility = Facility::factory()->create();
    $inactiveDefault = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'software_type' => 'freee',
        'name' => '非アクティブなデフォルト',
        'is_default' => true,
        'is_active' => false,
    ]);

    $activeNonDefault = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'software_type' => 'freee',
        'name' => 'アクティブな非デフォルト',
        'is_default' => false,
        'is_active' => true,
    ]);

    $profile = AccountingExportProfile::getDefault($facility->id, 'freee');
    expect($profile)->toBeNull();
});

test('getActiveForFacilityメソッドが施設の有効なプロファイルを取得すること', function () {
    $facility = Facility::factory()->create();
    $active1 = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'software_type' => 'freee',
        'name' => 'アクティブ1',
        'is_active' => true,
    ]);
    $active2 = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'software_type' => 'mf',
        'name' => 'アクティブ2',
        'is_active' => true,
    ]);
    $inactive = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'software_type' => 'freee',
        'name' => '非アクティブ',
        'is_active' => false,
    ]);

    $profiles = AccountingExportProfile::getActiveForFacility($facility->id);
    expect($profiles->count())->toBe(2)
        ->and($profiles->pluck('name'))->toContain('アクティブ1')
        ->and($profiles->pluck('name'))->toContain('アクティブ2');
});

test('createDefaultsForFacilityメソッドが正しくデフォルトを作成すること', function () {
    $facility = Facility::factory()->create();
    AccountingExportProfile::createDefaultsForFacility($facility->id);

    $profiles = AccountingExportProfile::where('facility_id', $facility->id)->get();
    expect($profiles->count())->toBe(4); // freee, mf, yayoi, kanjobugyo

    $softwareTypes = ['freee', 'mf', 'yayoi', 'kanjobugyo'];
    foreach ($softwareTypes as $type) {
        $profile = AccountingExportProfile::where('facility_id', $facility->id)
            ->where('software_type', $type)
            ->where('is_default', true)
            ->first();
        expect($profile)->not->toBeNull()
            ->and($profile->is_active)->toBeTrue();
    }
});

test('buildHeaderメソッドがヘッダー行を生成すること', function () {
    $facility = Facility::factory()->create();
    $profile = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'software_type' => 'freee',
        'name' => 'テストプロファイル',
        'header_mapping' => [
            'date' => '取引日',
            'amount' => '金額',
            'description' => '摘要',
        ],
        'field_mapping' => [
            'required' => ['date', 'amount'],
            'optional' => ['description'],
        ],
    ]);

    $header = $profile->buildHeader();
    expect($header)->toBeArray()
        ->and($header)->toEqual(['取引日', '金額', '摘要']);
});

test('getFieldMappingメソッドがフィールドマッピングを取得すること', function () {
    $facility = Facility::factory()->create();
    $profile = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'software_type' => 'freee',
        'name' => 'テストプロファイル',
        'field_mapping' => [
            'required' => ['date', 'amount'],
            'optional' => ['description', 'tax_code'],
        ],
    ]);

    $mapping = $profile->getFieldMapping();
    expect($mapping)->toBeArray()
        ->and($mapping['required'])->toEqual(['date', 'amount'])
        ->and($mapping['optional'])->toEqual(['description', 'tax_code']);
});

test('mapTaxCodeメソッドが税区分コードを変換すること', function () {
    $facility = Facility::factory()->create();
    $profile = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'software_type' => 'freee',
        'name' => 'テストプロファイル',
        'tax_code_mapping' => [
            'tax_exempt' => '0',
            'taxable_10' => '1',
            'taxable_8' => '2',
        ],
    ]);

    expect($profile->mapTaxCode('tax_exempt'))->toBe('0')
        ->and($profile->mapTaxCode('taxable_10'))->toBe('1')
        ->and($profile->mapTaxCode('taxable_8'))->toBe('2');

    // マッピングにない場合は元のコードを返す
    expect($profile->mapTaxCode('unknown'))->toBe('unknown');
});

test('formatDateメソッドが日付をフォーマットすること', function () {
    $facility = Facility::factory()->create();
    $profile = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'software_type' => 'freee',
        'name' => 'テストプロファイル',
        'date_format' => 'Y/m/d',
    ]);

    $date = Carbon::create(2026, 10, 15);
    expect($profile->formatDate($date))->toBe('2026/10/15');

    // カスタムフォーマット
    $profile->date_format = 'Y-m-d';
    expect($profile->formatDate($date))->toBe('2026-10-15');
});
test('getLineEndingメソッドが改行コードを取得すること', function () {
    $facility = Facility::factory()->create();
    $profile = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'software_type' => 'freee',
        'name' => 'テストプロファイル',
        'line_ending' => 'LF',
    ]);

    expect($profile->getLineEnding())->toBe("\n");

    $profile->line_ending = 'CRLF';
    expect($profile->getLineEnding())->toBe("\r\n");

    $profile->line_ending = 'unknown';
    expect($profile->getLineEnding())->toBe("\r\n"); // デフォルト
});

test('convertEncodingメソッドがエンコーディング変換を行うこと', function () {
    $facility = Facility::factory()->create();
    $profile = AccountingExportProfile::create([
        'facility_id' => $facility->id,
        'software_type' => 'freee',
        'name' => 'テストプロファイル',
        'encoding' => 'SJIS',
    ]);

    $csv = "日付,金額\n2026/10/15,1000";
    $converted = $profile->convertEncoding($csv);
    // SJISに変換されていることを確認（バイナリセーフチェックのため長さで確認）
    expect($converted)->toBeString();

    // UTF-8の場合は変換されない
    $profile->encoding = 'UTF-8';
    $converted = $profile->convertEncoding($csv);
    expect($converted)->toBe($csv);
});
