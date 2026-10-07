<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\MonthlyInvoiceResource\Pages\ListMonthlyInvoices;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->resident = Resident::create([
        'room_number' => '101',
        'name' => 'Filamentテスト入居者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
    ]);
});

test('MonthlyInvoiceResource一覧画面が正常に表示されること', function () {
    Livewire::test(ListMonthlyInvoices::class)
        ->assertSuccessful();
});

test('ヘッダーアクションから月次請求データ一括生成を実行できること', function () {
    Livewire::test(ListMonthlyInvoices::class)
        ->callAction('generateMonthlyInvoices', [
            'year_month' => '2026-10',
            'force_update' => false,
        ])
        ->assertHasNoActionErrors();

    expect(MonthlyInvoice::where('billing_year_month', '2026-10')->exists())->toBeTrue();
});

test('行アクションから入金消込を実行できること', function () {
    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-10',
        'resident_id' => $this->resident->id,
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    Livewire::test(ListMonthlyInvoices::class)
        ->callTableAction('markPayment', $invoice, [
            'paid_at' => '2026-11-10',
            'payment_method' => PaymentMethod::DirectDebit->value,
        ])
        ->assertHasNoTableActionErrors();

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->payment_method)->toBe(PaymentMethod::DirectDebit);
});
