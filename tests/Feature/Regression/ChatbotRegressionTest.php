<?php

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoiceCalculationService;

beforeEach(function () {
    $this->facility = Facility::factory()->create();
    $this->service = app(InvoiceCalculationService::class);
});

test('チャットボット導入後も請求生成が正常動作すること', function () {
    Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '501',
        'name' => '満月在籍者',
        'base_rent' => 50000,
        'base_management_fee' => 25000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-09-01',
    ]);

    $stats = $this->service->generateForMonth('2026-10');

    $invoice = MonthlyInvoice::where('billing_year_month', '2026-10')->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->rent_subtotal)->toBe(50000)
        ->and($invoice->management_fee_subtotal)->toBe(25000)
        ->and($stats['created'])->toBe(1);
});

test('チャットボット導入後も日割り計算が正常動作すること', function () {
    Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '502',
        'name' => '月中入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-10-15',
    ]);

    $stats = $this->service->generateForMonth('2026-10');

    $invoice = MonthlyInvoice::where('billing_year_month', '2026-10')->first();

    // 10月15日〜31日で17日間在籍 → 日割り計算
    // 家賃: 50000 * 17/31 = 27419, 管理費: 20000 * 17/31 = 10968
    expect($invoice->total_amount)->toBe(38387);
});

test('チャットボット導入後も入金処理が正常動作すること', function () {
    $resident = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '503',
        'name' => '入金テスト者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-09-01',
    ]);

    $this->service->generateForMonth('2026-10');

    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-10')
        ->first();

    $invoice->markAsPaid(\App\Enums\PaymentMethod::BankTransfer);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->receipt_number)->not->toBeNull();
});

test('チャットボット用マイグレーションが既存テーブルに影響を与えないこと', function () {
    // 既存テーブルのカラムが維持されていること
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('residents');

    expect($columns)->toContain('name')
        ->and($columns)->toContain('room_number')
        ->and($columns)->toContain('base_rent');

    // チャットボット用テーブルが存在すること
    expect(\Illuminate\Support\Facades\Schema::hasTable('chatbot_faqs'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Schema::hasTable('chat_logs'))->toBeTrue();
});

test('チャットボット導入後もFilament既存リソースのCRUDが正常動作すること', function () {
    $user = \App\Models\User::factory()->corporateAdmin()->create();
    $this->actingAs($user);

    $this->get(\App\Filament\Resources\ResidentResource::getUrl('index'))
        ->assertSuccessful();

    $this->get(\App\Filament\Resources\MonthlyInvoiceResource::getUrl('index'))
        ->assertSuccessful();

    $this->get(\App\Filament\Resources\ItemMasterResource::getUrl('index'))
        ->assertSuccessful();
});
