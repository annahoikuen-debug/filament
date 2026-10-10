<?php

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\DTO\ChatRequest;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-15');

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

    // 2026-08 (先々月)
    MonthlyInvoice::factory()->create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-08',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 3000,
        'status' => InvoiceStatus::Paid,
        'paid_at' => '2026-08-25',
    ]);

    // 2026-09 (先月)
    MonthlyInvoice::factory()->create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-09',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 5000,
        'status' => InvoiceStatus::Billed,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('先月の請求額が正しく返ること', function () {
    $response = $this->service->handle(
        new ChatRequest('山田太郎の先月の請求額はいくらですか'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('invoice_amount')
        ->and($response->reply)->toContain('2026 年 9 月')
        ->and($response->reply)->toContain('75,000円');
});

test('任意月（2026年8月）の請求額が正しく返ること', function () {
    $response = $this->service->handle(
        new ChatRequest('山田太郎の2026年8月の請求額'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('invoice_amount')
        ->and($response->reply)->toContain('2026 年 8 月')
        ->and($response->reply)->toContain('73,000円');
});

test('先々月の支払い状況が正しく返ること', function () {
    $response = $this->service->handle(
        new ChatRequest('山田太郎の先々月の支払い状況'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('invoice_status')
        ->and($response->reply)->toContain('2026 年 8 月')
        ->and($response->reply)->toContain('入金済');
});

test('未来月の請求額はエラー（まだ作成されていない）応答になること', function () {
    $response = $this->service->handle(
        new ChatRequest('山田太郎の2026年12月の請求額'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('invoice_amount')
        ->and($response->reply)->toContain('未来月')
        ->and($response->reply)->toContain('まだ作成されていません');
});

test('未来月の自費利用料はエラー応答になること', function () {
    $response = $this->service->handle(
        new ChatRequest('山田太郎の2026年12月の自費利用料'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('daily_charge_total')
        ->and($response->reply)->toContain('未来月')
        ->and($response->reply)->toContain('まだありません');
});

test('期間指定なしは最新月（既存動作維持）が返ること', function () {
    $response = $this->service->handle(
        new ChatRequest('山田太郎の請求額はいくらですか'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('invoice_amount')
        ->and($response->reply)->toContain('2026 年 9 月')
        ->and($response->reply)->toContain('75,000円');
});
