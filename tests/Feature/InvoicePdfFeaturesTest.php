<?php

use App\Services\InvoicePdfService;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class InvoicePdfFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private InvoicePdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoicePdfService::class);
        
        $this->resident = \App\Models\Resident::create([
            'room_number' => '101',
            'name' => 'Feature Test Resident',
            'move_in_date' => '2026-01-01',
        ]);
        
        $this->invoice = \App\Models\MonthlyInvoice::create([
            'billing_year_month' => '2026-10',
            'resident_id' => $this->resident->id,
            'rent_subtotal' => 65000,
            'management_fee_subtotal' => 30000,
            'service_subtotal' => 5000,
            'status' => InvoiceStatus::Unbilled,
        ]);
    }
    
    public function test_invoice_pdf_generates_without_error()
    {
        $pdf = $this->service->generateInvoicePdf($this->invoice);
        
        $this->assertNotNull($pdf);
        $output = $pdf->output();
        $this->assertIsString($output);
        $this->assertGreaterThan(1000, strlen($output));
    }
    
    public function test_receipt_pdf_generates_after_payment()
    {
        // 入金済みにする
        $this->invoice->markAsPaid(PaymentMethod::BankTransfer);
        
        $pdf = $this->service->generateReceiptPdf($this->invoice);

        $this->assertNotNull($pdf);
        $output = $pdf->output();
        $this->assertIsString($output);
        $this->assertGreaterThan(1000, strlen($output));
    }
    
    public function test_template_config_includes_compliance_settings()
    {
        $config = $this->service->getTemplateConfig('invoice');
        
        $this->assertArrayHasKey('invoice_compliance', $config);
        $this->assertArrayHasKey('show_registration_number_prominently', $config['invoice_compliance']);
        $this->assertArrayHasKey('separate_tax_rates', $config['invoice_compliance']);
        $this->assertArrayHasKey('show_tax_breakdown_by_rate', $config['invoice_compliance']);
        
        // デフォルト値の確認
        $this->assertTrue($config['invoice_compliance']['show_registration_number_prominently']);
        $this->assertTrue($config['invoice_compliance']['separate_tax_rates']);
        $this->assertTrue($config['invoice_compliance']['show_tax_breakdown_by_rate']);
    }
    
    /**
     * PDFの代わりにBladeビューをレンダリングし、HTML文字列を返す
     * （PDFバイナリは圧縮されるため、レイアウト検証はHTMLで行う）
     */
    protected function renderPdfHtml($invoice, string $type): string
    {
        [$view, $data] = $this->service->prepareViewData($invoice, $type);

        return view($view, $data)->render();
    }

    public function test_qr_code_generation_methods_exist()
    {
        // メソッドが存在することを確認
        $this->assertTrue(method_exists($this->service, 'generatePaymentQrCode'));
        $this->assertTrue(method_exists($this->service, 'getBankTransferQrCodeData'));
        $this->assertTrue(method_exists($this->service, 'getReceiptVerificationQrCodeData'));
    }
    
    public function test_qr_code_data_is_set_when_enabled()
    {
        // QRコードを有効にするために、一時的に設定をオーバーライド
        config(['pdf.invoice.show_qr_code' => true]);
        
        // BladeビューのHTMLにQRコードのデータURIが含まれることを確認
        $html = $this->renderPdfHtml($this->invoice, 'invoice');
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        
        // 設定を元に戻す
        config(['pdf.invoice.show_qr_code' => false]);
    }
    
    public function test_receipt_qr_code_data_is_set_when_enabled()
    {
        // 入金済みにする
        $this->invoice->markAsPaid(PaymentMethod::BankTransfer);
        
        // QRコードを有効にするために、一時的に設定をオーバーライド
        config(['pdf.receipt.show_qr_code' => true]);
        
        // BladeビューのHTMLにQRコードのデータURIが含まれることを確認
        $html = $this->renderPdfHtml($this->invoice, 'receipt');
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        
        // 設定を元に戻す
        config(['pdf.receipt.show_qr_code' => false]);
    }
    
    public function test_monthly_zip_still_works_with_changes()
    {
        $zipPath = $this->service->generateMonthlyZip('2026-10');
        
        $this->assertIsString($zipPath);
        $this->assertTrue(file_exists($zipPath));
        $this->assertGreaterThan(0, filesize($zipPath));
        
        // クリーンアップ
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }
    }
    
    public function test_registration_number_displayed_prominently_when_configured()
    {
        // 登録番号の目立つ表示を有効にする
        config(['pdf.invoice.invoice_compliance.show_registration_number_prominently' => true]);
        
        // 施設の登録番号を設定
        Config::set('facility.invoice_registration_number', 'T1234567890123');
        
        // BladeビューのHTMLに登録番号が目立つ形で表示されていることを確認
        $html = $this->renderPdfHtml($this->invoice, 'invoice');
        $registrationNumber = config('facility.invoice_registration_number', 'T1234567890123');
        $this->assertStringContainsString('登録番号: '.$registrationNumber, $html);
        
        // 設定を元に戻す
        config(['pdf.invoice.invoice_compliance.show_registration_number_prominently' => true]); // デフォルトはtrueなので戻す必要はないが、明示的に
    }
}