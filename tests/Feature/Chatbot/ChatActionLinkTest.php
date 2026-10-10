<?php

use App\Enums\InvoiceStatus;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Services\Chatbot\ChatActionLinkGenerator;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\DTO\ChatRequest;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-15');
    $this->facility = Facility::factory()->create();
    $this->user = User::factory()->facilityAdmin()->create([
        'facility_id' => $this->facility->id,
    ]);

    $this->resident = Resident::factory()->create([
        'facility_id' => $this->facility->id,
        'name' => '高橋一郎',
        'room_number' => '301',
    ]);

    $this->invoice = MonthlyInvoice::factory()->create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 80000,
        'status' => InvoiceStatus::Billed,
    ]);

    $this->service = app(ChatbotService::class);
});

test('ChatActionLinkGenerator が正しくURLを生成すること', function () {
    $generator = new ChatActionLinkGenerator;

    $invoiceLink = $generator->invoiceEdit(123);
    expect($invoiceLink['url'])->toContain('/admin/monthly-invoices/123/edit')
        ->and($invoiceLink['label'])->toContain('請求書編集画面');

    $residentLink = $generator->residentEdit(456);
    expect($residentLink['url'])->toContain('/admin/residents/456/edit')
        ->and($residentLink['label'])->toContain('入居者台帳');
});

test('入居者照会時に actionLinks に入居者台帳リンクが含まれること', function () {
    $response = $this->service->handle(
        new ChatRequest('高橋一郎の情報'),
        $this->user
    );

    expect($response->actionLinks)->not()->toBeEmpty()
        ->and($response->actionLinks[0]['url'])->toContain("/admin/residents/{$this->resident->id}/edit");
});

test('請求照会時に actionLinks に請求書編集と入居者台帳リンクが含まれること', function () {
    $response = $this->service->handle(
        new ChatRequest('高橋一郎の今月の請求額'),
        $this->user
    );

    expect($response->actionLinks)->toHaveCount(2)
        ->and($response->actionLinks[0]['url'])->toContain("/admin/monthly-invoices/{$this->invoice->id}/edit")
        ->and($response->actionLinks[1]['url'])->toContain("/admin/residents/{$this->resident->id}/edit");
});

test('API経由でも action_links がJSONに含まれ、既存構造を破壊しないこと', function () {
    $this->actingAs($this->user)
        ->postJson('/api/chatbot/message', [
            'message' => '高橋一郎の請求額',
        ])
        ->assertOk()
        ->assertJsonStructure([
            'reply',
            'intent',
            'data',
            'sources',
            'quick_replies',
            'quick_reply_categories',
            'chat_log_id',
            'action_links',
        ])
        ->assertJsonPath('action_links.0.url', url("/admin/monthly-invoices/{$this->invoice->id}/edit"));
});
