<?php

namespace Tests\Feature\Chatbot;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceInvoiceStatus;
use App\Enums\ServiceType;
use App\Models\DailyCharge;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\ServiceInvoice;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\DTO\ChatRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewIntentsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Facility $facility;

    private Resident $resident1;

    private Resident $resident2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facility = Facility::factory()->create(['name' => 'テスト施設']);

        $this->user = User::factory()->create([
            'facility_id' => $this->facility->id,
            'role' => 'facility_admin',
        ]);

        $this->resident1 = Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'name' => '山田太郎',
            'name_kana' => 'ヤマダタロウ',
            'room_number' => '101',
            'status' => 'active',
            'base_rent' => 50000,
            'base_management_fee' => 20000,
            'move_in_date' => '2024-01-01',
        ]);

        $this->resident2 = Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'name' => '佐藤花子',
            'name_kana' => 'サトウハナコ',
            'room_number' => '102',
            'status' => 'active',
            'base_rent' => 55000,
            'base_management_fee' => 22000,
            'move_in_date' => '2024-02-01',
        ]);
    }

    private function makeRequest(string $message, ?string $sessionId = null): ChatRequest
    {
        return new ChatRequest(
            message: $message,
            sessionId: $sessionId,
        );
    }

    private function getService(): ChatbotService
    {
        return app(ChatbotService::class);
    }

    /** @test */
    public function unpaid_list_intent_returns_unpaid_residents(): void
    {
        $ym = now()->format('Y-m');

        // 住民1: 未払い (Billed = 請求済で未入金)
        MonthlyInvoice::factory()->create([
            'resident_id' => $this->resident1->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => $ym,
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 5000,
            'total_amount' => 75000,
            'status' => InvoiceStatus::Billed,
        ]);

        // 住民2: 入金済み
        MonthlyInvoice::factory()->create([
            'resident_id' => $this->resident2->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => $ym,
            'rent_subtotal' => 55000,
            'management_fee_subtotal' => 22000,
            'service_subtotal' => 3000,
            'total_amount' => 80000,
            'status' => InvoiceStatus::Paid,
        ]);

        $response = $this->getService()->handle($this->makeRequest('未払いの一覧'), $this->user);

        $this->assertEquals('unpaid_list', $response->intent);
        $this->assertStringContainsString('山田太郎', $response->reply);
        $this->assertStringContainsString('未払い', $response->reply);
        $this->assertStringNotContainsString('佐藤花子', $response->reply); // 入金済みは含まれない
        $this->assertEquals(1, $response->data['count']);
        // 未払い額は税込（75000 + 消費税2500 = 77500）
        $this->assertEquals(77500, $response->data['total_amount']);
    }

    /** @test */
    public function unpaid_list_intent_returns_empty_when_no_unpaid(): void
    {
        $ym = now()->format('Y-m');

        MonthlyInvoice::factory()->create([
            'resident_id' => $this->resident1->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => $ym,
            'total_amount' => 75000,
            'status' => InvoiceStatus::Paid,
        ]);

        $response = $this->getService()->handle($this->makeRequest('未払い一覧'), $this->user);

        $this->assertEquals('unpaid_list', $response->intent);
        $this->assertStringContainsString('未払いの入居者はいません', $response->reply);
    }

    /** @test */
    public function unpaid_list_respects_facility_scope(): void
    {
        $otherFacility = Facility::factory()->create(['name' => '他施設']);
        $otherUser = User::factory()->create(['facility_id' => $otherFacility->id, 'role' => 'facility_admin']);
        $otherResident = Resident::factory()->create(['facility_id' => $otherFacility->id, 'name' => '他施設太郎']);

        $ym = now()->format('Y-m');
        MonthlyInvoice::factory()->create([
            'resident_id' => $otherResident->id,
            'facility_id' => $otherFacility->id,
            'billing_year_month' => $ym,
            'total_amount' => 100000,
            'status' => InvoiceStatus::Billed,
        ]);

        $response = $this->getService()->handle($this->makeRequest('未払い一覧'), $this->user);

        $this->assertEquals('unpaid_list', $response->intent);
        $this->assertStringNotContainsString('他施設太郎', $response->reply);
    }

    /** @test */
    public function billing_summary_intent_returns_aggregated_data(): void
    {
        $ym = now()->format('Y-m');

        // 未払い2件 (Billed)
        MonthlyInvoice::factory()->create([
            'resident_id' => $this->resident1->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => $ym,
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 25000,
            'service_subtotal' => 0,
            'status' => InvoiceStatus::Billed,
        ]);
        MonthlyInvoice::factory()->create([
            'resident_id' => $this->resident2->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => $ym,
            'rent_subtotal' => 55000,
            'management_fee_subtotal' => 25000,
            'service_subtotal' => 0,
            'status' => InvoiceStatus::Billed,
        ]);

        // 入金済み1件
        $resident3 = Resident::factory()->create(['facility_id' => $this->facility->id, 'name' => '田中一郎']);
        MonthlyInvoice::factory()->create([
            'resident_id' => $resident3->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => $ym,
            'rent_subtotal' => 40000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 0,
            'status' => InvoiceStatus::Paid,
        ]);

        $response = $this->getService()->handle($this->makeRequest('今月の請求集計'), $this->user);

        $this->assertEquals('billing_summary', $response->intent);
        // saving イベントで total_amount = rent + management + service に再計算される
        // 集計は税込（total_with_tax）: 77500 + 82500 + 62000
        $this->assertEquals(77500 + 82500 + 62000, $response->data['total_billed']);
        $this->assertEquals(77500 + 82500, $response->data['total_unpaid']);
        $this->assertEquals(2, $response->data['unpaid_count']);
        $this->assertEquals(1, $response->data['paid_count']);
        $this->assertEquals(3, $response->data['total_count']);
        $this->assertStringContainsString('総請求額', $response->reply);
        $this->assertStringContainsString('未回収', $response->reply);
    }

    /** @test */
    public function billing_summary_with_specific_month(): void
    {
        $lastMonth = now()->subMonth()->format('Y-m');

        MonthlyInvoice::factory()->create([
            'resident_id' => $this->resident1->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => $lastMonth,
            'total_amount' => 70000,
            'status' => InvoiceStatus::Paid,
        ]);

        $response = $this->getService()->handle($this->makeRequest('先月の請求集計'), $this->user);

        $this->assertEquals('billing_summary', $response->intent);
        $this->assertEquals($lastMonth, $response->data['year_month']);
    }

    /** @test */
    public function invoice_pdf_intent_returns_pdf_link(): void
    {
        $ym = now()->format('Y-m');

        $invoice = MonthlyInvoice::factory()->create([
            'resident_id' => $this->resident1->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => $ym,
            'total_amount' => 75000,
            'status' => InvoiceStatus::Billed,
        ]);

        $response = $this->getService()->handle($this->makeRequest('山田太郎の請求書PDF'), $this->user);

        $this->assertEquals('invoice_pdf', $response->intent);
        $this->assertStringContainsString('請求書PDF', $response->reply);
        $this->assertEquals($this->resident1->id, $response->data['resident_id']);
        $this->assertArrayHasKey('pdf_url', $response->data);
        $this->assertNotEmpty($response->actionLinks);
        // PDFダウンロードリンクが含まれること
        $pdfLink = collect($response->actionLinks)->first(fn (array $l) => str_contains($l['label'], 'PDF'));
        $this->assertNotNull($pdfLink);
    }

    /** @test */
    public function invoice_pdf_intent_requires_resident(): void
    {
        $response = $this->getService()->handle($this->makeRequest('請求書PDF'), $this->user);

        $this->assertEquals('invoice_pdf', $response->intent);
        $this->assertStringContainsString('入居者を特定できません', $response->reply);
        $this->assertNotEmpty($response->quickReplies);
    }

    /** @test */
    public function daily_charge_detail_intent_returns_itemized_list(): void
    {
        $ym = now()->format('Y-m');

        DailyCharge::factory()->create([
            'resident_id' => $this->resident1->id,
            'facility_id' => $this->facility->id,
            'date' => "{$ym}-05",
            'unit_price' => 1000,
            'quantity' => 2,
        ]);
        DailyCharge::factory()->create([
            'resident_id' => $this->resident1->id,
            'facility_id' => $this->facility->id,
            'date' => "{$ym}-10",
            'unit_price' => 500,
            'quantity' => 1,
        ]);

        $response = $this->getService()->handle($this->makeRequest('山田太郎の自費明細'), $this->user);

        $this->assertEquals('daily_charge_detail', $response->intent);
        $this->assertStringContainsString('自費利用料明細', $response->reply);
        $this->assertEquals(2500, $response->data['total']);
        $this->assertCount(2, $response->data['details']);
    }

    /** @test */
    public function daily_charge_detail_intent_empty_month(): void
    {
        $response = $this->getService()->handle($this->makeRequest('山田太郎の自費明細'), $this->user);

        $this->assertEquals('daily_charge_detail', $response->intent);
        $this->assertStringContainsString('明細はありません', $response->reply);
    }

    /** @test */
    public function service_invoice_intent_returns_care_insurance_billing(): void
    {
        $ym = now()->format('Y-m');

        ServiceInvoice::factory()->create([
            'resident_id' => $this->resident1->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => $ym,
            'service_type' => ServiceType::VisitingCare,
            'service_type_label' => '訪問介護',
            'amount' => 100000,
            'tax_amount' => 10000,
            'status' => ServiceInvoiceStatus::Confirmed,
        ]);
        ServiceInvoice::factory()->create([
            'resident_id' => $this->resident1->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => now()->subMonth()->format('Y-m'),
            'service_type' => ServiceType::DayCare,
            'service_type_label' => '通所介護',
            'amount' => 80000,
            'tax_amount' => 8000,
            'status' => ServiceInvoiceStatus::Sent,
        ]);

        $response = $this->getService()->handle($this->makeRequest('山田太郎の介護請求'), $this->user);

        $this->assertEquals('service_invoice', $response->intent);
        $this->assertStringContainsString('介護保険請求一覧', $response->reply);
        $this->assertStringContainsString('訪問介護', $response->reply);
        $this->assertStringContainsString('通所介護', $response->reply);
        $this->assertEquals(180000, $response->data['total_amount']);
        $this->assertCount(2, $response->data['invoices']);
    }

    /** @test */
    public function service_invoice_intent_with_month_filter(): void
    {
        $ym = now()->format('Y-m');

        ServiceInvoice::factory()->create([
            'resident_id' => $this->resident1->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => $ym,
            'service_type' => ServiceType::VisitingCare,
            'amount' => 100000,
        ]);
        ServiceInvoice::factory()->create([
            'resident_id' => $this->resident1->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => now()->subMonth()->format('Y-m'),
            'service_type' => ServiceType::DayCare,
            'amount' => 80000,
        ]);

        // MonthParser は「今月」を null（最新月扱い）にするため、明示的な月指定で絞り込む
        $response = $this->getService()->handle($this->makeRequest('山田太郎の10月の介護請求'), $this->user);

        $this->assertEquals('service_invoice', $response->intent);
        $this->assertCount(1, $response->data['invoices']);
        $this->assertEquals($ym, $response->data['invoices'][0]['billing_year_month']);
    }

    /** @test */
    public function service_invoice_intent_requires_resident(): void
    {
        $response = $this->getService()->handle($this->makeRequest('介護保険請求'), $this->user);

        $this->assertEquals('service_invoice', $response->intent);
        $this->assertStringContainsString('入居者を特定できません', $response->reply);
    }

    /** @test */
    public function all_new_intents_support_multi_turn_context(): void
    {
        $ym = now()->format('Y-m');

        MonthlyInvoice::factory()->create([
            'resident_id' => $this->resident1->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => $ym,
            'total_amount' => 75000,
            'status' => InvoiceStatus::Billed,
        ]);

        $sessionId = 'test-session-new-intents';

        // 最初に入居者を特定
        $response1 = $this->getService()->handle($this->makeRequest('山田太郎の情報'), $this->user, $sessionId);
        $this->assertEquals('resident_lookup', $response1->intent);

        // 続けて未払い一覧（文脈なしで全体を返す）
        $response2 = $this->getService()->handle($this->makeRequest('未払い一覧'), $this->user, $sessionId);
        $this->assertEquals('unpaid_list', $response2->intent);

        // 請求集計
        $response3 = $this->getService()->handle($this->makeRequest('今月の集計'), $this->user, $sessionId);
        $this->assertEquals('billing_summary', $response3->intent);
    }
}
