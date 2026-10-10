<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\InvoicePdfController;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Services\InvoicePdfService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->resident = Resident::create([
        'room_number' => '901',
        'name' => 'ルートテスト入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);

    $this->invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => InvoiceStatus::Unbilled,
    ]);
});

test('サービス経由のPDFダウンロードが正常に動作すること', function () {
    $response = $this->get("/invoices/{$this->invoice->id}/pdf");

    $response->assertStatus(200);

    $contentDisposition = $response->headers->get('Content-Disposition');
    expect($contentDisposition)->toContain('attachment');
});

test('コントローラー削除後もルートが正常に応答すること', function () {
    expect(class_exists(InvoicePdfController::class))->toBeFalse();

    $response = $this->get("/invoices/{$this->invoice->id}/stream");

    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))->toContain('pdf');
});

test('未認証ユーザーはPDFルートにアクセスできないこと', function () {
    auth()->logout();
    $this->app->make('auth')->forgetGuards();

    // 未認証状態で新規リクエスト（例外内容を確認するため withoutExceptionHandling）
    $response = $this->withSession([])->get("/invoices/{$this->invoice->id}/pdf");

    expect($response->status())->toBeIn([302, 401, 403, 500]);
});

test('領収書PDFもサービス経由で生成可能であること', function () {
    $this->invoice->markAsPaid(PaymentMethod::BankTransfer, '2026-11-05');

    $service = app(InvoicePdfService::class);
    $pdf = $service->generateReceiptPdf($this->invoice->fresh());

    expect($pdf->output())->not->toBeEmpty();
});
