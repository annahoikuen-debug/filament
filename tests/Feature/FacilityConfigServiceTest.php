<?php

use App\Models\Facility;
use App\Services\FacilityConfigService;

it('returns config from database when facility exists', function () {
    $facility = Facility::factory()->create([
        'name' => 'テスト施設',
        'operator' => 'テスト法人',
        'postal_code' => '1234567',
        'address' => '東京都テスト区1-2-3',
        'phone' => '03-1234-5678',
        'fax' => '03-8765-4321',
        'invoice_registration_number' => 'T1234567890123',
    ]);

    $service = new FacilityConfigService;
    $config = $service->getConfig($facility->id);

    expect($config['name'])->toBe('テスト施設')
        ->and($config['operator'])->toBe('テスト法人')
        ->and($config['postal_code'])->toBe('1234567')
        ->and($config['address'])->toBe('東京都テスト区1-2-3')
        ->and($config['phone'])->toBe('03-1234-5678')
        ->and($config['fax'])->toBe('03-8765-4321')
        ->and($config['invoice_registration_number'])->toBe('T1234567890123');

    expect($service->isFromDatabase($facility->id))->toBeTrue();
});

it('falls back to config when no facility exists', function () {
    Config::set('facility', [
        'name' => '設定ファイル施設',
        'operator' => '設定法人',
        'bank' => ['name' => '設定銀行'],
        'billing' => ['direct_debit_day' => 15],
    ]);

    $service = new FacilityConfigService;
    $config = $service->getConfig();

    expect($config['name'])->toBe('設定ファイル施設')
        ->and($config['operator'])->toBe('設定法人')
        ->and($service->isFromDatabase())->toBeFalse();
});

it('returns array when no facility exists', function () {
    $service = new FacilityConfigService;

    expect($service->getConfig())->toBeArray();
});

it('getConfigOrFail throws for missing facility', function () {
    $service = new FacilityConfigService;

    $service->getConfigOrFail(99999);
})->throws(RuntimeException::class, '施設ID 99999 が見つかりません。');

it('getConfigOrFail returns config for existing facility', function () {
    $facility = Facility::factory()->create(['name' => 'OK施設']);

    $service = new FacilityConfigService;
    $config = $service->getConfigOrFail($facility->id);

    expect($config['name'])->toBe('OK施設');
});

it('provides individual getters', function () {
    $facility = Facility::factory()->create([
        'name' => 'ゲッター施設',
        'operator' => 'ゲッター法人',
        'postal_code' => '9999999',
        'address' => '大阪市テスト区',
        'phone' => '06-1111-2222',
        'fax' => '06-3333-4444',
        'invoice_registration_number' => 'T9999999999999',
    ]);

    $service = new FacilityConfigService;
    $id = $facility->id;

    expect($service->getName($id))->toBe('ゲッター施設')
        ->and($service->getOperator($id))->toBe('ゲッター法人')
        ->and($service->getPostalCode($id))->toBe('9999999')
        ->and($service->getAddress($id))->toBe('大阪市テスト区')
        ->and($service->getPhone($id))->toBe('06-1111-2222')
        ->and($service->getFax($id))->toBe('06-3333-4444')
        ->and($service->getInvoiceRegistrationNumber($id))->toBe('T9999999999999')
        ->and($service->getBank($id))->toBeArray()
        ->and($service->getBilling($id))->toBeArray()
        ->and($service->getBilling($id)['direct_debit_day'])->toBe(27)
        ->and($service->getBilling($id)['bank_transfer_due_days'])->toBe(30)
        ->and($service->getEmail($id))->toBeString()
        ->and($service->getFacility($id))->toBeInstanceOf(Facility::class)
        ->and($service->getAllActiveFacilities())->not->toBeEmpty();
});

it('getBank and getBilling return defaults when empty', function () {
    $service = new FacilityConfigService;

    expect($service->getBank())->toBeArray()
        ->and($service->getBilling())->toBeArray()
        ->and($service->getBilling()['direct_debit_day'])->toBe(27)
        ->and($service->getBilling()['bank_transfer_due_days'])->toBe(30);
});

it('returns null for seal/logo paths and missing facility', function () {
    $facility = Facility::factory()->create();

    $service = new FacilityConfigService;
    $id = $facility->id;

    expect($service->getSealPath($id))->toBeNull()
        ->and($service->getLogoPath($id))->toBeNull()
        ->and($service->getFacility(99999))->toBeNull();
});
