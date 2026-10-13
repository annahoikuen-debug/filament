<?php

use App\Enums\InvoiceStatus;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use Carbon\Carbon;

test('請求書と領収書のPDFファイル名に当日日付Ymdが付与されること', function () {
    Carbon::setTestNow('2026-10-15');

    $facility = Facility::factory()->create();
    $resident = Resident::create([
        'facility_id' => $facility->id,
        'room_number' => '303',
        'name' => '日付テスト入居者',
        'base_rent' => 60000,
        'base_management_fee' => 30000,
        'move_in_date' => '2026-01-01',
    ]);

    $invoice = MonthlyInvoice::create([
        'facility_id' => $facility->id,
        'resident_id' => $resident->id,
        'billing_year_month' => '2026-10',
        'rent_subtotal' => 60000,
        'management_fee_subtotal' => 30000,
        'service_subtotal' => 0,
        'status' => InvoiceStatus::Paid,
    ]);

    $invoiceFileName = sprintf(
        '請求書_%s_%s号室_%s様_%s.pdf',
        $invoice->billing_year_month,
        $invoice->resident->room_number,
        $invoice->resident->name,
        now()->format('Ymd')
    );

    $receiptFileName = sprintf(
        '領収証_%s_%s号室_%s様_%s.pdf',
        $invoice->billing_year_month,
        $invoice->resident->room_number,
        $invoice->resident->name,
        now()->format('Ymd')
    );

    expect($invoiceFileName)->toBe('請求書_2026-10_303号室_日付テスト入居者様_20261015.pdf')
        ->and($receiptFileName)->toBe('領収証_2026-10_303号室_日付テスト入居者様_20261015.pdf')
        ->and($invoiceFileName)->not->toMatch('/[\\\\\\/:*?"<>|]/');

    Carbon::setTestNow();
});
