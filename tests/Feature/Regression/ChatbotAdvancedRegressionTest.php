<?php

use App\Enums\InvoiceStatus;
use App\Models\ChatLog;
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

    $this->facilityA = Facility::factory()->create(['name' => '東京ケアセンター']);
    $this->facilityB = Facility::factory()->create(['name' => '横浜ケアセンター']);

    $this->userA = User::factory()->facilityAdmin()->create([
        'facility_id' => $this->facilityA->id,
    ]);

    $this->userB = User::factory()->facilityAdmin()->create([
        'facility_id' => $this->facilityB->id,
    ]);

    $this->corporateAdmin = User::factory()->corporateAdmin()->create();

    $this->residentA = Resident::factory()->create([
        'facility_id' => $this->facilityA->id,
        'name' => '木村 拓也',
        'name_kana' => 'キムラ タクヤ',
        'room_number' => '401',
    ]);

    $this->invoiceCurrent = MonthlyInvoice::factory()->create([
        'facility_id' => $this->facilityA->id,
        'resident_id' => $this->residentA->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 80000,
        'status' => InvoiceStatus::Billed,
    ]);

    $this->invoiceLastMonth = MonthlyInvoice::factory()->create([
        'facility_id' => $this->facilityA->id,
        'resident_id' => $this->residentA->id,
        'billing_year_month' => '2026-09',
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'total_amount' => 80000,
        'status' => InvoiceStatus::Paid,
        'paid_at' => '2026-10-05',
    ]);

    $this->service = app(ChatbotService::class);
});

test('【総合検証】シノニム置換、2ターン文脈、アクションリンク、フィードバック、階層クイックリプライが統合動作すること', function () {
    $sessionId = 'advanced-regression-session';

    // 1ターン目: シノニム「滞納状況」（->支払い状況）で木村拓也を特定
    $res1 = $this->service->handle(
        new ChatRequest('木村拓也の滞納状況', $sessionId),
        $this->userA
    );

    expect($res1->intent)->toBe('invoice_status')
        ->and($res1->reply)->toContain('木村 拓也')
        ->and($res1->actionLinks)->not()->toBeEmpty()
        ->and($res1->chatLogId)->not()->toBeNull();

    // 回答フィードバック送信 (Phase 5)
    $feedbackRes = $this->actingAs($this->userA)->postJson('/api/chatbot/feedback', [
        'chat_log_id' => $res1->chatLogId,
        'feedback' => 'helpful',
    ]);
    $feedbackRes->assertOk();
    expect(ChatLog::find($res1->chatLogId)->feedback)->toBe('helpful');

    // 2ターン目: 主語なし「先月の請求額は？」でセッション文脈引き継ぎ (Phase 4)
    $res2 = $this->service->handle(
        new ChatRequest('先月の請求額は？', $sessionId),
        $this->userA
    );

    expect($res2->intent)->toBe('invoice_amount')
        ->and($res2->reply)->toContain('木村 拓也')
        ->and($res2->reply)->toContain('2026 年 9 月の請求額')
        ->and($res2->actionLinks)->toHaveCount(2);

    // 未知の質問で階層クイックリプライが正しく返ること (Phase 2)
    $res3 = $this->service->handle(
        new ChatRequest('未定義の質問です', $sessionId),
        $this->userA
    );

    expect($res3->categoryQuickReplies)->toHaveKey('請求・入金')
        ->and($res3->quickReplies)->toHaveKey('請求額の確認');
});

test('【セキュリティ検証】文脈保持やリンク生成でも他施設データは100%漏洩しないこと', function () {
    $sessionId = 'leak-test-session';

    // 施設Aの管理者が照会
    $this->service->handle(
        new ChatRequest('木村拓也の請求額', $sessionId),
        $this->userA
    );

    // 施設Bの管理者が同一セッションIDで文脈質問
    $resB = $this->service->handle(
        new ChatRequest('先月の請求額は？', $sessionId),
        $this->userB
    );

    expect($resB->reply)->toContain('入居者を特定できませんでした')
        ->and($resB->reply)->not()->toContain('木村');
});

test('【PII検証】チャットログ保存時の入居者名自動伏字化が維持されていること', function () {
    $this->service->handle(
        new ChatRequest('木村 拓也の請求額を教えて'),
        $this->userA
    );

    $log = ChatLog::latest('id')->first();
    expect($log->user_message)->toContain('***')
        ->and($log->user_message)->not()->toContain('木村 拓也')
        ->and($log->bot_reply)->toContain('***')
        ->and($log->bot_reply)->not()->toContain('木村 拓也');
});
