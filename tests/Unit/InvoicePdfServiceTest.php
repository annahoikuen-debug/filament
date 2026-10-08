<?php

use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use Mockery;
use ReflectionMethod;

beforeEach(function () {
    $this->resident = Resident::create([
        'room_number' => '101',
        'name' => 'テスト居住者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    $this->invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 10000,
        'total_amount' => 0, // will be synced by observer or mutator
        'tax_rate' => 10,
        'status' => \App\Enums\InvoiceStatus::Unbilled,
    ]);

    // Ensure total_amount is synced (assuming observer/mutator)
    $this->invoice->refresh();

    // Mock facility config
    Config::set('facility', [
        'name' => 'テスト施設',
        'operator' => 'テスト運営',
        'postal_code' => '123-4567',
        'address' => 'テスト住所 1-2-3',
        'phone' => '012-345-6789',
        'fax' => '012-345-6780',
        'invoice_registration_number' => 'T1234567890123',
        'bank' => [
            'name' => 'テスト銀行',
            'branch_name' => 'テスト支店',
            'account_type' => '普通',
            'account_number' => '1234567',
            'account_holder' => 'テスト施設',
        ],
        'billing' => [
            'direct_debit_day' => 5,
        ],
    ]);
});

/**
 * Call private method via reflection
 */
function invokePrivateMethod($object, $methodName, array $parameters = [])
{
    $reflection = new ReflectionMethod($object, $methodName);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs($object, $parameters);
}

afterEach(function () {
    Mockery::close();
});

test('銀行振込QRコードデータにBladeプレースホルダーが含まれないこと', function () {
    $pdfService = new InvoicePdfService();
    $data = invokePrivateMethod($pdfService, 'getBankTransferQrCodeData', [$this->invoice, Config::get('facility')]);

    expect($data)->not->toContain('{{')
        ->and($data)->not->toContain('}}')
        ->and($data)->toMatch('/STU{/')
        ->and($data)->toMatch('/REF:/');
});

test('銀行振込QRコードデータが正しいフォーマットであること', function () {
    $pdfService = new InvoicePdfService();
    $data = invokePrivateMethod($pdfService, 'getBankTransferQrCodeData', [$this->invoice, Config::get('facility')]);

    // Expected format: STU{...}
    expect($data)->toStartWith('STU{')
        ->and($data)->toEndWith('}');

    // Check that essential parts exist
    expect($data)->toContain('振:')
        ->and($data)->toContain('種:振込')
        ->and($data)->toContain('金:')
        ->and($data)->toContain('名:')
        ->and($data)->toContain('  współ:')
        ->and($data)->toContain(' 口:')
        ->and($data)->toContain(' 住:')
        ->and($data)->toContain('REF:');
});

test('QRコード生成メソッドが有効なデータURIを返すこと', function () {
    $pdfService = new InvoicePdfService();
    $qrData = invokePrivateMethod($pdfService, 'generatePaymentQrCode', ['test data']);

    expect($qrData)->toStartWith('data:image/svg+xml;base64,')
        ->and($qrData)->not->toBeEmpty();
});

test('QRコードが有効な場合でもPDFが一度しか生成されないこと', function () {
    $mockInstance = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $mockInstance->shouldReceive('setOptions')->andReturnSelf();
    $mockInstance->shouldReceive('setPaper')->andReturnSelf();

    // Facadeモック（Pdfファサードを経由してloadViewを1回だけ呼び出す）
    \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()->andReturn($mockInstance);

    // QRコードを有効化（getTemplateConfig はconfig参照のため Config::set で対応）
    Config::set('pdf.invoice.show_qr_code', true);

    $pdfService = new InvoicePdfService();
    $pdfService->generatePdfFromInvoice($this->invoice, 'invoice', null);

    // Assertions already done via mock expectations
});
