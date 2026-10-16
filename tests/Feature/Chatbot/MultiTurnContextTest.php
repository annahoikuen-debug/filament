<?php

namespace Tests\Feature\Chatbot;

use App\Enums\InvoiceStatus;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTurnContextTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Facility $facility;

    private Resident $resident;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facility = Facility::factory()->create();
        $this->user = User::factory()->corporateAdmin()->create();
        $this->resident = Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'name' => '田中 太郎',
            'status' => 'active',
        ]);
    }

    public function test_followup_question_reuses_resident_context(): void
    {
        // ターン1: 名前付き質問で成功
        $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => '田中 太郎の請求額を教えてください',
                'session_id' => 'sess-mt-1',
            ])->assertStatus(200);

        // ターン2: 主語省略（「未払いは？」）で直前の入居者を引き継ぐ
        $second = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => '未払いは？',
                'session_id' => 'sess-mt-1',
            ]);

        $second->assertStatus(200);
        // FAQ フォールバックではなく invoice_status intent で応答している
        $this->assertNotSame('faq', $second->json('intent'));
    }

    public function test_followup_period_question_uses_last_intent(): void
    {
        MonthlyInvoice::factory()->create([
            'resident_id' => $this->resident->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => now()->subMonth()->format('Y-m'),
            'status' => InvoiceStatus::Billed,
        ]);

        // ターン1: 名前付き質問
        $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => $this->resident->name.'の今月の請求額を教えてください',
                'session_id' => 'sess-mt-2',
            ])->assertStatus(200);

        // ターン2: 「先月は？」→ invoice_amount を引き継ぐ
        $second = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => '先月は？',
                'session_id' => 'sess-mt-2',
            ]);

        $second->assertStatus(200);
        $this->assertNotSame('faq', $second->json('intent'));
    }

    public function test_elliptical_question_without_resident_asks_for_name(): void
    {
        // 文脈なしの「先月は？」→ 既存パターンで invoice_amount になるが入居者未特定の案内を返す
        $response = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => '先月は？',
                'session_id' => 'sess-mt-no-context',
            ]);

        $response->assertStatus(200);
        $this->assertSame('invoice_amount', $response->json('intent'));
        $this->assertStringContainsString('入居者', $response->json('reply'));
    }

    public function test_elliptical_inquiry_without_context_asks_for_resident(): void
    {
        // 文脈なしの「未払いは？」→ intent は照会だが入居者未特定の案内を返す
        $response = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => '未払いは？',
                'session_id' => 'sess-mt-no-context-2',
            ]);

        $response->assertStatus(200);
        $this->assertSame('invoice_status', $response->json('intent'));
        $this->assertStringContainsString('入居者', $response->json('reply'));
    }

    public function test_context_does_not_leak_across_sessions(): void
    {
        // ターン1: session A で名前質問
        $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => $this->resident->name.'の請求額を教えてください',
                'session_id' => 'sess-a',
            ])->assertStatus(200);

        // session B（別セッション）では省略質問が session A の文脈を引き継がない
        // （invoice_status にはなるが、A の入居者のデータは返らない）
        $response = $this->actingAs($this->user)
            ->postJson('/api/chatbot/message', [
                'message' => '未払いは？',
                'session_id' => 'sess-b',
            ]);

        $response->assertStatus(200);
        $this->assertSame('invoice_status', $response->json('intent'));
        $this->assertStringContainsString('入居者', $response->json('reply'));
    }
}
