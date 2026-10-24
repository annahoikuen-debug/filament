<?php

namespace Tests\Feature\Security;

use App\Models\ApiToken;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\ServiceInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileApiControllerAuthTest extends TestCase
{
    use RefreshDatabase;

    protected Facility $facility;

    protected Facility $otherFacility;

    protected Resident $resident;

    protected ChargeItem $chargeItem;

    protected string $validToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facility = Facility::factory()->create(['is_active' => true]);
        $this->otherFacility = Facility::factory()->create(['is_active' => true]);

        $this->resident = Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'status' => 'active',
        ]);

        $this->chargeItem = ChargeItem::factory()->create([
            'facility_id' => $this->facility->id,
            'is_active' => true,
        ]);

        [$this->validToken] = ApiToken::issue($this->facility->id, 'mobile-test', ['mobile.read', 'mobile.write']);
    }

    /** @test */
    public function bearer_tokenなしで_get_residentsすると401を返す()
    {
        $response = $this->getJson('/api/mobile/residents', [
            'facility_id' => $this->facility->id,
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function 無効なトークンで_get_residentsすると401を返す()
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer invalid_token'])
            ->getJson('/api/mobile/residents', ['facility_id' => $this->facility->id]);

        $response->assertStatus(401);
    }

    /** @test */
    public function read権限のあるトークンで_get_residentsすると入居者一覧を返す()
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->validToken}"])
            ->getJson('/api/mobile/residents?facility_id='.$this->facility->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'room_number', 'name', 'name_kana', 'display_name'],
                ],
            ])
            ->assertJsonFragment(['id' => $this->resident->id]);
    }

    /** @test */
    public function 他施設のidで_get_residentsすると403を返す()
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->validToken}"])
            ->getJson('/api/mobile/residents?facility_id='.$this->otherFacility->id);

        $response->assertStatus(403);
    }

    /** @test */
    public function bearer_tokenなしで_get_charge_itemsすると401を返す()
    {
        $response = $this->getJson('/api/mobile/charge-items?facility_id='.$this->facility->id);
        $response->assertStatus(401);
    }

    /** @test */
    public function read権限のあるトークンで_get_charge_itemsすると品目一覧を返す()
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->validToken}"])
            ->getJson('/api/mobile/charge-items?facility_id='.$this->facility->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'display_name', 'default_price', 'tax_type', 'category'],
                ],
            ])
            ->assertJsonFragment(['id' => $this->chargeItem->id]);
    }

    /** @test */
    public function bearer_tokenなしで_post_daily_chargesすると401を返す()
    {
        $response = $this->postJson('/api/mobile/daily-charges', [
            'facility_id' => $this->facility->id,
            'charges' => [[
                'resident_id' => $this->resident->id,
                'charge_item_id' => $this->chargeItem->id,
                'date' => now()->format('Y-m-d'),
                'quantity' => 1,
            ]],
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function write権限のあるトークンで_post_daily_chargesすると記録を作成する()
    {
        $date = now()->format('Y-m-d');

        $response = $this->postJson('/api/mobile/daily-charges', [
            'facility_id' => $this->facility->id,
            'charges' => [[
                'resident_id' => $this->resident->id,
                'charge_item_id' => $this->chargeItem->id,
                'date' => $date,
                'quantity' => 3,
                'note' => 'テスト備考',
            ]],
        ], [
            'Authorization' => "Bearer {$this->validToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('daily_charges', [
            'resident_id' => $this->resident->id,
            'charge_item_id' => $this->chargeItem->id,
            'date' => $date.' 00:00:00',
            'quantity' => 3,
            'note' => 'テスト備考',
        ]);
    }

    /** @test */
    public function 同一入居者・品目・日付で更新される()
    {
        $date = now()->format('Y-m-d');

        DailyCharge::factory()->create([
            'facility_id' => $this->facility->id,
            'resident_id' => $this->resident->id,
            'charge_item_id' => $this->chargeItem->id,
            'date' => $date,
            'quantity' => 1,
        ]);

        $response = $this->postJson('/api/mobile/daily-charges', [
            'facility_id' => $this->facility->id,
            'charges' => [[
                'resident_id' => $this->resident->id,
                'charge_item_id' => $this->chargeItem->id,
                'date' => $date,
                'quantity' => 5,
            ]],
        ], [
            'Authorization' => "Bearer {$this->validToken}",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.updated', 1);

        $this->assertDatabaseHas('daily_charges', [
            'resident_id' => $this->resident->id,
            'charge_item_id' => $this->chargeItem->id,
            'date' => $date.' 00:00:00',
            'quantity' => 5,
        ]);
    }

    /** @test */
    public function 在籍期間外の日付でエラーになる()
    {
        $resident = Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'status' => 'active',
            'move_in_date' => now()->addDays(10),
        ]);

        $response = $this->postJson('/api/mobile/daily-charges', [
            'facility_id' => $this->facility->id,
            'charges' => [[
                'resident_id' => $resident->id,
                'charge_item_id' => $this->chargeItem->id,
                'date' => now()->format('Y-m-d'),
                'quantity' => 1,
            ]],
        ], [
            'Authorization' => "Bearer {$this->validToken}",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.errors.0.error', '利用日が入居者の在籍期間外です');
    }

    /** @test */
    public function bearer_tokenなしで_post_service_invoicesすると401を返す()
    {
        $response = $this->postJson('/api/mobile/service-invoices', [
            'facility_id' => $this->facility->id,
            'invoices' => [[
                'resident_id' => $this->resident->id,
                'billing_year_month' => now()->format('Y-m'),
                'service_type' => 'visiting_care',
                'amount' => 50000,
                'tax_amount' => 5000,
                'tax_rate' => 10.00,
            ]],
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function write権限のあるトークンで_post_service_invoicesすると請求を作成する()
    {
        $yearMonth = now()->format('Y-m');

        $response = $this->postJson('/api/mobile/service-invoices', [
            'facility_id' => $this->facility->id,
            'invoices' => [[
                'resident_id' => $this->resident->id,
                'billing_year_month' => $yearMonth,
                'service_type' => 'visiting_care',
                'amount' => 50000,
                'tax_amount' => 5000,
                'tax_rate' => 10.00,
                'status' => 'confirmed',
                'notes' => 'テスト備考',
            ]],
        ], [
            'Authorization' => "Bearer {$this->validToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('service_invoices', [
            'resident_id' => $this->resident->id,
            'billing_year_month' => $yearMonth,
            'service_type' => 'visiting_care',
            'amount' => 50000,
            'tax_amount' => 5000,
            'status' => 'confirmed',
            'notes' => 'テスト備考',
        ]);
    }

    /** @test */
    public function pdf_base64でアップロードすると保存される()
    {
        Storage::fake('local');

        $pdfContent = '%PDF-1.4 test pdf content';
        $pdfBase64 = base64_encode($pdfContent);

        $response = $this->postJson('/api/mobile/service-invoices', [
            'facility_id' => $this->facility->id,
            'invoices' => [[
                'resident_id' => $this->resident->id,
                'billing_year_month' => now()->format('Y-m'),
                'service_type' => 'visiting_care',
                'amount' => 50000,
                'tax_amount' => 5000,
                'tax_rate' => 10.00,
                'pdf_base64' => $pdfBase64,
                'pdf_filename' => 'test_invoice.pdf',
            ]],
        ], [
            'Authorization' => "Bearer {$this->validToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $invoice = ServiceInvoice::first();
        $this->assertNotNull($invoice->pdf_path);
        $this->assertEquals('test_invoice.pdf', $invoice->pdf_original_name);
        Storage::disk('local')->assertExists($invoice->pdf_path);
    }

    /** @test */
    public function read権限のみのトークンで_post_daily_chargesすると401を返す()
    {
        [$readOnlyToken] = ApiToken::issue($this->facility->id, 'read-only', ['mobile.read']);

        $response = $this->postJson('/api/mobile/daily-charges', [
            'facility_id' => $this->facility->id,
            'charges' => [[
                'resident_id' => $this->resident->id,
                'charge_item_id' => $this->chargeItem->id,
                'date' => now()->format('Y-m-d'),
                'quantity' => 1,
            ]],
        ], [
            'Authorization' => "Bearer {$readOnlyToken}",
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function read権限のみのトークンで_post_service_invoicesすると401を返す()
    {
        [$readOnlyToken] = ApiToken::issue($this->facility->id, 'read-only', ['mobile.read']);

        $response = $this->postJson('/api/mobile/service-invoices', [
            'facility_id' => $this->facility->id,
            'invoices' => [[
                'resident_id' => $this->resident->id,
                'billing_year_month' => now()->format('Y-m'),
                'service_type' => 'visiting_care',
                'amount' => 50000,
                'tax_amount' => 5000,
                'tax_rate' => 10.00,
            ]],
        ], [
            'Authorization' => "Bearer {$readOnlyToken}",
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function get_daily_charges_listで記録済み一覧を取得できる()
    {
        $yearMonth = now()->format('Y-m');
        DailyCharge::factory()->create([
            'facility_id' => $this->facility->id,
            'resident_id' => $this->resident->id,
            'charge_item_id' => $this->chargeItem->id,
            'date' => now()->format('Y-m-d'),
            'quantity' => 2,
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->validToken}"])
            ->getJson('/api/mobile/daily-charges?facility_id='.$this->facility->id.'&billing_year_month='.$yearMonth);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'date', 'resident', 'charge_item', 'unit_price', 'quantity', 'subtotal', 'note'],
                ],
            ]);
    }

    /** @test */
    public function get_service_invoices_listで請求一覧を取得できる()
    {
        $yearMonth = now()->format('Y-m');
        ServiceInvoice::factory()->create([
            'facility_id' => $this->facility->id,
            'resident_id' => $this->resident->id,
            'billing_year_month' => $yearMonth,
            'service_type' => 'visiting_care',
            'amount' => 50000,
            'tax_amount' => 5000,
            'tax_rate' => 10.00,
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->validToken}"])
            ->getJson('/api/mobile/service-invoices?facility_id='.$this->facility->id.'&billing_year_month='.$yearMonth);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'billing_year_month', 'resident', 'service_type', 'amount', 'tax_amount', 'status', 'has_pdf'],
                ],
                'meta',
            ]);
    }
}
