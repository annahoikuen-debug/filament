<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\MonthlyInvoiceResource\Pages\ListMonthlyInvoices;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->user);

    $this->facility = Facility::factory()->create();
    $this->resident1 = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '101',
        'name' => '入居者1',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);
    $this->resident2 = Resident::create([
        'facility_id' => $this->facility->id,
        'room_number' => '102',
        'name' => '入居者2',
        'base_rent' => 50000,
        'base_management_fee' => 20000,
        'move_in_date' => '2026-01-01',
    ]);
});

test('markAsBilled一括アクションは未請求のみ更新し入金済・請求済を上書きしないこと', function () {
    $unbilled = MonthlyInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident1->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Unbilled,
    ]);

    $paid = MonthlyInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident2->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Paid,
        'paid_at' => '2026-10-25',
        'payment_method' => PaymentMethod::DirectDebit,
    ]);

    Livewire::test(ListMonthlyInvoices::class)
        ->callTableBulkAction('markAsBilled', [$unbilled, $paid]);

    $unbilled->refresh();
    $paid->refresh();

    expect($unbilled->status)->toBe(InvoiceStatus::Billed)
        ->and($paid->status)->toBe(InvoiceStatus::Paid); // 入金済が請求済に巻き戻っていないこと
});

test('bulkMarkAsPaid一括アクションはすでに入金済のデータを二重消込しないこと', function () {
    $billed = MonthlyInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident1->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Billed,
    ]);

    $alreadyPaid = MonthlyInvoice::create([
        'facility_id' => $this->facility->id,
        'resident_id' => $this->resident2->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 20000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Paid,
        'paid_at' => '2026-10-10',
        'payment_method' => PaymentMethod::BankTransfer,
        'receipt_number' => 'REC-ORIGINAL-999',
    ]);

    Livewire::test(ListMonthlyInvoices::class)
        ->callTableBulkAction('bulkMarkAsPaid', [$billed, $alreadyPaid], [
            'receipt_date_mode' => 'auto',
        ]);

    $billed->refresh();
    $alreadyPaid->refresh();

    expect($billed->status)->toBe(InvoiceStatus::Paid)
        ->and($billed->payment_method)->toBe(PaymentMethod::DirectDebit)
        ->and($alreadyPaid->receipt_number)->toBe('REC-ORIGINAL-999') // 元の領収書番号が維持されること
        ->and($alreadyPaid->payment_method)->toBe(PaymentMethod::BankTransfer); // 元の支払方法が維持されること
});
