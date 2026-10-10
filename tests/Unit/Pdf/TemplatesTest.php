<?php

namespace Tests\Unit\Pdf;

use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\Pdf\Templates\InvoiceTemplate;
use App\Services\Pdf\Templates\ReceiptTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_template_get_required_fonts(): void
    {
        $this->assertSame(
            ['ipaexg', 'YuMincho', 'YuGothic', 'Meiryo', 'msgothic'],
            (new InvoiceTemplate)->getRequiredFonts()
        );
    }

    public function test_receipt_template_get_required_fonts(): void
    {
        $this->assertSame(
            ['ipaexg', 'YuMincho', 'YuGothic', 'Meiryo', 'msgothic'],
            (new ReceiptTemplate)->getRequiredFonts()
        );
    }

    public function test_invoice_template_get_css_uses_file(): void
    {
        $css = (new InvoiceTemplate)->getCss();

        $this->assertNotEmpty($css);
        // resources/css/pdf-invoice.css が存在するためファイル内容が返る
        $this->assertStringContainsString('@page', $css);
    }

    public function test_receipt_template_get_css_uses_file(): void
    {
        $css = (new ReceiptTemplate)->getCss();

        $this->assertNotEmpty($css);
        $this->assertStringContainsString('@page', $css);
    }

    public function test_invoice_template_get_css_falls_back_to_default(): void
    {
        // 実ファイルを退避してフォールバック経路をカバー
        $cssPath = resource_path('css/pdf-invoice.css');
        $backup = $cssPath.'.bak_test';

        if (file_exists($cssPath)) {
            rename($cssPath, $backup);
        }

        try {
            $css = (new InvoiceTemplate)->getCss();
            $this->assertStringContainsString('@page { margin: 15mm; }', $css);
        } finally {
            if (file_exists($backup)) {
                rename($backup, $cssPath);
            }
        }
    }

    public function test_receipt_template_get_css_falls_back_to_default(): void
    {
        $cssPath = resource_path('css/pdf-receipt.css');
        $backup = $cssPath.'.bak_test';

        if (file_exists($cssPath)) {
            rename($cssPath, $backup);
        }

        try {
            $css = (new ReceiptTemplate)->getCss();
            $this->assertStringContainsString('@page { margin: 15mm; }', $css);
        } finally {
            if (file_exists($backup)) {
                rename($backup, $cssPath);
            }
        }
    }

    public function test_invoice_template_render_returns_html(): void
    {
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'テスト太郎',
        ]);
        $invoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'facility_id' => $facility->id,
        ]);

        $data = app(\App\Services\Pdf\DataProviders\InvoiceDataProvider::class)->getInvoiceData($invoice);

        $html = (new InvoiceTemplate)->render($data->toArray());

        $this->assertNotEmpty($html);
    }

    public function test_receipt_template_render_returns_html(): void
    {
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'name' => 'テスト太郎',
        ]);
        $invoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'facility_id' => $facility->id,
        ]);

        $data = app(\App\Services\Pdf\DataProviders\InvoiceDataProvider::class)->getReceiptData($invoice);

        $html = (new ReceiptTemplate)->render($data->toArray());

        $this->assertNotEmpty($html);
    }
}
