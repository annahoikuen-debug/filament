<?php

use App\Enums\InvoiceStatus;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\Pdf\Contracts\RendererInterface;
use App\Services\Pdf\InvoicePdfGenerator;
use Illuminate\Http\Response;

beforeEach(function () {
    $this->facility = Facility::factory()->create(['name' => 'テスト施設']);
    $this->resident = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '301',
        'name' => '山田太郎',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-03-01',
    ]);
    $this->invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-03',
        'resident_id' => $this->resident->id,
        'facility_id' => $this->facility->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => InvoiceStatus::Billed,
    ]);

    // レンダラーをモック（PDFエンジンに依存しない）
    $this->mock(RendererInterface::class, function ($mock) {
        $mock->shouldReceive('render')->andReturnUsing(fn (string $html) => 'PDF_BINARY::'.md5($html));

        $makeResponse = function (string $filename, bool $attachment) {
            $type = $attachment ? 'attachment' : 'inline';
            $response = new Response('BINARY');
            $response->headers->set('Content-Disposition', $type.'; filename="'.$filename.'"');

            return $response;
        };

        $mock->shouldReceive('stream')->andReturnUsing(fn (string $h, string $f) => $makeResponse($f, false));
        $mock->shouldReceive('download')->andReturnUsing(fn (string $h, string $f) => $makeResponse($f, true));
    });

    $this->generator = app(InvoicePdfGenerator::class);
});

test('generateInvoice がPDFバイナリを返すこと', function () {
    $result = $this->generator->generateInvoice($this->invoice);

    expect($result)->toBeString()->toStartWith('PDF_BINARY::');
});

test('generateInvoice download=true でダウンロードレスポンスを返すこと', function () {
    $response = $this->generator->generateInvoice($this->invoice, true);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment');
});

test('streamInvoice がストリームレスポンスを返すこと', function () {
    $response = $this->generator->streamInvoice($this->invoice);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->headers->get('Content-Disposition'))->toContain('inline')
        ->and($response->headers->get('Content-Disposition'))->toContain('請求書_2026-03_山田太郎様_301.pdf');
});

test('generateReceipt がPDFバイナリを返すこと', function () {
    $result = $this->generator->generateReceipt($this->invoice);

    expect($result)->toBeString()->toStartWith('PDF_BINARY::');
});

test('generateReceipt download=true で領収証ファイル名のダウンロードレスポンスを返すこと', function () {
    $response = $this->generator->generateReceipt($this->invoice, true);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->headers->get('Content-Disposition'))->toContain('領収証_2026-03_山田太郎様_301.pdf');
});

test('streamReceipt がストリームレスポンスを返すこと', function () {
    $response = $this->generator->streamReceipt($this->invoice);

    expect($response)->toBeInstanceOf(Response::class);
});

test('previewInvoice / previewReceipt がHTMLを返すこと', function () {
    $invoiceHtml = $this->generator->previewInvoice($this->invoice);
    $receiptHtml = $this->generator->previewReceipt($this->invoice);

    expect($invoiceHtml)->toBeString()->not->toBe('')
        ->and($receiptHtml)->toBeString()->not->toBe('');
});

test('previewInvoiceFromData がDTO配列からHTMLを返すこと', function () {
   $data = \App\DTOs\Pdf\InvoicePdfData::fromInvoice($this->invoice->load('resident'))->toArray();

   $html = $this->generator->previewInvoiceFromData($data);

   expect($html)->toBeString()->not->toBe('');
});

test('generateMonthlyBatch が請求書配列を返すこと', function () {
    $results = $this->generator->generateMonthlyBatch('2026-03');

    expect($results)->toHaveCount(1)
        ->and($results[0]['invoice_id'])->toBe($this->invoice->id)
        ->and($results[0]['filename'])->toBe('【301号室】山田太郎様_請求書_2026-03.pdf')
        ->and($results[0]['content'])->toStartWith('PDF_BINARY::');
});

test('generateMonthlyBatchStream がGeneratorを返すこと', function () {
    $items = iterator_to_array($this->generator->generateMonthlyBatchStream('2026-03'));

    expect($items)->toHaveCount(1)
        ->and($items[0]['filename'])->toBe('【301号室】山田太郎様_請求書_2026-03.pdf')
        ->and($items[0]['html'])->toBeString()->not->toBe('');
});

test('generateMonthlyZipStream がフォールバック方式でZIPに書き込むこと', function () {
    $zipPath = tempnam(sys_get_temp_dir(), 'zip').'.zip';
    $zip = new ZipArchive;
    expect($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    $count = $this->generator->generateMonthlyZipStream('2026-03', $zip);
    $zip->close();

    expect($count)->toBe(1);

    $zip = new ZipArchive;
    $zip->open($zipPath);
    expect($zip->locateName('【301号室】山田太郎様_請求書_2026-03.pdf'))->not->toBeFalse();
    $zip->close();

    @unlink($zipPath);
});

test('generateMonthlyZipStream は対象データがなければ0を返すこと', function () {
    $zipPath = tempnam(sys_get_temp_dir(), 'zip').'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $count = $this->generator->generateMonthlyZipStream('2099-01', $zip);
    $zip->close();

    expect($count)->toBe(0);
    @unlink($zipPath);
});

test('generateInvoiceFilename がファイル名を返すこと', function () {
    expect($this->generator->generateInvoiceFilename($this->invoice))
        ->toBe('請求書_2026-03_山田太郎様_301.pdf');
});
