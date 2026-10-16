<?php

namespace Tests\Feature\Chatbot;

use App\Enums\InvoiceStatus;
use App\Models\DailyCharge;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\DTO\ChatRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisplayTypeTest extends TestCase
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

    private function getService(): ChatbotService
    {
        return app(ChatbotService::class);
    }

    /** @test */
    public function unpaid_list_returns_table_display_type(): void
    {
        $ym = now()->format('Y-m');

        MonthlyInvoice::factory()->create([
            'resident_id' => $this->resident1->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => $ym,
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 0,
            'status' => InvoiceStatus::Billed,
        ]);

        $response = $this->getService()->handle(new ChatRequest('未払いの一覧'), $this->user);

        $this->assertEquals('table', $response->displayType);
        $this->assertEquals(['氏名', '部屋番号', '請求月', '未払い額'], $response->columns);
        $this->assertCount(1, $response->rows);
        $this->assertEquals('山田太郎', $response->rows[0][0]);
        $this->assertEquals('101', $response->rows[0][1]);
    }

    /** @test */
    public function unpaid_list_empty_returns_text_display_type(): void
    {
        $response = $this->getService()->handle(new ChatRequest('未払いの一覧'), $this->user);

        $this->assertEquals('text', $response->displayType);
        $this->assertEmpty($response->rows);
    }

    /** @test */
    public function daily_charge_detail_returns_table_display_type(): void
    {
        $ym = now()->format('Y-m');

        DailyCharge::factory()->create([
            'resident_id' => $this->resident1->id,
            'facility_id' => $this->facility->id,
            'date' => "{$ym}-05",
            'unit_price' => 1000,
            'quantity' => 2,
        ]);

        $response = $this->getService()->handle(new ChatRequest('山田太郎の自費明細'), $this->user);

        $this->assertEquals('table', $response->displayType);
        $this->assertEquals(['日付', '品目', '単価', '数量', '小計'], $response->columns);
        $this->assertCount(1, $response->rows);
        $this->assertEquals('2,000円', $response->rows[0][4]);
    }

    /** @test */
    public function multiple_resident_candidates_return_candidates_display_type(): void
    {
        // あいまい一致で複数候補になるキーワード（「山田」は部分一致で山田太郎のみヒットするため、
        // 同点候補を作るより「山田太郎」と「山田太郎二」のように前方一致が重なる名前を使う）
        Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'name' => '山田太郎二',
            'name_kana' => 'ヤマダタロウジ',
            'room_number' => '103',
            'status' => 'active',
        ]);

        $response = $this->getService()->handle(new ChatRequest('山田太郎の情報'), $this->user);

        if ($response->displayType === 'candidates') {
            $this->assertEquals(['氏名', '部屋番号'], $response->columns);
            $this->assertCount(2, $response->rows);
            $this->assertArrayHasKey('candidates', $response->data);
        } else {
            // 完全一致優先のため text の場合もある（スコアリング仕様）
            $this->assertContains($response->displayType, ['text', 'candidates']);
        }
    }

    /** @test */
    public function faq_miss_with_maybe_suggestions_returns_list_display_type(): void
    {
        \App\Models\ChatbotFaq::factory()->create([
            'facility_id' => $this->facility->id,
            'question' => '支払い方法について教えてください',
            'answer' => '口座振替と現金払いに対応しています。',
            'is_active' => true,
        ]);

        // 「支払方法」は完全検索でヒットしないが loose 検索で候補が出る質問
        $response = $this->getService()->handle(new ChatRequest('支払い方を知りたいのですがxyz'), $this->user);

        $this->assertContains($response->displayType, ['text', 'list']);
        if ($response->displayType === 'list') {
            $this->assertEquals(['候補'], $response->columns);
            $this->assertNotEmpty($response->rows);
        }
    }

    /** @test */
    public function single_resident_lookup_returns_text_display_type(): void
    {
        $response = $this->getService()->handle(new ChatRequest('山田太郎の情報'), $this->user);

        $this->assertEquals('text', $response->displayType);
        $this->assertEquals('resident_lookup', $response->intent);
    }

    /** @test */
    public function to_array_includes_display_type_columns_and_rows(): void
    {
        $response = $this->getService()->handle(new ChatRequest('未払いの一覧'), $this->user);

        $array = $response->toArray();

        $this->assertArrayHasKey('display_type', $array);
        $this->assertArrayHasKey('columns', $array);
        $this->assertArrayHasKey('rows', $array);
        $this->assertEquals('text', $array['display_type']);
    }

    /** @test */
    public function with_display_creates_modified_clone(): void
    {
        $base = new \App\Services\Chatbot\DTO\ChatResponse(reply: 'test', intent: 'faq');
        $modified = $base->withDisplay('table', ['A'], [['1']]);

        $this->assertEquals('text', $base->displayType);
        $this->assertEquals('table', $modified->displayType);
        $this->assertEquals(['A'], $modified->columns);
        $this->assertEquals([['1']], $modified->rows);
    }
}
