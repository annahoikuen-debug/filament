<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Config;

test('正常な施設設定では例外が発生しないこと', function () {
    Config::set('facility.invoice_registration_number', 'T1234567890123');
    Config::set('facility.bank', [
        'name' => 'テスト銀行',
        'branch_name' => 'テスト支店',
        'account_type' => '普通',
        'account_number' => '1234567',
        'account_holder' => 'カ）テスト',
    ]);

    $provider = new AppServiceProvider(app());
    $provider->boot();

    expect(true)->toBeTrue();
});

test('不正な請求書登録番号でRuntimeExceptionが発生すること', function () {
    Config::set('facility.invoice_registration_number', 'INVALID123');

    $provider = new AppServiceProvider(app());
    $provider->boot();
})->throws(RuntimeException::class, '施設登録番号の形式が不正です');

test('Tから始まっても桁数が13桁でない登録番号でRuntimeExceptionが発生すること', function () {
    Config::set('facility.invoice_registration_number', 'T123');

    $provider = new AppServiceProvider(app());
    $provider->boot();
})->throws(RuntimeException::class);

test('銀行情報に必須項目が不足している場合RuntimeExceptionが発生すること', function () {
    Config::set('facility.invoice_registration_number', null);
    Config::set('facility.bank', [
        'name' => 'テスト銀行',
        'account_number' => null,
        'account_holder' => null,
    ]);

    $provider = new AppServiceProvider(app());
    $provider->boot();
})->throws(RuntimeException::class, '銀行情報に必須項目が不足しています');

test('facility設定が空の場合は例外が発生しないこと', function () {
    Config::set('facility', []);

    $provider = new AppServiceProvider(app());
    $provider->boot();

    expect(true)->toBeTrue();
});
