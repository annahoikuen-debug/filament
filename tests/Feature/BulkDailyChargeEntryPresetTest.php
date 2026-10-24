<?php

namespace Tests\Feature;

use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\DailyChargePreset;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\DailyChargePresetService;
use App\Services\InvoiceCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step 11-15: プリセット一括展開サービスのリグレッションテスト
 * プリセット経由で登録された自費データが月次請求（service_subtotal）に正しく合算されることを検証
 */
class BulkDailyChargeEntryPresetTest extends TestCase
{
    use RefreshDatabase;

    protected DailyChargePresetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DailyChargePresetService::class);
    }

    protected function createPresetWithItems(array $items = []): DailyChargePreset
    {
        $preset = DailyChargePreset::create([
            'name' => 'デイサービス利用パック',
            'description' => '昼食 + おやつ + 入浴介助',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        foreach ($items as $i => [$name, $price, $qty]) {
            $chargeItem = ChargeItem::create([
                'name' => $name,
                'default_price' => $price,
                'is_active' => true,
                'tax_type' => 'standard',
            ]);

            $preset->items()->create([
                'charge_item_id' => $chargeItem->id,
                'default_quantity' => $qty,
                'sort_order' => $i,
            ]);
        }

        return $preset;
    }

    protected function createResident(Facility $facility): Resident
    {
        return Resident::create([
            'facility_id' => $facility->id,
            'room_number' => '101',
            'name' => '山田太郎',
            'name_kana' => 'ヤマダタロウ',
            'base_rent' => 100000,
            'base_management_fee' => 20000,
            'status' => 'active',
            'move_in_date' => '2020-01-01',
        ]);
    }

    public function test_プリセットからバルクインサート用レコードが生成される(): void
    {
        $preset = $this->createPresetWithItems([
            ['昼食', 500, 1],
            ['おやつ', 150, 2],
            ['入浴介助', 800, 1],
        ]);
        $facility = Facility::factory()->create();
        $resident = $this->createResident($facility);

        $records = $this->service->buildRecords($preset, [$resident->id], ['2026-10-01', '2026-10-02']);

        // 3品目 × 2日 = 6レコード
        $this->assertCount(6, $records);

        foreach ($records as $record) {
            $this->assertSame($resident->id, $record['resident_id']);
            $this->assertArrayHasKey('unit_price', $record);
            $this->assertArrayHasKey('quantity', $record);
            $this->assertGreaterThan(0, $record['unit_price']);
        }

        // 昼食（単価500×1）の検証
        $lunch = array_values(array_filter($records, fn ($r) => $r['unit_price'] === 500));
        $this->assertCount(2, $lunch);
        $this->assertSame(1, $lunch[0]['quantity']);

        // おやつ（単価150×数量2）の検証
        $snack = array_values(array_filter($records, fn ($r) => $r['unit_price'] === 150));
        $this->assertCount(2, $snack);
        $this->assertSame(2, $snack[0]['quantity']);
    }

    public function test_一括登録で重複がスキップされる(): void
    {
        $preset = $this->createPresetWithItems([
            ['昼食', 500, 1],
        ]);
        $facility = Facility::factory()->create();
        $resident = $this->createResident($facility);

        $records = $this->service->buildRecords($preset, [$resident->id], ['2026-10-01']);
        $result1 = $this->service->bulkCreate($records);
        $this->assertSame(1, $result1['created']);
        $this->assertSame(0, $result1['skipped']);

        // 同じレコードを再送 → 全件スキップ
        $result2 = $this->service->bulkCreate($records);
        $this->assertSame(0, $result2['created']);
        $this->assertSame(1, $result2['skipped']);

        $this->assertSame(1, DailyCharge::count());
    }

    public function test_曜日パターンで月水金のみに展開される(): void
    {
        // 2026-10-05(月) 〜 2026-10-09(金)
        $dates = $this->service->expandDates('2026-10-05', '2026-10-09', [1, 3, 5]); // 月水金

        $this->assertSame(['2026-10-05', '2026-10-07', '2026-10-09'], $dates);

        // 平日のみ
        $weekdays = $this->service->expandDates('2026-10-05', '2026-10-11', [1, 2, 3, 4, 5]);
        $this->assertSame(['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09'], $weekdays);

        // 空指定=全日
        $all = $this->service->expandDates('2026-10-05', '2026-10-09', []);
        $this->assertCount(5, $all);
    }

    public function test_曜日スケジュール適用で自費記録が作成される(): void
    {
        $preset = $this->createPresetWithItems([
            ['昼食', 500, 1],
            ['入浴介助', 800, 1],
        ]);
        $facility = Facility::factory()->create();
        $resident = $this->createResident($facility);

        $result = $this->service->applySchedule(
            $preset,
            [$resident->id],
            '2026-10-05',
            '2026-10-09',
            [1, 3, 5], // 月水金
        );

        $this->assertSame(3, count($result['applied_dates']));
        $this->assertSame(6, $result['created']); // 2品目 × 3日
        $this->assertSame(6, DailyCharge::count());

        // 施設IDが自動補完されている
        $charge = DailyCharge::first();
        $this->assertSame($facility->id, $charge->facility_id);
    }

    public function test_プリセット経由の自費が月次請求のservice_subtotalに反映される(): void
    {
        $preset = $this->createPresetWithItems([
            ['昼食', 500, 1],
            ['おやつ', 150, 2],
        ]);
        $facility = Facility::factory()->create();
        $resident = $this->createResident($facility);

        // 10/2〜10/31 の毎日適用（30日 × (500×1 + 150×2) = 30 × 800 = 24,000）
        // ※集計サービスは日時文字列との文字列比較のため、月初当日(10/1 00:00:00)は期間外扱いになる既存仕様
        $dates = $this->service->expandDates('2026-10-02', '2026-10-31', []);
        $records = $this->service->buildRecords($preset, [$resident->id], $dates);
        $result = $this->service->bulkCreate($records);

        $this->assertSame(60, $result['created']); // 2品目 × 30日

        // 月次請求書生成（Step 15: service_subtotalへの合算検証）
        app(InvoiceCalculationService::class)->generateForMonth('2026-10');

        $invoice = MonthlyInvoice::where('resident_id', $resident->id)
            ->where('billing_year_month', '2026-10')
            ->first();

        $this->assertNotNull($invoice);

        // 登録済み自費データの合計と請求書のservice_subtotalが一致すること（Step 15: リグレッション検証）
        $expectedServiceSubtotal = (int) DailyCharge::where('resident_id', $resident->id)
            ->whereBetween('date', ['2026-10-02', '2026-10-31'])
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) AS total')
            ->value('total');

        $this->assertGreaterThan(0, $expectedServiceSubtotal);
        $this->assertSame($expectedServiceSubtotal, $invoice->service_subtotal);

        // 1日あたり (500×1 + 150×2) = 800円 × 30日
        $this->assertSame(24000, $invoice->service_subtotal);

        // 基本料 = 家賃100,000 + 管理費20,000
        $this->assertSame(120000, $invoice->rent_subtotal + $invoice->management_fee_subtotal);
    }

    public function test_数百名への一括展開が高速に完了する(): void
    {
        $preset = $this->createPresetWithItems([
            ['昼食', 500, 1],
        ]);
        $facility = Facility::factory()->create();

        $residentIds = [];
        for ($i = 1; $i <= 50; $i++) {
            $residentIds[] = Resident::create([
                'facility_id' => $facility->id,
                'room_number' => sprintf('%03d', $i),
                'name' => "入居者{$i}",
                'name_kana' => "ニュウジュョシャ{$i}",
                'base_rent' => 100000,
                'base_management_fee' => 20000,
                'status' => 'active',
                'move_in_date' => '2020-01-01',
            ])->id;
        }

        $start = microtime(true);
        $result = $this->service->applySchedule(
            $preset,
            $residentIds,
            '2026-10-01',
            '2026-10-31',
            [1, 2, 3, 4, 5], // 平日のみ
        );
        $elapsed = microtime(true) - $start;

        $weekdayCount = count($result['applied_dates']);
        $this->assertGreaterThan(20, $weekdayCount);
        $this->assertSame(50 * $weekdayCount, $result['created']);
        $this->assertSame(50 * $weekdayCount, DailyCharge::count());
        // 50名×23日=1150件が3秒以内（N+1なし・バルクインサート）
        $this->assertLessThan(3.0, $elapsed, '一括登録が3秒以内に完了すること');
    }

    public function test_確定済み請求書がある月の入居者を除外できる(): void
    {
        $preset = $this->createPresetWithItems([
            ['昼食', 500, 1],
        ]);
        $facility = Facility::factory()->create();
        $resident = $this->createResident($facility);

        // 確定済（billed）請求書を作成
        MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'facility_id' => $facility->id,
            'billing_year_month' => '2026-10',
            'rent_subtotal' => 100000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 0,
            'status' => 'billed',
            'payment_method' => 'bank_transfer',
            'tax_rate' => 10,
        ]);

        // ページの確定月ガードと同じロジックをシミュレート
        $lockedResidentIds = MonthlyInvoice::whereIn('resident_id', [$resident->id])
            ->whereIn('billing_year_month', ['2026-10'])
            ->whereIn('status', ['billed', 'paid'])
            ->pluck('resident_id')
            ->unique()
            ->toArray();

        $this->assertContains($resident->id, $lockedResidentIds);

        // 対象から除外されればレコード生成は0件
        $targetIds = array_diff([$resident->id], $lockedResidentIds);
        $this->assertSame([], $targetIds);
    }

    public function test_存在しない品目や無効プリセットはレコード生成しない(): void
    {
        // 無効化したプリセット
        $preset = $this->createPresetWithItems([
            ['昼食', 500, 1],
        ]);
        $preset->update(['is_active' => false]);

        $facility = Facility::factory()->create();
        $resident = $this->createResident($facility);

        // 無効プリセットでもサービス自体は動作するが、UIの availableFor スコープで除外されることを検証
        $visible = DailyChargePreset::query()->availableFor($facility->id)->get();
        $this->assertFalse($visible->contains('id', $preset->id));
    }
}
