<?php

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\DTO\ChatRequest;

beforeEach(function () {
    $this->facility1 = Facility::factory()->create(['name' => '施設A']);
    $this->facility2 = Facility::factory()->create(['name' => '施設B']);

    $this->corporateAdmin = User::factory()->corporateAdmin()->create();
    $this->facilityAdmin1 = User::factory()->facilityAdmin()->create(['facility_id' => $this->facility1->id]);
    $this->facilityAdmin2 = User::factory()->facilityAdmin()->create(['facility_id' => $this->facility2->id]);

    $this->service = app(ChatbotService::class);

    $this->resident1 = Resident::factory()->create([
        'facility_id' => $this->facility1->id,
        'room_number' => '101',
        'name' => '施設A入居者',
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
    ]);

    $this->resident2 = Resident::factory()->create([
        'facility_id' => $this->facility2->id,
        'room_number' => '201',
        'name' => '施設B入居者',
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
        'base_rent' => 60000,
        'base_management_fee' => 25000,
    ]);

    MonthlyInvoice::factory()->create([
        'facility_id' => $this->facility1->id,
        'resident_id' => $this->resident1->id,
        'billing_year_month' => '2026-09',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Billed,
    ]);

    MonthlyInvoice::factory()->create([
        'facility_id' => $this->facility2->id,
        'resident_id' => $this->resident2->id,
        'billing_year_month' => '2026-09',
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 25000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Billed,
    ]);
});

test('facility_admin は自施設の入居者情報を取得できること', function () {
    $response = $this->service->handle(
        new ChatRequest('施設A入居者の情報を教えてください'),
        $this->facilityAdmin1
    );

    expect($response->intent)->toBe('resident_lookup')
        ->and($response->reply)->toContain('施設A入居者');
});

test('facility_admin が他施設の入居者名を質問しても情報が返らないこと', function () {
    $response = $this->service->handle(
        new ChatRequest('施設B入居者の情報を教えてください'),
        $this->facilityAdmin1
    );

    expect($response->reply)->toContain('見つかりません');
});

test('facility_admin が他施設の請求額を質問しても金額が漏洩しないこと', function () {
    $response = $this->service->handle(
        new ChatRequest('施設B入居者の今月の請求額はいくらですか'),
        $this->facilityAdmin1
    );

    expect($response->reply)->not->toContain('60,000円')
        ->and($response->reply)->not->toContain('85,000円');
});

test('facility_admin のログに facility_id が正しく記録されること', function () {
    $this->service->handle(
        new ChatRequest('施設A入居者の情報を教えてください'),
        $this->facilityAdmin1
    );

    $log = \App\Models\ChatLog::latest()->first();

    expect($log->facility_id)->toBe($this->facility1->id)
        ->and($log->user_id)->toBe($this->facilityAdmin1->id);
});

test('corporate_admin は全施設を検索できること', function () {
    $response = $this->service->handle(
        new ChatRequest('施設B入居者の情報を教えてください'),
        $this->corporateAdmin
    );

    expect($response->reply)->toContain('施設B入居者');
});

test('corporate_admin のログの facility_id は null になること', function () {
    $this->service->handle(
        new ChatRequest('施設B入居者の情報を教えてください'),
        $this->corporateAdmin
    );

    $log = \App\Models\ChatLog::latest()->first();

    expect($log->facility_id)->toBeNull();
});

test('未認証ユーザーはAPIにアクセスできないこと', function () {
    $response = $this->postJson('/api/chatbot/message', [
        'message' => '施設B入居者の情報を教えてください',
    ]);

    $response->assertStatus(401);
});

test('他施設の入居者名を直接指定してもデータが漏洩しないこと（境界値）', function () {
    // 施設Aの管理者が施設Bの部屋番号で検索
    $response = $this->service->handle(
        new ChatRequest('201号室の情報を教えてください'),
        $this->facilityAdmin1
    );

    expect($response->reply)->toContain('見つかりません');
});
