<?php

use App\Enums\InvoiceStatus;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\DTO\ChatRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Carbon::setTestNow('2026-10-15');
    Cache::flush();
    $this->facility = Facility::factory()->create();
    $this->facility2 = Facility::factory()->create();

    $this->user = User::factory()->facilityAdmin()->create([
        'facility_id' => $this->facility->id,
    ]);

    $this->residentA = Resident::factory()->create([
        'facility_id' => $this->facility->id,
        'name' => '佐藤花子',
        'room_number' => '201',
    ]);

    $this->residentB = Resident::factory()->create([
        'facility_id' => $this->facility->id,
        'name' => '鈴木一郎',
        'room_number' => '202',
    ]);

    // 佐藤花子の今月と先月の請求書を作成
    MonthlyInvoice::factory()->create([
        'resident_id' => $this->residentA->id,
        'facility_id' => $this->facility->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 70000,
        'status' => InvoiceStatus::Billed,
    ]);

    MonthlyInvoice::factory()->create([
        'resident_id' => $this->residentA->id,
        'facility_id' => $this->facility->id,
        'billing_year_month' => '2026-09',
        'rent_subtotal' => 45000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 65000,
        'status' => InvoiceStatus::Paid,
        'paid_at' => '2026-10-05',
    ]);

    $this->service = app(ChatbotService::class);
});

test('1ターン目で入居者特定後、2ターン目の主語なし「先月の請求額」で文脈が引き継がれること', function () {
    $sessionId = 'test-context-session-1';

    // 1ターン目: 佐藤花子の請求額
    $res1 = $this->service->handle(
        new ChatRequest('佐藤花子の請求額を教えて', $sessionId),
        $this->user
    );
    expect($res1->intent)->toBe('invoice_amount')
        ->and($res1->reply)->toContain('佐藤花子')
        ->and($res1->reply)->toContain('2026 年 10 月の請求額');

    // 2ターン目: 「先月の請求額は？」(名前なし)
    $res2 = $this->service->handle(
        new ChatRequest('先月の請求額', $sessionId),
        $this->user
    );
    expect($res2->intent)->toBe('invoice_amount')
        ->and($res2->reply)->toContain('佐藤花子')
        ->and($res2->reply)->toContain('2026 年 9 月の請求額');
});

test('2ターン目で「支払い状況は？」と尋ねても文脈から入居者が解決されること', function () {
    $sessionId = 'test-context-session-2';

    // 1ターン目: 佐藤花子の入居者照会
    $this->service->handle(
        new ChatRequest('201号室の入居者', $sessionId),
        $this->user
    );

    // 2ターン目: 「支払い状況は？」
    $res2 = $this->service->handle(
        new ChatRequest('支払い状況は？', $sessionId),
        $this->user
    );

    expect($res2->intent)->toBe('invoice_status')
        ->and($res2->reply)->toContain('佐藤花子');
});

test('別の入居者を質問した場合はセッション文脈が正しく上書きされること', function () {
    $sessionId = 'test-context-session-3';

    // 1ターン目: 佐藤花子
    $this->service->handle(
        new ChatRequest('佐藤花子の請求額', $sessionId),
        $this->user
    );

    // 2ターン目: 鈴木一郎
    $this->service->handle(
        new ChatRequest('鈴木一郎の情報', $sessionId),
        $this->user
    );

    // 3ターン目: 請求額は？（鈴木一郎が対象になること）
    $res3 = $this->service->handle(
        new ChatRequest('請求額は？', $sessionId),
        $this->user
    );

    expect($res3->reply)->toContain('鈴木一郎');
});

test('他施設のユーザーが同一セッションIDを使用しても他施設入居者の文脈は流用されないこと', function () {
    $sessionId = 'test-context-session-isolated';

    // facility1 のユーザーが入居者を照会
    $this->service->handle(
        new ChatRequest('佐藤花子の請求額', $sessionId),
        $this->user
    );

    // facility2 のユーザーが同一セッションIDで主語なし質問
    $userFacility2 = User::factory()->facilityAdmin()->create([
        'facility_id' => $this->facility2->id,
    ]);

    $res2 = $this->service->handle(
        new ChatRequest('先月の請求額は？', $sessionId),
        $userFacility2
    );

    // 他施設の佐藤花子は解決されず、入居者特定エラーになること
    expect($res2->reply)->toContain('入居者を特定できませんでした');
});
