<?php

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Models\ChatLog;
use App\Models\ChatbotFaq;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\DTO\ChatRequest;

beforeEach(function () {
    $this->facilityA = Facility::factory()->create(['name' => '南ケアセンター']);
    $this->facilityB = Facility::factory()->create(['name' => '北ケアセンター']);

    $this->corporateAdmin = User::factory()->corporateAdmin()->create();
    $this->facilityAdminA = User::factory()->facilityAdmin()->create(['facility_id' => $this->facilityA->id]);
    $this->facilityAdminB = User::factory()->facilityAdmin()->create(['facility_id' => $this->facilityB->id]);

    $this->service = app(ChatbotService::class);

    $this->residentA = Resident::factory()->create([
        'facility_id' => $this->facilityA->id,
        'room_number' => '101',
        'name' => '佐藤健一',
        'name_kana' => 'サトウケンイチ',
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
        'base_rent' => 60000,
        'base_management_fee' => 20000,
    ]);

    $this->residentB = Resident::factory()->create([
        'facility_id' => $this->facilityB->id,
        'room_number' => '201',
        'name' => '中村健二',
        'name_kana' => 'ナカムラケンジ',
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
        'base_rent' => 70000,
        'base_management_fee' => 25000,
    ]);

    MonthlyInvoice::factory()->create([
        'facility_id' => $this->facilityA->id,
        'resident_id' => $this->residentA->id,
        'billing_year_month' => '2026-09',
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Paid,
        'paid_at' => '2026-09-25',
    ]);
});

test('改善後も既存の入居者照会・請求照会が正常動作すること', function () {
    // 入居者照会
    $lookupResponse = $this->service->handle(
        new ChatRequest('佐藤健一の情報を教えてください'),
        $this->facilityAdminA
    );
    expect($lookupResponse->intent)->toBe('resident_lookup')
        ->and($lookupResponse->reply)->toContain('佐藤健一')
        ->and($lookupResponse->reply)->toContain('101号室');

    // 請求額照会
    $invoiceResponse = $this->service->handle(
        new ChatRequest('佐藤健一の請求額はいくらですか'),
        $this->facilityAdminA
    );
    expect($invoiceResponse->intent)->toBe('invoice_amount')
        ->and($invoiceResponse->reply)->toContain('80,000円');

    // 支払状況照会
    $statusResponse = $this->service->handle(
        new ChatRequest('佐藤健一の支払い状況を教えてください'),
        $this->facilityAdminA
    );
    expect($statusResponse->intent)->toBe('invoice_status')
        ->and($statusResponse->reply)->toContain('入金済');
});

test('改善後も施設スコープ（他施設情報漏洩防止）が厳密に維持されていること', function () {
    // 施設A管理者が施設Bの入居者（中村健二）を質問しても見つからない
    $response = $this->service->handle(
        new ChatRequest('中村健二の請求額'),
        $this->facilityAdminA
    );
    expect($response->reply)->toContain('入居者を特定できませんでした');

    // 施設A管理者の検索でも施設Bの情報は漏洩しない
    $lookupResponse = $this->service->handle(
        new ChatRequest('中村健二の情報'),
        $this->facilityAdminA
    );
    expect($lookupResponse->reply)->toContain('見つかりませんでした');
});

test('チャットログのマスキング機能が維持されていること', function () {
    config()->set('chatbot.mask_names', true);

    $this->service->handle(
        new ChatRequest('佐藤健一の請求額を教えて'),
        $this->facilityAdminA
    );

    $log = ChatLog::latest('id')->first();
    expect($log->user_message)->toContain('***の請求額を教えて')
        ->and($log->user_message)->not()->toContain('佐藤健一');
});

test('FAQ自動応答機能が正常動作し、改善版の追加データと両立すること', function () {
    ChatbotFaq::create([
        'question' => '施設のWi-Fi環境について教えてください',
        'keywords' => ['Wi-Fi', 'ワイファイ', 'インターネット'],
        'answer' => '全居室および共有スペースで無料Wi-Fiをご利用いただけます。',
        'category' => '施設環境',
    ]);

    $response = $this->service->handle(
        new ChatRequest('Wi-Fiは使えますか？'),
        $this->facilityAdminA
    );

    expect($response->intent)->toBe('faq')
        ->and($response->reply)->toContain('無料Wi-Fi')
        ->and($response->quickReplies)->toBeEmpty();
});
