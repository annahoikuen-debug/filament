<?php

use App\Services\InvoicePdfService;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use Tests\TestCase;

class InvoicePdfFeaturesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
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
        $service = new InvoicePdfService();
        $pdf = $service->generateInvoicePdf($this->invoice);
        
        $this->assertNotNull($pdf);
        $output = $pdf->output();
        $this->assertIsString($output);
        $this->assertGreaterThan(1000, strlen($output));
    }
    
    public function test_receipt_pdf_generates_after_payment()
    {
        // 入金済みにする
        $this->invoice->markAsPaid(PaymentMethod::BankTransfer);
        
        $service = new InvoicePdfService();
        $pdf = $service->generateReceiptPdf($this->invoice);
        
        $this->assertNotNull($pdf);
        $output = $pdf->output();
        $this->assertIsString($output);
        $this->assertGreaterThan(1000, strlen($output));
    }
    
    public function test_template_config_includes_compliance_settings()
    {
        $service = new InvoicePdfService();
        $config = $service->getTemplateConfig('invoice');
        
        $this->assertArrayHasKey('invoice_compliance', $config);
        $this->assertArrayHasKey('show_registration_number_prominently', $config['invoice_compliance']);
        $this->assertArrayHasKey('separate_tax_rates', $config['invoice_compliance']);
        $this->assertArrayHasKey('show_tax_breakdown_by_rate', $config['invoice_compliance']);
        
        // デフォルト値の確認
        $this->assertTrue($config['invoice_compliance']['show_registration_number_prominently']);
        $this->assertTrue($config['invoice_compliance']['separate_tax_rates']);
        $this->assertTrue($config['invoice_compliance']['show_tax_breakdown_by_rate']);
    }
    
    public function test_qr_code_generation_methods_exist()
    {
        $service = new InvoicePdfService();
        
        // メソッドが存在することを確認
        $this->assertTrue(method_exists($service, 'generatePaymentQrCode'));
        $this->assertTrue(method_exists($service, 'getBankTransferQrCodeData'));
        $this->assertTrue(method_exists($service, 'getReceiptVerificationQrCodeData'));
    }
    
    public function test_qr_code_data_is_set_when_enabled()
    {
        // QRコードを有効にするために、一時的に設定をオーバーライド
        config(['pdf.invoice.show_qr_code' => true]);
        
        $service = new InvoicePdfService();
        $pdf = $service->generateInvoicePdf($this->invoice);
        
        $this->assertNotNull($pdf);
        $output = $pdf->output();
        $this->assertIsString($output);
        
        // QRコードデータが含まれていることを確認（データURIのプレフィックスをチェック）
        $this->assertStringContainsString('data:image/svg+xml;base64,', $output);
        
        // 設定を元に戻す
        config(['pdf.invoice.show_qr_code' => false]);
    }
    
    public function test_receipt_qr_code_data_is_set_when_enabled()
    {
        // 入金済みにする
        $this->invoice->markAsPaid(PaymentMethod::BankTransfer);
        
        // QRコードを有効にするために、一時的に設定をオーバーライド
        config(['pdf.receipt.show_qr_code' => true]);
        
        $service = new InvoicePdfService();
        $pdf = $service->generateReceiptPdf($this->invoice);
        
        $this->assertNotNull($pdf);
        $output = $pdf->output();
        $this->assertIsString($output);
        
        // QRコードデータが含まれていることを確認（データURIのプレフィックスをチェック）
        $this->assertStringContainsString('data:image/svg+xml;base64,', $output);
        
        // 設定を元に戻す
        config(['pdf.receipt.show_qr_code' => false]);
    }
    
    public function test_monthly_zip_still_works_with_changes()
    {
        $service = new InvoicePdfService();
        
        $zipPath = $service->generateMonthlyZip('2026-10');
        
        $this->assertString($zipPath);
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
        
        $service = new InvoicePdfService();
        $pdf = $service->generateInvoicePdf($this->invoice);
        $output = $pdf->output();
        
        $this->assertNotNull($pdf);
        $this->assertIsString($output);
        
        // 登録番号がヘッダー部分に目立つ形で表示されていることを確認
        $registrationNumber = config('facility.invoice_registration_number', 'T1234567890123');
        $this->assertStringContainsString('登録番号: '.$registrationNumber, $output);
        
        // 設定を元に戻す
        config(['pdf.invoice.invoice_compliance.show_registration_number_prominently' => true]); // デフォルトはtrueなので戻す必要はないが、明示的に
    }
}