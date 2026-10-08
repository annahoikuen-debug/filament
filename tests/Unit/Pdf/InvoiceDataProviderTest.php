<?php

namespace Tests\Unit\Pdf;

use App\Services\Pdf\DataProviders\InvoiceDataProvider;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\DailyCharge;
use App\Models\ChargeItem;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvoiceDataProviderTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceDataProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new InvoiceDataProvider();
        
        // Run tax columns migration
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_07_000001_add_tax_columns_to_monthly_invoices.php']);
    }

    public function test_get_invoice_data()
    {
        $resident = Resident::factory()->create([
            'name' => 'テスト太郎',
            'room_number' => '101',
        ]);

        $invoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-10',
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 10000,
            'taxable_amount' => 30000,
            'tax_rate' => 10,
            'tax_amount' => 3000,
        ]);

        $data = $this->provider->getInvoiceData($invoice);

        $this->assertInstanceOf(\App\DTOs\Pdf\InvoicePdfData::class, $data);
        $this->assertEquals('テスト太郎', $data->resident->name);
        $this->assertEquals('101', $data->resident->roomNumber);
        $this->assertEquals('2026-10', $data->billingYearMonth);
        $this->assertStringStartsWith('INV-202610-', $data->invoiceNumber);
        $this->assertEquals(50000, $data->rentSubtotal);
        $this->assertEquals(20000, $data->managementFeeSubtotal);
        $this->assertEquals(10000, $data->serviceSubtotal);
        $this->assertEquals(50000, $data->taxInfo->nonTaxable); // rent_subtotal
        $this->assertEquals(30000, $data->taxInfo->taxable);   // taxable_amount accessor
    }

    public function test_get_receipt_data()
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
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 10000,
            'taxable_amount' => 30000,
            'tax_rate' => 10,
            'tax_amount' => 3000,
        ]);

        $data = $this->provider->getReceiptData($invoice);

        $this->assertInstanceOf(\App\DTOs\Pdf\ReceiptPdfData::class, $data);
        $this->assertEquals('テスト太郎', $data->resident->name);
        $this->assertEquals('101', $data->resident->roomNumber);
        $this->assertEquals('2026-10', $data->billingYearMonth);
        $this->assertEquals('REC-202610-001', $data->receiptNumber);
        $this->assertEquals('2026年10月15日', $data->receivedAt);
        $this->assertEquals(50000, $data->rentSubtotal);
    }

    public function test_get_monthly_invoices_data()
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

        // Different month - should not be included
        MonthlyInvoice::factory()->create([
            'resident_id' => $resident1->id,
            'billing_year_month' => '2026-09',
        ]);

        $data = $this->provider->getMonthlyInvoicesData('2026-10');

        $this->assertCount(2, $data);
        $this->assertEquals('入居者A', $data[0]->resident->name);
        $this->assertEquals('入居者B', $data[1]->resident->name);
    }

    public function test_get_monthly_invoices_data_with_facility_filter()
    {
        $facility1 = \App\Models\Facility::factory()->create(['id' => 1, 'name' => '施設A']);
        $facility2 = \App\Models\Facility::factory()->create(['id' => 2, 'name' => '施設B']);

        $resident1 = Resident::factory()->create(['facility_id' => 1]);
        $resident2 = Resident::factory()->create(['facility_id' => 2]);

        MonthlyInvoice::factory()->create(['resident_id' => $resident1->id, 'billing_year_month' => '2026-10']);
        MonthlyInvoice::factory()->create(['resident_id' => $resident2->id, 'billing_year_month' => '2026-10']);

        $data = $this->provider->getMonthlyInvoicesData('2026-10', 1);

        $this->assertCount(1, $data);
        $this->assertEquals($resident1->id, $data[0]->resident->id);
    }
}