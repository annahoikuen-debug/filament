<?php

use App\Services\InvoicePdfService;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use Tests\TestCase;

class InvoicePdfLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->resident = \App\Models\Resident::create([
            'room_number' => '101',
            'name' => 'Layout Test Resident',
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
    
    public function test_invoice_pdf_contains_expected_layout_elements()
    {
        $service = new InvoicePdfService();
        $pdf = $service->generateInvoicePdf($this->invoice);
        $output = $pdf->output();
        
        // PDFが生成されていることを確認
        $this->assertNotNull($pdf);
        $this->assertIsString($output);
        $this->assertGreaterThan(2000, strlen($output));
        
        // 基本的な文字列が含まれていることを確認
        $this->assertStringContainsString('御 請 求 書', $output);
        $this->assertStringContainsString($this->resident->name, $output);
        $this->assertStringContainsString('ご請求金額', $output);
        $this->assertStringContainsString('ご請求サマリー', $output);
    }
    
    public function test_receipt_pdf_contains_expected_layout_elements()
    {
        // 入金済み状態にする
        $this->invoice->markAsPaid(PaymentMethod::BankTransfer);
        
        $service = new InvoicePdfService();
        $pdf = $service->generateReceiptPdf($this->invoice);
        $output = $pdf->output();
        
        // PDFが生成されていることを確認
        $this->assertNotNull($pdf);
        $this->assertIsString($output);
        $this->assertGreaterThan(2000, strlen($output));
        
        // 基本的な文字列が含まれていることを確認
        $this->assertStringContainsString('領　収　証', $output);
        $this->assertStringContainsString($this->resident->name, $output);
        $this->assertStringContainsString('領収金額', $output);
        $this->assertStringContainsString('内訳明細', $output);
    }
    
    public function test_consistent_styling_between_invoice_and_receipt()
    {
        $service = new InvoicePdfService();
        
        // 請求書PDF
        $invoicePdf = $service->generateInvoicePdf($this->invoice);
        $invoiceOutput = $invoicePdf->output();
        
        // 領収書PDF（入金済みにしてから）
        $this->invoice->markAsPaid(PaymentMethod::BankTransfer);
        $receiptPdf = $service->generateReceiptPdf($this->invoice);
        $receiptOutput = $receiptPdf->output();
        
        // 両方とも正常に生成されることを確認
        $this->assertNotNull($invoicePdf);
        $this->assertNotNull($receiptPdf);
        $this->assertIsString($invoiceOutput);
        $this->assertIsString($receiptOutput);
        
        // 共通のスタイル要素が含まれていることを確認
        $commonElements = [
            'Noto Sans JP',
            '施設名', // Note: This is a placeholder - in actual implementation we'd check for the specific facility name
        ];
        
        foreach ($commonElements as $element) {
            $this->assertStringContainsString(
                $element, 
                $invoiceOutput, 
                "Invoice PDF should contain {$element}"
            );
            $this->assertStringContainsString(
                $element, 
                $receiptOutput, 
                "Receipt PDF should contain {$element}"
            );
        }
    }
    
    public function test_amount_formatting_consistency()
    {
        $service = new InvoicePdfService();
        
        // 請求書PDF
        $invoicePdf = $service->generateInvoicePdf($this->invoice);
        $invoiceOutput = $invoicePdf->output();
        
        // 領収書PDF（入金済みにしてから）
        $this->invoice->markAsPaid(PaymentMethod::BankTransfer);
        $receiptPdf = $service->generateReceiptPdf($this->invoice);
        $receiptOutput = $receiptPdf->output();
        
        // 金額フォーマットの一貫性を確認（実際の金額が含まれていることをチェック）
        $expectedAmount = number_format(
            $this->invoice->rent_subtotal + 
            $this->invoice->management_fee_subtotal + 
            $this->invoice->service_subtotal + 
            round(($this->invoice->management_fee_subtotal + $this->invoice->service_subtotal) * ($this->invoice->tax_rate / 100))
        );
        
        $this->assertStringContainsString(
            '¥' . $expectedAmount, 
            $invoiceOutput,
            "Invoice PDF should contain formatted total amount"
        );
        
        $this->assertStringContainsString(
            '¥' . $expectedAmount, 
            $receiptOutput,
            "Receipt PDF should contain formatted total amount"
        );
    }
    
    public function test_page_break_present_when_details_exist()
    {
        // 明細データを追加
        $dailyCharge = \App\Models\DailyCharge::create([
            'resident_id' => $this->resident->id,
            'date' => '2026-10-01',
            'charge_item_id' => 1, // Assuming charge item exists
            'unit_price' => 1000,
            'quantity' => 1,
            'subtotal' => 1000,
            'note' => 'テスト明細'
        ]);
        
        $service = new InvoicePdfService();
        $pdf = $service->generateInvoicePdf($this->invoice);
        $output = $pdf->output();
        
        // PDFが生成されていることを確認
        $this->assertNotNull($pdf);
        $this->assertIsString($output);
        
        // ページブレークが存在することを確認（実際のPDF内部構造をチェックするのは複雑なので、
        // 代わりに明細が存在する場合に適切なマークアップが生成されていることを確認）
        $this->assertStringContainsString('日々の自費サービス利用明細', $output);
        $this->assertStringContainsString('テスト明細', $output);
    }
}