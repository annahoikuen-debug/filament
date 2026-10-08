<?php

use App\Services\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfDesignSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_design_tokens_are_available_in_template_config()
    {
        $service = new InvoicePdfService();
        
        // 請求書のテンプレート設定を取得
        $config = $service->getTemplateConfig('invoice');
        
        // カラーパレットの存在確認
        $this->assertArrayHasKey('colors', $config);
        $this->assertArrayHasKey('primary', $config['colors']);
        $this->assertEquals('#1e3a8a', $config['colors']['primary']);
        
        $this->assertArrayHasKey('typography', $config);
        $this->assertArrayHasKey('font_family', $config['typography']);
        $this->assertStringContainsString('Noto Sans JP', $config['typography']['font_family']);
        
        $this->assertArrayHasKey('spacing', $config);
        $this->assertArrayHasKey('page_margin', $config['spacing']);
        $this->assertEquals(15, $config['spacing']['page_margin']);
    }
    
    public function test_backward_compatibility_of_existing_config_keys()
    {
        $service = new InvoicePdfService();
        $config = $service->getTemplateConfig('invoice');
        
        // 既存コードが参照するキーは残存することを確認
        $this->assertArrayHasKey('primary_color', $config);
        $this->assertArrayHasKey('font_size', $config);
        $this->assertArrayHasKey('margin_top', $config);
        $this->assertArrayHasKey('paper_size', $config);
    }
    
    public function test_pdf_generation_works_with_new_design_system()
    {
        // 既存のテストパターンを流用してPDF生成が正常に動作することを確認
        $this->resident = \App\Models\Resident::create([
            'room_number' => '101',
            'name' => 'Design System Test',
            'move_in_date' => '2026-01-01',
        ]);
        
        $invoice = \App\Models\MonthlyInvoice::create([
            'billing_year_month' => '2026-10',
            'resident_id' => $this->resident->id,
            'rent_subtotal' => 65000,
            'management_fee_subtotal' => 30000,
            'service_subtotal' => 3000,
            'status' => \App\Enums\InvoiceStatus::Unbilled,
        ]);
        
        $service = new InvoicePdfService();
        $pdf = $service->generateInvoicePdf($invoice);
        
        $this->assertNotNull($pdf);
        $output = $pdf->output();
        $this->assertIsString($output);
        $this->assertGreaterThan(1000, strlen($output)); // 十分なサイズがあることを確認
    }
}