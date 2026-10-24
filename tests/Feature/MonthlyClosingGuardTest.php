<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Facility;
use App\Models\MonthlyClosingState;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 月次締め（closed）ガードの回帰テスト
 *
 * 締め済みの月の請求データは金額・作成・削除が変更できないが、
 * 入金消込は可能であり、差戻し（reopen）後は再び変更できる。
 */
class MonthlyClosingGuardTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;

    private Resident $resident;

    protected function setUp(): void
    {
        parent::setUp();
        $this->facility = Facility::factory()->create();
        $this->resident = Resident::factory()->create([
            'facility_id' => $this->facility->id,
            'move_in_date' => '2026-01-01',
        ]);
    }

    private function closeMonth(string $ym = '2026-09'): void
    {
        $state = MonthlyClosingState::findOrCreate($ym, $this->facility->id);
        foreach ([
            MonthlyClosingState::STATUS_GENERATED,
            MonthlyClosingState::STATUS_APPROVED,
            MonthlyClosingState::STATUS_SENT,
            MonthlyClosingState::STATUS_CLOSED,
        ] as $status) {
            $state->advanceTo($status);
        }
    }

    private function createInvoice(string $ym = '2026-09'): MonthlyInvoice
    {
        return MonthlyInvoice::create([
            'resident_id' => $this->resident->id,
            'facility_id' => $this->facility->id,
            'billing_year_month' => $ym,
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 0,
            'status' => InvoiceStatus::Billed,
        ]);
    }

    public function test_締め済み月の請求金額は変更できない(): void
    {
        $invoice = $this->createInvoice();
        $this->closeMonth();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/締め済み/');

        $invoice->update(['rent_subtotal' => 60000]);
    }

    public function test_締め済み月に新規請求は作成できない(): void
    {
        $this->closeMonth();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/締め済み/');

        $this->createInvoice();
    }

    public function test_締め済み月の請求書は削除できない(): void
    {
        $invoice = $this->createInvoice();
        $this->closeMonth();

        $this->expectException(\RuntimeException::class);

        $invoice->delete();
    }

    public function test_締め済み月でも入金消込はできる(): void
    {
        $invoice = $this->createInvoice();
        $this->closeMonth();

        $invoice->markAsPaid(PaymentMethod::BankTransfer, '2026-10-05');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertNotNull($invoice->receipt_number);
    }

    public function test_締め済み月は他施設の締め状態に影響されない(): void
    {
        // 別施設の同月は締められていないため、自施設のデータは変更可能
        $invoice = $this->createInvoice();
        $this->closeMonth(); // $this->facility のみ締め

        $otherFacility = Facility::factory()->create();
        $otherResident = Resident::factory()->create([
            'facility_id' => $otherFacility->id,
            'move_in_date' => '2026-01-01',
        ]);
        $otherInvoice = MonthlyInvoice::create([
            'resident_id' => $otherResident->id,
            'facility_id' => $otherFacility->id,
            'billing_year_month' => '2026-09',
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 0,
            'status' => InvoiceStatus::Billed,
        ]);

        $otherInvoice->update(['rent_subtotal' => 55000]);
        $this->assertSame(55000, $otherInvoice->fresh()->rent_subtotal);
    }

    public function test_差戻し後は再び変更できる(): void
    {
        $invoice = $this->createInvoice();
        $this->closeMonth();

        MonthlyClosingState::findOrCreate('2026-09', $this->facility->id)->reopen('テスト差戻し');

        $invoice->update(['rent_subtotal' => 60000]);
        $this->assertSame(60000, $invoice->fresh()->rent_subtotal);
    }
}
