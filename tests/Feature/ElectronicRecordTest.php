<?php

namespace Tests\Feature;

use App\Models\ElectronicRecord;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\Denchoho\ElectronicRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ElectronicRecordTest extends TestCase
{
    use RefreshDatabase;

    private string $tempPdfPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempPdfPath = storage_path('app/test_invoice.pdf');
        File::put($this->tempPdfPath, '%PDF-1.4 sample pdf content for denchoho test');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempPdfPath)) {
            File::delete($this->tempPdfPath);
        }
        parent::tearDown();
    }

    public function test_record_invoice_pdf_creates_electronic_record_with_sha256_hash(): void
    {
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'name' => '田中電帳太郎',
        ]);

        $invoice = MonthlyInvoice::factory()->create([
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'rent_subtotal' => 80000,
            'management_fee_subtotal' => 40000,
            'service_subtotal' => 0,
            'tax_amount' => 12000,
        ]);

        $service = new ElectronicRecordService;
        $record = $service->recordInvoicePdf($invoice, $this->tempPdfPath);

        $this->assertDatabaseHas('electronic_records', [
            'id' => $record->id,
            'partner_name' => '田中電帳太郎',
            'amount' => (int) $invoice->fresh()->total_with_tax,
            'document_type' => 'invoice',
        ]);

        // SHA-256 ハッシュが正しいこと
        $expectedHash = hash_file('sha256', $this->tempPdfPath);
        $this->assertEquals($expectedHash, $record->sha256_hash);

        // 改ざん検知テスト: 改ざんなし
        $this->assertTrue($record->verifyIntegrity());

        // 改ざん発生（1バイト追記）
        File::append($this->tempPdfPath, 'TAMPERED');
        $this->assertFalse($record->verifyIntegrity());
    }

    public function test_multi_condition_search_satisfies_denchoho_requirements(): void
    {
        $facility = Facility::factory()->create();

        ElectronicRecord::create([
            'facility_id' => $facility->id,
            'document_type' => 'invoice',
            'transaction_date' => '2026-10-10',
            'amount' => 50000,
            'partner_name' => '佐藤花子',
            'file_path' => '/tmp/dummy1.pdf',
            'file_name' => 'dummy1.pdf',
            'sha256_hash' => str_repeat('a', 64),
            'file_size' => 1000,
        ]);

        ElectronicRecord::create([
            'facility_id' => $facility->id,
            'document_type' => 'invoice',
            'transaction_date' => '2026-10-15',
            'amount' => 150000,
            'partner_name' => '鈴木一郎',
            'file_path' => '/tmp/dummy2.pdf',
            'file_name' => 'dummy2.pdf',
            'sha256_hash' => str_repeat('b', 64),
            'file_size' => 2000,
        ]);

        $service = new ElectronicRecordService;

        // 範囲検索（日付: 10/11〜10/20、金額: 100,000〜200,000）
        $results = $service->search(
            $facility->id,
            startDate: '2026-10-11',
            endDate: '2026-10-20',
            minAmount: 100000,
            maxAmount: 200000,
        );

        $this->assertCount(1, $results);
        $this->assertEquals('鈴木一郎', $results[0]->partner_name);
    }
}
