<?php

namespace Tests\Feature;

use App\Mail\InvoiceMail;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\ServiceInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceMailTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;

    private Resident $resident;

    private MonthlyInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facility = Facility::factory()->create();
        $this->resident = Resident::create([
            'facility_id' => $this->facility->id,
            'name' => '佐藤太郎',
            'name_kana' => 'サトウタロウ',
            'room_number' => '101',
            'status' => 'active',
            'base_rent' => 50000,
            'base_management_fee' => 20000,
        ]);
        $this->invoice = MonthlyInvoice::create([
            'resident_id' => $this->resident->id,
            'billing_year_month' => '2026-03',
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 0,
            'total_amount' => 70000,
            'status' => 'billed',
        ]);
    }

    public function test_content_passes_view_data(): void
    {
        $mail = new InvoiceMail($this->invoice, 'https://example.com/invoice.pdf', 'よろしくお願いします', true);

        $content = $mail->content();

        $this->assertSame('emails.invoice', $content->view);
        $this->assertSame($this->invoice->id, $content->with['invoice']->id);
        $this->assertSame('https://example.com/invoice.pdf', $content->with['pdfUrl']);
        $this->assertSame('よろしくお願いします', $content->with['message']);
        $this->assertTrue($content->with['includeCareServices']);
        $this->assertTrue($content->with['careServices'] instanceof \Illuminate\Support\Collection);
    }

    public function test_envelope_contains_subject_and_suffix_without_care_services(): void
    {
        $mail = new InvoiceMail($this->invoice, 'https://example.com/invoice.pdf');

        $this->assertSame(
            '【請求書】2026-03月分 佐藤太郎様',
            $mail->envelope()->subject
        );
    }

    public function test_envelope_contains_care_services_suffix(): void
    {
        $mail = new InvoiceMail($this->invoice, 'https://example.com/invoice.pdf', includeCareServices: true);

        $this->assertSame(
            '【請求書】2026-03月分 佐藤太郎様（介護サービス含む）',
            $mail->envelope()->subject
        );
    }

    public function test_attachments_fetches_pdf_from_url(): void
    {
        Http::fake([
            'https://example.com/invoice.pdf' => Http::response('%PDF-1.4 fake invoice', 200),
        ]);

        $mail = new InvoiceMail($this->invoice, 'https://example.com/invoice.pdf');
        $attachments = $mail->attachments();

        $this->assertCount(1, $attachments);
        $this->assertInstanceOf(\Illuminate\Mail\Mailables\Attachment::class, $attachments[0]);

        $this->assertSame('請求書_2026-03_101号室_佐藤太郎.pdf', $attachments[0]->as);
        $this->assertSame('application/pdf', $attachments[0]->mime);
    }

    public function test_attachments_skips_failed_url_response(): void
    {
        Http::fake([
            'https://example.com/invoice.pdf' => Http::response('Not Found', 404),
        ]);

        $mail = new InvoiceMail($this->invoice, 'https://example.com/invoice.pdf');

        $this->assertCount(0, $mail->attachments());
    }

    public function test_attachments_handles_http_exception(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('connection timeout');
        });

        $mail = new InvoiceMail($this->invoice, 'https://example.com/invoice.pdf');

        $this->assertCount(0, $mail->attachments());
    }

    public function test_attachments_empty_when_no_pdf_url(): void
    {
        $mail = new InvoiceMail($this->invoice, '');

        $this->assertCount(0, $mail->attachments());
    }

    public function test_attachments_includes_care_service_pdfs(): void
    {
        // hasPdf() は実ファイルシステムを参照するため実ディスクに配置する
        $careDir = storage_path('app/care');
        if (! is_dir($careDir)) {
            mkdir($careDir, 0777, true);
        }
        file_put_contents($careDir.'/visiting_care_2026-03.pdf', '%PDF-1.4 care1');
        file_put_contents($careDir.'/day_care_2026-03.pdf', '%PDF-1.4 care2');
        Http::fake([
            'https://example.com/invoice.pdf' => Http::response('%PDF-1.4 fake invoice', 200),
        ]);

        ServiceInvoice::create([
            'facility_id' => $this->facility->id,
            'resident_id' => $this->resident->id,
            'billing_year_month' => '2026-03',
            'service_type' => 'visiting_care',
            'service_type_label' => '訪問介護',
            'amount' => 30000,
            'tax_amount' => 3000,
            'pdf_path' => 'care/visiting_care_2026-03.pdf',
            'status' => 'confirmed',
        ]);
        ServiceInvoice::create([
            'facility_id' => $this->facility->id,
            'resident_id' => $this->resident->id,
            'billing_year_month' => '2026-03',
            'service_type' => 'day_care',
            'service_type_label' => '通所介護',
            'amount' => 20000,
            'tax_amount' => 2000,
            'pdf_path' => 'care/day_care_2026-03.pdf',
            'status' => 'sent',
        ]);
        // draft は対象外
        ServiceInvoice::create([
            'facility_id' => $this->facility->id,
            'resident_id' => $this->resident->id,
            'billing_year_month' => '2026-03',
            'service_type' => 'other',
            'service_type_label' => 'その他',
            'amount' => 1000,
            'pdf_path' => 'care/other_2026-03.pdf',
            'status' => 'draft',
        ]);
        // pdf_path が null は対象外
        ServiceInvoice::create([
            'facility_id' => $this->facility->id,
            'resident_id' => $this->resident->id,
            'billing_year_month' => '2026-03',
            'service_type' => 'welfare_equipment',
            'service_type_label' => '福祉用具貸与',
            'amount' => 1500,
            'status' => 'confirmed',
        ]);

        $mail = new InvoiceMail($this->invoice, 'https://example.com/invoice.pdf', includeCareServices: true);
        $attachments = $mail->attachments();

        $this->assertCount(3, $attachments);
        $this->assertSame('請求書_2026-03_101号室_佐藤太郎.pdf', $attachments[0]->as);
        $this->assertSame('訪問介護_請求書_2026-03_101号室_佐藤太郎.pdf', $attachments[1]->as);
        $this->assertSame('通所介護_請求書_2026-03_101号室_佐藤太郎.pdf', $attachments[2]->as);

        @unlink($careDir.'/visiting_care_2026-03.pdf');
        @unlink($careDir.'/day_care_2026-03.pdf');
    }

    public function test_attachments_skips_care_invoice_with_missing_file(): void
    {
        Http::fake([
            'https://example.com/invoice.pdf' => Http::response('%PDF-1.4 fake invoice', 200),
        ]);

        ServiceInvoice::create([
            'facility_id' => $this->facility->id,
            'resident_id' => $this->resident->id,
            'billing_year_month' => '2026-03',
            'service_type' => 'home_nursing',
            'service_type_label' => '訪問看護',
            'amount' => 12000,
            'pdf_path' => 'care/missing.pdf',
            'status' => 'confirmed',
        ]);

        $mail = new InvoiceMail($this->invoice, 'https://example.com/invoice.pdf', includeCareServices: true);

        // 住居費PDFのみ（介護PDFはディスク上に存在しないためスキップ）
        $this->assertCount(1, $mail->attachments());
    }

    public function test_attachments_no_care_services_when_disabled(): void
    {
        Http::fake([
            'https://example.com/invoice.pdf' => Http::response('%PDF-1.4 fake invoice', 200),
        ]);

        ServiceInvoice::create([
            'facility_id' => $this->facility->id,
            'resident_id' => $this->resident->id,
            'billing_year_month' => '2026-03',
            'service_type' => 'visiting_care',
            'service_type_label' => '訪問介護',
            'amount' => 30000,
            'pdf_path' => 'care/visiting_care_2026-03.pdf',
            'status' => 'confirmed',
        ]);
        $mail = new InvoiceMail($this->invoice, 'https://example.com/invoice.pdf', includeCareServices: false);

        $this->assertCount(1, $mail->attachments());
    }
}
