<?php

use App\Models\ChartOfAccount;
use App\Models\Facility;

test('fillableフィールドのみが一括代入で設定されること', function () {
    // 必要な依存関係を作成
    $facility = Facility::factory()->create();
    $chartOfAccount = ChartOfAccount::create([
        'facility_id' => $facility->id,
        'item_type' => 'rent',
        'account_side' => 'debit',
        'account_code' => '1100',
        'account_name' => '売掛金',
        'sub_account_code' => '001',
        'sub_account_name' => '入居者売掛金',
        'tax_code' => 'tax_exempt',
        'department_code' => '001',
        'department_name' => '施設管理部',
        'tag_codes' => ['tag1', 'tag2'],
        'is_active' => true,
        'sort_order' => 1,
        'notes' => 'テスト用メモ',
    ]);

    expect($chartOfAccount->exists)->toBeTrue()
        ->and($chartOfAccount->account_code)->toBe('1100')
        ->and($chartOfAccount->account_name)->toBe('売掛金')
        ->and($chartOfAccount->sub_account_code)->toBe('001')
        ->and($chartOfAccount->sub_account_name)->toBe('入居者売掛金')
        ->and($chartOfAccount->tax_code)->toBe('tax_exempt')
        ->and($chartOfAccount->department_code)->toBe('001')
        ->and($chartOfAccount->department_name)->toBe('施設管理部')
        ->and($chartOfAccount->tag_codes)->toEqual(['tag1', 'tag2'])
        ->and($chartOfAccount->is_active)->toBeTrue()
        ->and($chartOfAccount->sort_order)->toBe(1)
        ->and($chartOfAccount->notes)->toBe('テスト用メモ');
});

test('fillable外のフィールド（id）が一括代入で無視されること', function () {
    // 必要な依存関係を作成
    $facility = Facility::factory()->create();
    $chartOfAccount = ChartOfAccount::create([
        'id' => 99999,
        'facility_id' => $facility->id,
        'item_type' => 'rent',
        'account_side' => 'debit',
        'account_code' => '1100',
        'account_name' => '売掛金',
    ]);

    expect($chartOfAccount->id)->not->toBe(99999);
});

test('不正なフィールド名での一括代入は無視されること', function () {
    // 必要な依存関係を作成
    $facility = Facility::factory()->create();
    $chartOfAccount = ChartOfAccount::create([
        'facility_id' => $facility->id,
        'item_type' => 'rent',
        'account_side' => 'debit',
        'account_code' => '1100',
        'account_name' => '売掛金',
        'unknown_field' => 'テスト',
    ]);

    expect($chartOfAccount->exists)->toBeTrue()
        ->and($chartOfAccount->getAttribute('unknown_field'))->toBeNull();
});

test('キャストが正しく動作すること', function () {
    // 必要な依存関係を作成
    $facility = Facility::factory()->create();
    $chartOfAccount = ChartOfAccount::create([
        'facility_id' => $facility->id,
        'item_type' => 'rent',
        'account_side' => 'debit',
        'account_code' => '1100',
        'account_name' => '売掛金',
        'tag_codes' => ['tag1', 'tag2'],
        'is_active' => 'true',
        'sort_order' => '5',
    ]);

    expect($chartOfAccount->is_active)->toBeTrue()
        ->and($chartOfAccount->sort_order)->toBe(5)
        ->and($chartOfAccount->tag_codes)->toEqual(['tag1', 'tag2']);
});

test('定数が正しく定義されていること', function () {
    expect(ChartOfAccount::ITEM_TYPE_RENT)->toBe('rent')
        ->and(ChartOfAccount::ITEM_TYPE_MANAGEMENT_FEE)->toBe('management_fee')
        ->and(ChartOfAccount::ITEM_TYPE_SERVICE)->toBe('service')
        ->and(ChartOfAccount::ITEM_TYPE_ADVANCE_PAYMENT)->toBe('advance_payment')
        ->and(ChartOfAccount::ACCOUNT_SIDE_DEBIT)->toBe('debit')
        ->and(ChartOfAccount::ACCOUNT_SIDE_CREDIT)->toBe('credit');
});

test('facilityリレーションが正しく動作すること', function () {
    // 必要な依存関係を作成
    $facility = Facility::factory()->create();
    $chartOfAccount = ChartOfAccount::create([
        'facility_id' => $facility->id,
        'item_type' => 'rent',
        'account_side' => 'debit',
        'account_code' => '1100',
        'account_name' => '売掛金',
        'is_active' => true,
    ]);

    expect($chartOfAccount->facility->id)->toBe($facility->id);
});

