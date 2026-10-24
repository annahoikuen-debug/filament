<?php

namespace Tests\Feature;

use App\Enums\ServiceInvoiceStatus;
use App\Models\Facility;
use App\Models\Resident;
use App\Services\CareSoftware\CareCsvParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareSoftwareIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_care_csv_parser_parses_and_imports_records(): void
    {
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'name' => '介護太郎',
        ]);

        $csv = "被保険者番号,氏名,サービス種別,請求年月,自己負担額,保険請求額\n"
             ."H123456789,介護太郎,訪問介護,2026-09,8500,76500\n"
             ."H999999999,未登録花子,通所介護,2026-09,12000,108000\n";

        $service = new CareCsvParserService;
        $result = $service->parse($csv, $facility);

        // 1件パース成功、1件エラー（未登録入居者）
        $this->assertCount(1, $result['parsed']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('未登録花子', $result['errors'][0]);

        $parsed = $result['parsed'][0];
        $this->assertEquals($resident->id, $parsed['resident_id']);
        $this->assertEquals(8500, $parsed['amount']);
        $this->assertEquals('2026-09', $parsed['billing_year_month']);

        // DB へのインポートテスト
        $importedCount = $service->import($result['parsed']);
        $this->assertEquals(1, $importedCount);

        $this->assertDatabaseHas('service_invoices', [
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-09',
            'amount' => 8500,
            'status' => ServiceInvoiceStatus::Confirmed->value,
        ]);
    }
}
