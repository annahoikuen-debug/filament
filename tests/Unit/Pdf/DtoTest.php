<?php

namespace Tests\Unit\Pdf;

use App\DTOs\Pdf\DailyChargePdfData;
use App\DTOs\Pdf\FacilityPdfData;
use App\DTOs\Pdf\ResidentPdfData;
use App\DTOs\Pdf\TaxInfoPdfData;
use App\Enums\ResidentStatus;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DtoTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_pdf_data_from_model()
    {
        $resident = Resident::factory()->create([
            'name' => 'テスト太郎',
            'name_kana' => 'テストタロウ',
            'room_number' => '101',
            'status' => ResidentStatus::Active,
        ]);

        $dto = ResidentPdfData::fromModel($resident);

        $this->assertEquals($resident->id, $dto->id);
        $this->assertEquals('テスト太郎', $dto->name);
        $this->assertEquals('テストタロウ', $dto->nameKana);
        $this->assertEquals('101', $dto->roomNumber);
        $this->assertEquals('active', $dto->status);

        $array = $dto->toArray();
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('room_number', $array);
    }

    public function test_facility_pdf_data_from_config()
    {
        config(['facility' => [
            'id' => 1,
            'name' => 'テスト施設',
            'operator' => 'テスト運営',
            'postal_code' => '123-4567',
            'address' => '東京都テスト区',
            'phone' => '03-1234-5678',
            'fax' => '03-1234-5679',
            'invoice_registration_number' => 'T1234567890123',
            'bank' => [
                'name' => 'テスト銀行',
                'branch_name' => 'テスト支店',
                'account_type' => '普通',
                'account_number' => '1234567',
                'account_holder' => 'テスト施設',
            ],
            'billing' => [
                'direct_debit_day' => 27,
            ],
            'logo_path' => '/path/to/logo.png',
            'seal_path' => '/path/to/seal.png',
        ]]);

        $dto = FacilityPdfData::fromConfig();

        $this->assertEquals(1, $dto->id);
        $this->assertEquals('テスト施設', $dto->name);
        $this->assertEquals('テスト運営', $dto->operator);
        $this->assertEquals('123-4567', $dto->postalCode);
        $this->assertEquals('東京都テスト区', $dto->address);
        $this->assertEquals('03-1234-5678', $dto->phone);
        $this->assertEquals('03-1234-5679', $dto->fax);
        $this->assertEquals('T1234567890123', $dto->invoiceRegistrationNumber);
        $this->assertEquals('テスト銀行', $dto->bank['name']);
        $this->assertEquals(27, $dto->billing['direct_debit_day']);

        $array = $dto->toArray();
        $this->assertArrayHasKey('bank', $array);
        $this->assertArrayHasKey('billing', $array);
    }

    public function test_tax_info_pdf_data_from_invoice()
    {
        // Run migrations first
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_07_000001_add_tax_columns_to_monthly_invoices.php']);

        $invoice = MonthlyInvoice::factory()->create([
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 10000,
            'taxable_amount' => 30000,
            'tax_rate' => 10,
            'tax_amount' => 3000,
        ]);

        // Refresh to get accessors
        $invoice->refresh();

        $dto = TaxInfoPdfData::fromInvoice($invoice);

        $this->assertEquals(50000, $dto->nonTaxable); // rent_subtotal
        $this->assertEquals(30000, $dto->taxable);   // taxable_amount accessor
        $this->assertEquals(10, $dto->taxRate);
        $this->assertEquals(3000, $dto->taxAmount);
        $this->assertEquals(83000, $dto->totalWithTax); // 50000 + 30000 + 3000

        $array = $dto->toArray();
        $this->assertEquals(50000, $array['non_taxable']);
        $this->assertEquals(30000, $array['taxable']);
    }

    public function test_daily_charge_pdf_data_from_model()
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_08_090609_add_tax_type_to_charge_items_table.php']);

        $resident = Resident::factory()->create();
        $chargeItem = ChargeItem::factory()->create([
            'name' => '口腔ケア',
            'tax_type' => 'standard',
        ]);

        $charge = DailyCharge::factory()->create([
            'resident_id' => $resident->id,
            'charge_item_id' => $chargeItem->id,
            'date' => '2026-10-05',
            'unit_price' => 1000,
            'quantity' => 2,
            // subtotal is an accessor (unit_price * quantity)
            'note' => 'テスト備考',
        ]);

        $dto = DailyChargePdfData::fromModel($charge);

        $this->assertEquals($charge->id, $dto->id);
        $this->assertEquals('10/05', $dto->date);
        $this->assertEquals('口腔ケア', $dto->itemName);
        $this->assertEquals(1000, $dto->unitPrice);
        $this->assertEquals(2, $dto->quantity);
        $this->assertEquals(2000, $dto->subtotal);
        $this->assertEquals('テスト備考', $dto->note);
        $this->assertTrue($dto->isTaxable); // standard tax_type should be taxable

        $array = $dto->toArray();
        $this->assertEquals('10/05', $array['date']);
        $this->assertEquals('口腔ケア', $array['item_name']);
    }
}
