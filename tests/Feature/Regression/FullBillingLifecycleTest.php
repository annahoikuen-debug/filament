<?php

use App\Enums\InvoiceStatus;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use App\Services\InvoiceCalculationService;
use Carbon\Carbon;

beforeEach(function () {
    $this->facility = Facility::factory()->create();
    $this->admin = User::factory()->create(['is_admin' => true, 'facility_id' => $this->facility->id]);
    $this->actingAs($this->admin);

    $this->resident = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '101',
        'name' => 'E2E検証入居者',
        'status' => 'active',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
    ]);

    $this->chargeItem = ChargeItem::create([
        'name' => '日用品パック',
        'default_price' => 5000,
        'is_active' => true,
    ]);
});

test('自費記録入力から月次請求生成、消込までのライフサイクルが一気通貫で正常完了すること', function () {
    // 1. 日次自費記録作成
    $charge = DailyCharge::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident->id,
        'charge_item_id' => $this->chargeItem->id,
        'date' => '2026-10-10',
        'unit_price' => 5000,
        'quantity' => 1,
        'note' => 'E2Eテスト',
    ]);
    expect($charge->exists)->toBeTrue();

    // 2. 月次請求データの一括生成
    $calcService = app(InvoiceCalculationService::class);
    $stats = $calcService->generateForMonth('2026-10', true);
    expect($stats['created'] + $stats['updated'])->toBeGreaterThan(0);

    $invoice = MonthlyInvoice::where('billing_year_month', '2026-10')
        ->where('resident_id', $this->resident->id)
        ->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->rent_subtotal)->toBe(60000)
        ->and($invoice->management_fee_subtotal)->toBe(30000)
        ->and($invoice->service_subtotal)->toBe(5000)
        ->and($invoice->status)->toBe(InvoiceStatus::Unbilled);

    // 3. 請求済への確定
    $invoice->update(['status' => InvoiceStatus::Billed]);
    expect($invoice->status)->toBe(InvoiceStatus::Billed);

    // 4. 入金消込の実行
    $invoice->markAsPaid(\App\Enums\PaymentMethod::BankTransfer, '2026-10-25');
    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->paid_at->format('Y-m-d'))->toBe('2026-10-25')
        ->and($invoice->receipt_number)->not->toBeNull();
});
