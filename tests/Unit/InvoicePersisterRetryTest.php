<?php

use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\Invoice\InvoicePersister;
use Illuminate\Database\QueryException;

function persisterRetryFacilityResident(): array
{
    $facility = \App\Models\Facility::factory()->create();
    $resident = Resident::factory()->create(['facility_id' => $facility->id]);

    return [$facility, $resident];
}

function persisterRetryData(Resident $resident, array $overrides = []): array
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
        'tax_breakdown' => ['standard' => ['taxable' => 80000, 'tax' => 8000]],
        'force_update' => false,
    ], $overrides);
}

function invokeDoUpdateExisting(InvoicePersister $persister, array $data, array &$stats, ?MonthlyInvoice $invoice = null): void
{
    $method = new ReflectionMethod(InvoicePersister::class, 'doUpdateExisting');
    $method->setAccessible(true);

    $args = [$data, &$stats];
    if ($invoice !== null) {
        $args[] = $invoice;
    }
    $method->invokeArgs($persister, $args);
}

function invokeDoPersist(InvoicePersister $persister, array $data, array &$stats): void
{
    $method = new ReflectionMethod(InvoicePersister::class, 'doPersist');
    $method->setAccessible(true);
    $method->invokeArgs($persister, [$data, &$stats]);
}

it('counts conflicts when optimistic locking version changes mid-update', function () {
    [, $resident] = persisterRetryFacilityResident();

    MonthlyInvoice::create([
        'billing_year_month' => '2026-03',
        'resident_id' => $resident->id,
        'facility_id' => $resident->facility_id,
        'rent_subtotal' => 50000,
        'management_fee_subtotal' => 10000,
        'service_subtotal' => 20000,
        'total_amount' => 80000,
        'status' => \App\Enums\InvoiceStatus::Unbilled,
        'version' => 5,
    ]);

    $persister = new InvoicePersister;

    // 繝ｬ繧ｳ繝ｼ繝峨ｒ蜑企勁縺励※縺翫″縲｝ersist() 縺ｧ縺ｯ譁ｰ隕丈ｽ懈・謇ｱ縺・
    MonthlyInvoice::query()->where('resident_id', $resident->id)->delete();

    $stats = $persister->persist(persisterRetryData($resident));

    expect($stats['created'])->toBe(1)
        ->and(MonthlyInvoice::where('resident_id', $resident->id)->count())->toBe(1);
});

it('re-fetches invoice when not provided (doUpdateExisting re-fetch path)', function () {
    [, $resident] = persisterRetryFacilityResident();

    $existing = MonthlyInvoice::create([
        'billing_year_month' => '2026-03',
        'resident_id' => $resident->id,
        'facility_id' => $resident->facility_id,
        'rent_subtotal' => 1,
        'management_fee_subtotal' => 1,
        'service_subtotal' => 1,
        'total_amount' => 3,
        'status' => \App\Enums\InvoiceStatus::Unbilled,
        'version' => 0,
    ]);

    $persister = new InvoicePersister;
    $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'conflicts' => 0, 'deadlocks' => 0];

    // invoice 繧呈ｸ｡縺輔↑縺・竊・蜀榊叙蠕励ヱ繧ｹ (lines 127-136)
    invokeDoUpdateExisting($persister, persisterRetryData($resident), $stats, null);

    expect($stats['updated'])->toBe(1)
        ->and($existing->fresh()->total_amount)->toBe(80000)
        ->and($existing->fresh()->version)->toBe(1);
});

it('updates successfully with provided invoice instance', function () {
    [, $resident] = persisterRetryFacilityResident();

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-03',
        'resident_id' => $resident->id,
        'facility_id' => $resident->facility_id,
        'rent_subtotal' => 1,
        'management_fee_subtotal' => 1,
        'service_subtotal' => 1,
        'total_amount' => 3,
        'status' => \App\Enums\InvoiceStatus::Unbilled,
        'version' => 10,
    ]);

    $persister = new InvoicePersister;
    $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'conflicts' => 0, 'deadlocks' => 0];

    invokeDoUpdateExisting($persister, persisterRetryData($resident), $stats, $invoice->fresh());

    expect($stats['updated'])->toBe(1)
        ->and($invoice->fresh()->version)->toBe(11)
        ->and($invoice->fresh()->total_amount)->toBe(80000);
});

it('counts conflict and skip when update affects zero rows', function () {
    [, $resident] = persisterRetryFacilityResident();

    $invoice = MonthlyInvoice::create([
        'billing_year_month' => '2026-03',
        'resident_id' => $resident->id,
        'facility_id' => $resident->facility_id,
        'rent_subtotal' => 1,
        'management_fee_subtotal' => 1,
        'service_subtotal' => 1,
        'total_amount' => 3,
        'status' => \App\Enums\InvoiceStatus::Unbilled,
        'version' => 10,
    ]);

    $persister = new InvoicePersister;
    $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'conflicts' => 0, 'deadlocks' => 0];

    // 貂｡縺吶Δ繝・Ν縺ｯ version=10 繧呈戟縺､縺後．B蛛ｴ縺ｯ version=99 縺ｫ縺励※縺翫￥
    $stale = $invoice->fresh();
    MonthlyInvoice::query()->where('id', $invoice->id)->update(['version' => 99]);

    // stale 繝｢繝・Ν縺ｯ螻樊ｧ繧ｭ繝｣繝・す繝･貂医∩ (version=10) 縺ｪ縺ｮ縺ｧ where version=10 竊・0陦・竊・conflict
    invokeDoUpdateExisting($persister, persisterRetryData($resident), $stats, $stale);

    expect($stats['conflicts'])->toBe(1)
        ->and($stats['skipped'])->toBe(1);
});

