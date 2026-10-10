<?php

use App\Enums\InvoiceStatus;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\Invoice\InvoicePersister;
use Illuminate\Support\Facades\DB;

function persisterData(Resident $resident, array $overrides = []): array
{
    return array_merge([
        'resident' => $resident,
        'year_month' => '2026-03',
        'rent_subtotal' => 50000,
        'management_subtotal' => 10000,
        'service_subtotal' => 20000,
        'total_amount' => 80000,
        'taxable_amount' => 80000,
        'tax_amount' => 8000,
        'tax_rate' => 10.0,
        'tax_breakdown' => ['standard' => ['base' => 80000, 'tax' => 8000]],
        'force_update' => false,
    ], $overrides);
}

it('creates a new unbilled invoice', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);

    $persister = new InvoicePersister;
    $stats = $persister->persist(persisterData($resident));

    expect($stats['created'])->toBe(1)
        ->and($stats['updated'])->toBe(0)
        ->and($stats['skipped'])->toBe(0);

    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-03')
        ->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Unbilled)
        ->and($invoice->rent_subtotal)->toBe(50000)
        ->and($invoice->service_subtotal)->toBe(20000);
});

it('updates an existing unbilled invoice', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);

    $persister = new InvoicePersister;
    $persister->persist(persisterData($resident));

    $stats = $persister->persist(persisterData($resident, ['total_amount' => 90000]));

    expect($stats['updated'])->toBe(1)
        ->and($stats['created'])->toBe(0);

    $invoice = MonthlyInvoice::where('resident_id', $resident->id)
        ->where('billing_year_month', '2026-03')
        ->first();

    expect($invoice->total_amount)->toBe(90000);
});

it('skips update when invoice is already billed without force flag', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);

    MonthlyInvoice::create([
        'billing_year_month' => '2026-03',
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 10000,
        'service_subtotal' => 20000,
        'total_amount' => 80000,
        'status' => InvoiceStatus::Billed,
        'version' => 0,
    ]);

    $persister = new InvoicePersister;
    $stats = $persister->persist(persisterData($resident, ['total_amount' => 90000]));

    expect($stats['skipped'])->toBe(1)
        ->and($stats['updated'])->toBe(0);

    expect(MonthlyInvoice::where('resident_id', $resident->id)->first()->total_amount)->toBe(80000);
});

it('updates billed invoice with force_update flag', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);

    MonthlyInvoice::create([
        'billing_year_month' => '2026-03',
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 10000,
        'service_subtotal' => 20000,
        'total_amount' => 80000,
        'status' => InvoiceStatus::Billed,
        'version' => 0,
    ]);

    $persister = new InvoicePersister;
    $stats = $persister->persist(persisterData($resident, ['total_amount' => 90000, 'force_update' => true]));

    expect($stats['updated'])->toBe(1)
        ->and($stats['skipped'])->toBe(0);

    expect(MonthlyInvoice::where('resident_id', $resident->id)->first()->total_amount)->toBe(90000);
});

it('handles multiple residents independently', function () {
    $facility = Facility::factory()->create();
    $resident1 = Resident::factory()->create(['facility_id' => $facility->id]);
    $resident2 = Resident::factory()->create(['facility_id' => $facility->id]);

    $persister = new InvoicePersister;
    $stats1 = $persister->persist(persisterData($resident1));
    $stats2 = $persister->persist(persisterData($resident2));

    expect($stats1['created'])->toBe(1)
        ->and($stats2['created'])->toBe(1)
        ->and(MonthlyInvoice::count())->toBe(2);
});

it('updates invoice created by another path', function () {
    $facility = Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);

    // 直接DBに作成（別パスからの作成を想定）
    DB::table('monthly_invoices')->insert([
        'billing_year_month' => '2026-03',
        'resident_id' => $resident->id,
        'facility_id' => $facility->id,
        'rent_subtotal' => 1,
        'management_fee_subtotal' => 1,
        'service_subtotal' => 1,
        'total_amount' => 3,
        'status' => 'unbilled',
        'version' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $persister = new InvoicePersister;
    $stats = $persister->persist(persisterData($resident, ['total_amount' => 12345]));

    expect($stats['updated'])->toBe(1);
    expect(MonthlyInvoice::where('resident_id', $resident->id)->first()->total_amount)->toBe(12345);
});
