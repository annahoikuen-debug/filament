<?php

use App\Enums\InvoiceStatus;
use App\Models\AccountingExportProfile;
use App\Models\ChargeItem;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\Accounting\AccountingSyncService;
use App\Services\InvoiceCalculationService;

beforeEach(function () {
    $this->service = app(AccountingSyncService::class);
    $this->calcService = app(InvoiceCalculationService::class);

    $this->facility = Facility::factory()->create([
        'name' => 'テスト施設',
        'invoice_registration_number' => 'T1234567890123',
    ]);

    // freee 認証情報を設定（モック用）
    $this->facility->update([
        'freee_client_id' => 'test_client_id',
        'freee_client_secret' => 'test_client_secret',
        'freee_access_token' => 'test_access_token',
        'freee_refresh_token' => 'test_refresh_token',
        'freee_token_expires_at' => now()->addHours(1),
        'freee_company_id' => 12345,
        'accounting_api_settings' => [
            'sync_enabled' => true,
            'auto_sync_on_export' => false,
        ],
    ]);

    $this->chargeItem = ChargeItem::factory()->create([
        'name' => '自費サービス',
        'default_price' => 1000,
    ]);

    // プロファイル作成
    $this->profile = AccountingExportProfile::createDefaultsForFacility($this->facility->id);
    $this->profile = AccountingExportProfile::getDefault($this->facility->id, AccountingExportProfile::SOFTWARE_FREEE);
});

test('認証情報なしの場合はエラーになること', function () {
    $facility = Facility::factory()->create([
        'name' => '認証なし施設',
        'invoice_registration_number' => 'T9876543210987',
    ]);

    $result = $this->service->syncForMonth('2026-10', $facility->id, 'freee');

    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

test('会計ソフト一覧取得が正しく動作すること', function () {
    $providers = $this->service->getAvailableProviders($this->facility->id);

    expect($providers)->toBeArray()
        ->and(count($providers))->toBe(2)
        ->and($providers[0]['key'])->toBe('freee')
        ->and($providers[0]['configured'])->toBeTrue()
        ->and($providers[1]['key'])->toBe('mf')
        ->and($providers[1]['configured'])->toBeFalse();
});

test('請求データがない場合は成功で0件返すこと', function () {
    // 未来の年月で請求データがない場合
    $result = $this->service->syncForMonth('2099-12', $this->facility->id, 'freee', $this->profile->id);

    // データがない場合は成功で0件返す（接続テスト前にチェックされる）
    expect($result['success'])->toBeTrue()
        ->and($result['synced_count'])->toBe(0);
});

test('入居者と請求データ作成後の同期フロー（モック版）', function () {
    $resident = Resident::factory()->create([
        'facility_id' => $this->facility->id,
        'room_number' => '101',
        'name' => 'テスト入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    // 請求データ生成
    $stats = $this->calcService->generateForMonth('2026-10');
    expect($stats['created'])->toBe(1);

    $invoice = MonthlyInvoice::where('billing_year_month', '2026-10')->first();
    expect($invoice)->not->toBeNull();

    // 請求確定ステータスに変更（同期対象にするため）
    $invoice->update(['status' => InvoiceStatus::Billed]);

    // 同期実行（実際のAPIはモックしていないため接続テストで失敗するが、ロジックは通る）
    $result = $this->service->syncForMonth('2026-10', $this->facility->id, 'freee', $this->profile->id);

    // 接続テストで失敗する想定
    expect($result['success'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

test('プロバイダー解決が正しく動作すること', function () {
    // freee認証あり → freeeが返る
    $softwareType = (new ReflectionClass($this->service))
        ->getMethod('resolveSoftwareType')
        ->invoke($this->service, $this->facility, null);

    expect($softwareType)->toBe('freee');
});
