<?php

use App\Enums\InvoiceStatus;
use App\Enums\ResidentStatus;
use App\Filament\Resources\MonthlyInvoiceResource;
use App\Filament\Resources\ResidentResource;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->resident = Resident::create([
        'room_number' => '101',
        'name' => 'テスト入居者',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'status' => ResidentStatus::Active,
        'move_in_date' => '2026-01-01',
    ]);

    $this->item = ChargeItem::create([
        'name' => 'テスト品目',
        'default_price' => 1000,
        'is_active' => true,
    ]);
});

test('DailyChargeResource generates correct resident edit link', function () {
    $charge = DailyCharge::create([
        'resident_id' => $this->resident->id,
        'charge_item_id' => $this->item->id,
        'date' => now()->toDateString(),
        'unit_price' => 1000,
        'quantity' => 1,
    ]);

    $url = ResidentResource::getUrl('edit', ['record' => $charge->resident]);

    expect($url)->toContain('residents')
        ->and($url)->toContain('edit');
});

test('MonthlyInvoiceResource generates correct resident edit link', function () {
    $invoice = MonthlyInvoice::create([
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $url = ResidentResource::getUrl('edit', ['record' => $invoice->resident]);

    expect($url)->toContain('residents')
        ->and($url)->toContain('edit');
});

test('MonthlyInvoiceResource generates correct daily charges link with filters', function () {
    $invoice = MonthlyInvoice::create([
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    // Call the private static method using reflection
    $reflection = new ReflectionClass(MonthlyInvoiceResource::class);
    $method = $reflection->getMethod('getDailyChargesUrl');
    $method->setAccessible(true);
    $url = $method->invoke(null, $invoice);

    expect($url)->toContain('daily-charges')
        ->and($url)->toContain('tableFilters')
        ->and($url)->toContain((string) $invoice->resident_id);
});

test('MonthlyInvoiceResource generates correct daily charges label', function () {
    $invoice = MonthlyInvoice::create([
        'resident_id' => $this->resident->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    // Call the private static method
    $reflection = new ReflectionClass(MonthlyInvoiceResource::class);
    $method = $reflection->getMethod('getDailyChargesLabel');
    $method->setAccessible(true);
    $label = $method->invoke(null, $invoice);

    expect($label)->toBeString();
});