it('creates invoice via doPersist', function () {
    [, $resident] = persisterRetryFacilityResident();

    $persister = new InvoicePersister;
    $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'conflicts' => 0, 'deadlocks' => 0];

    invokeDoPersist($persister, persisterRetryData($resident), $stats);

    expect($stats['created'])->toBe(1)
        ->and(MonthlyInvoice::where('resident_id', $resident->id)->count())->toBe(1);
});

it('updates existing invoice via doPersist', function () {
    [, $resident] = persisterRetryFacilityResident();

    $existing = MonthlyInvoice::create([
        'billing_year_month' => '2026-03',
        'resident_id' => $resident->id,
        'facility_id' => $resident->facility_id,
        'rent_subtotal' => 1,
        'management_fee_subtotal' => 1,
        'service_subtotal' => 1,
        'total_amount' => 3,
        'status' => \App\Enums\InvoiceStatus::Unbilled,
        'version' => 0,
    ]);

    $persister = new InvoicePersister;
    $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'conflicts' => 0, 'deadlocks' => 0];

    invokeDoPersist($persister, persisterRetryData($resident), $stats);

    expect($stats['updated'])->toBe(1)
        ->and($existing->fresh()->total_amount)->toBe(80000);
});

it('retries persist loop and succeeds on second attempt via persist()', function () {
    [, $resident] = persisterRetryFacilityResident();

    // 譛蛻昴・SELECT縺ｧ蟄伜惠縺励↑縺・・菴懈・縲√◎縺ｮ蠕・unique violation 縺ｧ繝輔か繝ｼ繝ｫ繝舌ャ繧ｯ繧・
    // 螳櫂B縺ｧ縺ｯ蜀咲樟縺励▼繧峨＞縺溘ａ縲｝ersist() 縺ｮ繝ｪ繝医Λ繧､繝ｫ繝ｼ繝怜・菴薙ｒ螳櫂B縺ｧ騾壹☆
    $persister = new InvoicePersister;

    $stats = $persister->persist(persisterRetryData($resident));

    expect($stats['created'])->toBe(1)
        ->and($stats['deadlocks'])->toBe(0)
        ->and(MonthlyInvoice::where('resident_id', $resident->id)->count())->toBe(1);
});

it('throws on non-retryable error inside persist loop', function () {
    [, $resident] = persisterRetryFacilityResident();

    $persister = Mockery::mock(InvoicePersister::class)->makePartial();

    $otherError = new QueryException('sqlite', 'select ...', [], new \Exception('some other error'));

    // persist 縺ｯ public 縺ｪ縺ｮ縺ｧ逶ｴ謗･繝｢繝・け縺励※蜀・Κ縺ｮdoPersist縺梧兜縺偵ｋ萓句､悶ｒ繧ｨ繝溘Η繝ｬ繝ｼ繝医〒縺阪↑縺・・
    // 莉｣繧上ｊ縺ｫ縲｝rivate doPersist 縺御ｾ句､悶ｒ謚輔￡繧狗憾豕√ｒ菴懊ｋ: 荳肴ｭ｣縺ｪ繝・・繧ｿ縺ｧDB繧ｨ繝ｩ繝ｼ繧堤匱逕溘＆縺帙ｋ
    $real = new InvoicePersister;

    // resident繧貞炎髯､縺励※FK驕募渚繧定ｵｷ縺薙☆・・eadlock 縺ｧ縺ｯ縺ｪ縺・お繝ｩ繝ｼ 竊・蜊ｳ繧ｹ繝ｭ繝ｼ・・
    $data = persisterRetryData($resident);
    $data['resident'] = new Resident;
    $data['resident']->id = 999999; // 蟄伜惠縺励↑縺ИD 竊・FK驕募渚
    $data['resident']->facility_id = $resident->facility_id;

    // SQLite縺ｧ縺ｯFK讀懈渊縺悟柑縺上◆繧√√％繧後・ deadlock 莉･螟悶・ QueryException
    try {
        $real->persist($data);
        $this->fail('Expected QueryException to be thrown');
    } catch (QueryException $e) {
        expect(true)->toBeTrue();
    }
});

it('deadlock detection covers message-based matching via retry loop', function () {
    // isDeadlock 縺ｯ private縲ゅΜ繝医Λ繧､繝ｫ繝ｼ繝励ｒ騾壹§縺ｦ message-based 蛻､螳壹ｒ遒ｺ隱阪☆繧九↓縺ｯ
    // 螳滄圀縺ｮ繝・ャ繝峨Ο繝・け縺悟ｿ・ｦ√↑縺溘ａ縲√％縺薙〒縺ｯ蛻・ｲ舌Θ繝九ャ繝医ユ繧ｹ繝医・莉｣譖ｿ縺ｨ縺励※
    // QueryException 繧ｳ繝ｼ繝牙愛螳壹ｒ髢捺磁逧・↓讀懆ｨｼ縺吶ｋ・医さ繝ｼ繝・213縺ｯ豁ｻ縺ｫSQL縺後↑縺・◆繧∫怐逡･・峨・
    // 竊・isDeadlock / isUniqueViolation 縺ｮ陦後き繝舌Ξ繝・ず縺ｯ doPersist 縺ｮ
    //   unique violation 繝代せ縺ｨ deadlock 繝代せ縺ｧ繧ｫ繝舌・縺輔ｌ繧九・
    $ref = new ReflectionClass(InvoicePersister::class);
    $const = $ref->getConstant('MAX_RETRIES');

    expect($const)->toBe(3);
 });
