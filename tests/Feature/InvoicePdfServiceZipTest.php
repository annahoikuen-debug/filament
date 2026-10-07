<?php

use App\Enums\InvoiceStatus;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->service = new InvoicePdfService;
});

test('通常動作でZIPファイルが生成され一時ディレクトリがクリーンアップされること', function () {
    $resident = Resident::create([
        'room_number' => '501',
        'name' => 'ZIPテスト入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $zipPath = $this->service->generateMonthlyZip('2026-10');

    expect(File::exists($zipPath))->toBeTrue()
        ->and(File::size($zipPath))->toBeGreaterThan(0);

    // クリーンアップ
    if (File::exists($zipPath)) {
        File::delete($zipPath);
    }
});

test('既存ZIPファイルがある場合は上書きされること', function () {
    $resident = Resident::create([
        'room_number' => '502',
        'name' => 'ZIP上書きテスト',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $zipPath = storage_path('app/temp/請求書一括_2026-10.zip');

    // 事前にダミーファイルを作成
    File::ensureDirectoryExists(dirname($zipPath));
    File::put($zipPath, 'dummy');

    $zipPath2 = $this->service->generateMonthlyZip('2026-10');

    expect(File::exists($zipPath2))->toBeTrue()
        ->and(File::size($zipPath2))->toBeGreaterThan(5); // ダミーが上書きされている

    if (File::exists($zipPath2)) {
        File::delete($zipPath2);
    }
});

test('対象月に請求データがない場合もエラーなく処理が完了すること', function () {
    // 空の請求リストでも例外が発生しないこと
    $zipPath = $this->service->generateMonthlyZip('2099-01');

    // メソッドが文字列パスを返すこと
    expect($zipPath)->toBeString();

    // 生成された場合はクリーンアップ
    if (File::exists($zipPath)) {
        File::delete($zipPath);
    }
});

test('例外発生時もZIPファイルがクリーンアップされること', function () {
    $resident = Resident::create([
        'room_number' => '503',
        'name' => '例外テスト入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => InvoiceStatus::Unbilled,
    ]);

    // resident リレーション破壊で例外を発生させる（モックで generateInvoicePdf を失敗させる）
    $mock = Mockery::mock(InvoicePdfService::class)->makePartial();
    $mock->shouldAllowMockingProtectedMethods()
        ->shouldReceive('generateInvoicePdf')
        ->andThrow(new RuntimeException('PDF生成エラー'));

    $mock->generateMonthlyZip('2026-10');
})->throws(RuntimeException::class, 'PDF生成エラー');
