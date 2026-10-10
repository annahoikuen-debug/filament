<?php

namespace Tests\Unit;

use App\Models\Resident;
use App\Services\Invoice\ProrationCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProrationCalculatorEdgeTest extends TestCase
{
    use RefreshDatabase;

    private ProrationCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ProrationCalculator;
    }

    public function test_入居が月末より後なら0日(): void
    {
        $resident = Resident::factory()->create(['move_in_date' => '2026-04-15']);

        $this->assertSame(0, $this->calculator->calculateLivingDays($resident, 2026, 3));
    }

    public function test_退去が月初より前なら0日(): void
    {
        $resident = Resident::factory()->create(['move_out_date' => '2026-02-28']);

        $this->assertSame(0, $this->calculator->calculateLivingDays($resident, 2026, 3));
    }

    public function test_livingDaysが0の場合は日割り金額0(): void
    {
        $this->assertSame(0, $this->calculator->calculateProratedAmount(50000, 2026, 3, 0));
    }

    public function test_livingDaysが0の場合は固定費0(): void
    {
        $resident = Resident::factory()->create([
            'base_rent' => 50000,
            'base_management_fee' => 20000,
            'move_in_date' => '2026-04-15',
        ]);

        $this->assertSame(['rent' => 0, 'management_fee' => 0], $this->calculator->calculateFixedCosts($resident, 2026, 3));
    }

    public function test_入居日のみで日割り固定費が計算される(): void
    {
        // 3月 (31日) に 3/16 入居 → 16日
        $resident = Resident::factory()->create([
            'base_rent' => 62000,
            'base_management_fee' => 31000,
            'move_in_date' => '2026-03-16',
        ]);

        $result = $this->calculator->calculateFixedCosts($resident, 2026, 3);

        // 62000 / 31 * 16 = 32000, 31000 / 31 * 16 = 16000
        $this->assertSame(32000, $result['rent']);
        $this->assertSame(16000, $result['management_fee']);
    }
}