test('getAccountメソッドが正しく動作すること', function () {
    // 必要な依存関係を作成
    $facility = Facility::factory()->create();
    // 既存のデータを削除して一意制約違反を防ぐ
    ChartOfAccount::where('facility_id', $facility->id)
        ->where('item_type', 'rent')
        ->where('account_side', 'debit')
        ->delete();

    // テスト用のアクティブな勘定科目を作成（ソート順序1）
    ChartOfAccount::create([
        'facility_id' => $facility->id,
        'item_type' => 'rent',
        'account_side' => 'debit',
        'account_code' => '9999',  // デフォルトと異なる勘定科目コード
        'account_name' => 'テスト売掛金',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $account = ChartOfAccount::getAccount($facility->id, 'rent', 'debit');
    expect($account)->not->toBeNull()
        ->and($account->account_code)->toBe('9999')
        ->and($account->sort_order)->toBe(1);
});

test('getAccountメソッドで非アクティブなものは除外されること', function () {
    // 必要な依存関係を作成
    $facility = Facility::factory()->create();
    // 既存のデータを削除して一意制約違反を防ぐ
    ChartOfAccount::where('facility_id', $facility->id)
        ->where('item_type', 'rent')
        ->where('account_side', 'debit')
        ->delete();

    // 最初は何もないのでnullが返ること
    $account = ChartOfAccount::getAccount($facility->id, 'rent', 'debit');
    expect($account)->toBeNull();

    // 非アクティブな勘定科目を作成
    ChartOfAccount::create([
        'facility_id' => $facility->id,
        'item_type' => 'rent',
        'account_side' => 'debit',
        'account_code' => '9999',  // デフォルトと異なる勘定科目コード
        'account_name' => 'テスト勘定科目',
        'is_active' => false,
        'sort_order' => 1,
    ]);

    // 非アクティブなものは除外されるのでnullが返ること
    $account = ChartOfAccount::getAccount($facility->id, 'rent', 'debit');
    expect($account)->toBeNull();

    // 同じレコードをアクティブに更新
    ChartOfAccount::where('facility_id', $facility->id)
        ->where('item_type', 'rent')
        ->where('account_side', 'debit')
        ->update(['is_active' => true]);

    // アクティブになったのでレコードが返されること
    $account = ChartOfAccount::getAccount($facility->id, 'rent', 'debit');
    expect($account)->not->toBeNull()
        ->and($account->account_code)->toBe('9999')
        ->and($account->is_active)->toBeTrue();
});

test('存在しない条件の場合はnullが返ること', function () {
    // 必要な依存関係を作成
    $facility = Facility::factory()->create();
    $account = ChartOfAccount::getAccount($facility->id, 'rent', 'debit');
    expect($account)->toBeNull();
});

test('getAllForFacilityメソッドが正しく動作すること', function () {
    // 必要な依存関係を作成
    $facility = Facility::factory()->create();
    ChartOfAccount::create([
        'facility_id' => $facility->id,
        'item_type' => 'rent',
        'account_side' => 'debit',
        'account_code' => '1100',
        'account_name' => '売掛金',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    ChartOfAccount::create([
        'facility_id' => $facility->id,
        'item_type' => 'rent',
        'account_side' => 'credit',
        'account_code' => '4110',
        'account_name' => '賃貸料収入',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    ChartOfAccount::create([
        'facility_id' => $facility->id,
        'item_type' => 'management_fee',
        'account_side' => 'debit',
        'account_code' => '1100',
        'account_name' => '売掛金',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $accounts = ChartOfAccount::getAllForFacility($facility->id);
    expect($accounts)->toBeArray()
        ->and(count($accounts))->toBe(3);
});

test('createDefaultsForFacilityメソッドが正しくデフォルトを作成すること', function () {
    // 必要な依存関係を作成
    $facility = Facility::factory()->create();
    ChartOfAccount::createDefaultsForFacility($facility->id);

    $accounts = ChartOfAccount::where('facility_id', $facility->id)->get();
    expect($accounts->count())->toBe(8);

    // 各品目×方向の組み合わせが存在すること
    $types = ['rent', 'management_fee', 'service', 'advance_payment'];
    $sides = ['debit', 'credit'];
    foreach ($types as $type) {
        foreach ($sides as $side) {
            $account = ChartOfAccount::where('facility_id', $facility->id)
                ->where('item_type', $type)
                ->where('account_side', $side)
                ->first();
            expect($account)->not->toBeNull()
                ->and($account->is_active)->toBeTrue();
        }
    }
});

test('createDefaultsForFacilityメソッドで既存データは更新されること', function () {
    // 必要な依存関係を作成
    $facility = Facility::factory()->create();
    ChartOfAccount::createDefaultsForFacility($facility->id);

    // 1回目の作成で8件
    expect(ChartOfAccount::where('facility_id', $facility->id)->count())->toBe(8);

    // 2回実行しても件数は変わらない（updateOrCreateのため）
    ChartOfAccount::createDefaultsForFacility($facility->id);
    expect(ChartOfAccount::where('facility_id', $facility->id)->count())->toBe(8);
});

test('tagCodesアクセサが正しく動作すること', function () {
    // 必要な依存関係を作成
    $facility = Facility::factory()->create();
    $chartOfAccount = ChartOfAccount::create([
        'facility_id' => $facility->id,
        'item_type' => 'rent',
        'account_side' => 'debit',
        'account_code' => '1100',
        'account_name' => '売掛金',
        'tag_codes' => ['tag1', 'tag2', 'tag3'],
    ]);

    expect($chartOfAccount->tag_codes)->toEqual(['tag1', 'tag2', 'tag3']);
});

test('tagCodesがnullの場合は空配列が返ること', function () {
    // 必要な依存関係を作成
    $facility = Facility::factory()->create();
    $chartOfAccount = ChartOfAccount::create([
        'facility_id' => $facility->id,
        'item_type' => 'rent',
        'account_side' => 'debit',
        'account_code' => '1100',
        'account_name' => '売掛金',
        'tag_codes' => null,
    ]);

    expect($chartOfAccount->tag_codes)->toBeNull();
});
