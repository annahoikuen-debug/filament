<?php

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Models\ChatLog;
use App\Models\ChatbotFaq;
use App\Models\DailyCharge;
use App\Models\ChargeItem;
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
        'room_number' => '201',
        'name' => '佐藤花子',
        'name_kana' => 'サトウハナコ',
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
    ]);
});

test('FAQに一致した場合は回答が返ること', function () {
    ChatbotFaq::create([
        'question' => '営業時間は？',
        'keywords' => ['営業時間'],
        'answer' => '9時から17時です。',
        'category' => 'general',
        'facility_id' => null,
        'is_active' => true,
    ]);

    $response = $this->service->handle(
        new ChatRequest('営業時間'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('faq')
        ->and($response->reply)->toBe('9時から17時です。');
});

test('請求額のみ（入居者名なし）で未特定応答が返ること', function () {
    $response = $this->service->handle(
        new ChatRequest('請求額'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('invoice_amount')
        ->and($response->reply)->toBe('入居者を特定できませんでした。名前または部屋番号を教えてください。');
});

test('支払い状況のみ（入居者名なし）で未特定応答が返ること', function () {
    $response = $this->service->handle(
        new ChatRequest('支払い状況'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('invoice_status')
        ->and($response->reply)->toBe('入居者を特定できませんでした。名前または部屋番号を教えてください。');
});

test('自費のみ（入居者名なし）で未特定応答が返ること', function () {
    $response = $this->service->handle(
        new ChatRequest('自費'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('daily_charge_total')
        ->and($response->reply)->toBe('入居者を特定できませんでした。名前または部屋番号を教えてください。');
});

test('FAQに一致しない場合は見つからない応答が返ること', function () {
    $response = $this->service->handle(
        new ChatRequest('xyzabc12345意味不明な質問'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('faq')
        ->and($response->reply)->toContain('回答が見つかりませんでした');
});

test('請求書ありの場合の請求額応答が返ること', function () {
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
        new ChatRequest('佐藤花子の請求額を教えて'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('invoice_amount')
        ->and($response->reply)->toContain('請求額')
        ->and($response->reply)->toContain('家賃')
        ->and($response->data['total_amount'])->toBe(75000);
});

test('支払い状況が応答されること', function () {
    MonthlyInvoice::factory()->create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-09',
        'status' => InvoiceStatus::Billed,
    ]);

    $response = $this->service->handle(
        new ChatRequest('佐藤花子の支払い状況を教えて'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('invoice_status')
        ->and($response->reply)->toContain('支払い状況')
        ->and($response->data['status'])->toBe('billed');
});

test('自費利用料合計が応答されること', function () {
    $item = ChargeItem::factory()->create(['default_price' => 1000]);

    DailyCharge::create([
        'resident_id' => $this->resident->id,
        'facility_id' => $this->facility->id,
        'charge_item_id' => $item->id,
        'date' => now()->format('Y-m-d'),
        'unit_price' => 1000,
        'quantity' => 3,
    ]);

    $response = $this->service->handle(
        new ChatRequest('佐藤花子の自費'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('daily_charge_total')
        ->and($response->reply)->toContain('自費利用料合計')
        ->and($response->data['total'])->toBe(3000);
});

test('マスキング有効時はログの氏名が***になること', function () {
    config(['chatbot.mask_names' => true]);

    $this->service->handle(
        new ChatRequest('佐藤花子の情報を教えてください'),
        $this->corporateAdmin
    );

    $log = ChatLog::latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_message)->not->toContain('佐藤花子')
        ->and($log->user_id)->toBe($this->corporateAdmin->id)
        ->and($log->facility_id)->toBe($this->corporateAdmin->facility_id);
});

test('マスキング無効時はログに氏名がそのまま残ること', function () {
    config(['chatbot.mask_names' => false]);

    $this->service->handle(
        new ChatRequest('佐藤花子の情報を教えてください'),
        $this->corporateAdmin
    );

    $log = ChatLog::latest('id')->first();

    expect($log->user_message)->toContain('佐藤花子');
});

test('入居中ステータスの入居者検索でステータスラベルが返ること', function () {
    $response = $this->service->handle(
        new ChatRequest('佐藤花子の情報を教えてください'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('resident_lookup')
        ->and($response->reply)->toContain('在籍中');
});

test('退去済ステータスの入居者検索でステータスラベルが返ること', function () {
    Resident::factory()->create([
        'facility_id' => $this->facility->id,
        'room_number' => '401',
        'name' => '高橋次郎',
        'name_kana' => 'タカハシジロウ',
        'status' => ResidentStatus::MovedOut,
        'base_rent' => 40000,
        'base_management_fee' => 10000,
    ]);

    $response = $this->service->handle(
        new ChatRequest('高橋次郎の情報を教えてください'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('resident_lookup')
        ->and($response->reply)->toContain('退去済');
});
