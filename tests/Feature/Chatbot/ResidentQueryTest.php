<?php

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Models\ChatbotFaq;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\DTO\ChatRequest;

beforeEach(function () {
    $this->facility = Facility::factory()->create();
    $this->corporateAdmin = User::factory()->corporateAdmin()->create();
    $this->service = app(ChatbotService::class);

    $this->resident = Resident::factory()->create([
        'facility_id' => $this->facility->id,
        'room_number' => '101',
        'name' => '山田太郎',
        'name_kana' => 'ヤマダタロウ',
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
    ]);
});

test('名前で入居者検索ができること', function () {
    $response = $this->service->handle(
        new ChatRequest('山田太郎の情報を教えてください'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('resident_lookup')
        ->and($response->reply)->toContain('山田太郎')
        ->and($response->reply)->toContain('101');
});

test('部屋番号で入居者検索ができること', function () {
    $response = $this->service->handle(
        new ChatRequest('101号室の情報を教えてください'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('resident_lookup')
        ->and($response->reply)->toContain('山田太郎');
});

test('最新請求書の金額が正しく返ること', function () {
    MonthlyInvoice::factory()->create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-09',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 5000,
        'status' => InvoiceStatus::Billed,
    ]);

    $response = $this->service->handle(
        new ChatRequest('山田太郎の今月の請求額はいくらですか'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('invoice_amount')
        ->and($response->reply)->toContain('75,000円');
});

test('支払状況が正しく返ること', function () {
    MonthlyInvoice::factory()->create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-09',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Billed,
    ]);

    $response = $this->service->handle(
        new ChatRequest('山田太郎の支払い状況を教えてください'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('invoice_status')
        ->and($response->reply)->toContain('請求済');
});

test('入金済の場合は入金日が表示されること', function () {
    MonthlyInvoice::factory()->create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-09',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Paid,
        'paid_at' => '2026-09-25',
    ]);

    $response = $this->service->handle(
        new ChatRequest('山田太郎の支払い状況を教えてください'),
        $this->corporateAdmin
    );

    expect($response->reply)->toContain('入金済')
        ->and($response->reply)->toContain('2026/09/25');
});

test('月中途入居者の日割り根拠が応答に含まれること', function () {
    $proratedResident = Resident::factory()->create([
        'facility_id' => $this->facility->id,
        'room_number' => '102',
        'name' => '月中入居者',
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-09-15',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
    ]);

    MonthlyInvoice::factory()->create([
        'facility_id' => $this->facility->id,
        'resident_id' => $proratedResident->id,
        'billing_year_month' => '2026-09',
        'rent_subtotal' => 27419,
        'management_fee_subtotal' => 10968,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Billed,
    ]);

    $response = $this->service->handle(
        new ChatRequest('月中入居者の今月の請求額はいくらですか'),
        $this->corporateAdmin
    );

    expect($response->reply)->toContain('日割り計算')
        ->and($response->reply)->toContain('16日/30日');
});

test('存在しない名前は見つかりません応答になること', function () {
    $response = $this->service->handle(
        new ChatRequest('存在しない人の情報を教えてください'),
        $this->corporateAdmin
    );

    expect($response->reply)->toContain('見つかりません');
});

test('FAQに一致する質問はFAQ回答が返ること', function () {
    ChatbotFaq::create([
        'question' => '利用料の支払い方法を教えてください',
        'keywords' => ['支払い', '振込'],
        'answer' => '利用料は毎月25日に口座振込でのお支払いです。',
        'category' => '支払い',
    ]);

    $response = $this->service->handle(
        new ChatRequest('支払い方法について教えてください'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('faq')
        ->and($response->reply)->toContain('口座振込');
});
