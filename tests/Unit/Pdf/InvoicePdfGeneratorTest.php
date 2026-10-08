<?php

namespace Tests\Unit\Pdf;

use App\Services\Pdf\InvoicePdfGenerator;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvoicePdfGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private InvoicePdfGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = app(InvoicePdfGenerator::class);
    }

    public function test_preview_invoice_returns_html()
    {
        $resident = Resident::factory()->create([
            'name' => 'テスト太郎',
            'room_number' => '101',
        ]);

        $invoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-10',
        ]);

        $html = $this->generator->previewInvoice($invoice);

        $this->assertIsString($html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('テスト太郎', $html);
        $this->assertStringContainsString('101', $html);
        $this->assertStringContainsString('2026-10', $html);
        $this->assertStringContainsString('御 請 求 書', $html);
    }

    public function test_preview_receipt_returns_html()
    {
        $resident = Resident::factory()->create([
            'name' => 'テスト太郎',
            'room_number' => '101',
        ]);

        $invoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-10',
            'receipt_number' => 'REC-202610-001',
            'paid_at' => '2026-10-15 10:00:00',
        ]);

        $html = $this->generator->previewReceipt($invoice);

        $this->assertIsString($html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('テスト太郎', $html);
        $this->assertStringContainsString('領収証', $html);
        $this->assertStringContainsString('REC-202610-001', $html);
    }

    public function test_generate_invoice_returns_pdf_bytes()
    {
        $resident = Resident::factory()->create();
        $invoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-10',
        ]);

        $pdf = $this->generator->generateInvoice($invoice, false);

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_generate_receipt_returns_pdf_bytes()
    {
        $resident = Resident::factory()->create();
        $invoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-10',
            'paid_at' => '2026-10-15',
        ]);

        $pdf = $this->generator->generateReceipt($invoice, false);

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_generate_invoice_download_returns_response()
    {
        $resident = Resident::factory()->create();
        $invoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-10',
        ]);

        $response = $this->generator->generateInvoice($invoice, true);

        $this->assertInstanceOf(\Illuminate\Http\Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_stream_invoice_returns_response()
    {
        $resident = Resident::factory()->create();
        $invoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-10',
        ]);

        $response = $this->generator->streamInvoice($invoice);

        $this->assertInstanceOf(\Illuminate\Http\Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_generate_monthly_batch()
    {
        $resident1 = Resident::factory()->create(['name' => '入居者A', 'room_number' => '101']);
        $resident2 = Resident::factory()->create(['name' => '入居者B', 'room_number' => '102']);

        MonthlyInvoice::factory()->create([
            'resident_id' => $resident1->id,
            'billing_year_month' => '2026-10',
        ]);

        MonthlyInvoice::factory()->create([
            'resident_id' => $resident2->id,
            'billing_year_month' => '2026-10',
        ]);

        $results = $this->generator->generateMonthlyBatch('2026-10');

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('filename', $results[0]);
        $this->assertArrayHasKey('content', $results[0]);
        $this->assertStringContainsString('入居者A', $results[0]['filename']);
        $this->assertStringStartsWith('%PDF', $results[0]['content']);
        $this->assertStringStartsWith('%PDF', $results[1]['content']);
    }
}