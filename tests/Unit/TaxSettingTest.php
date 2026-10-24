<?php

namespace Tests\Unit;

use App\Models\TaxSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaxSettingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_prevents_overlapping_active_tax_settings()
    {
        // Create an active tax setting for January 1 to December 31, 2026
        TaxSetting::create([
            'standard_rate' => 10,
            'reduced_rate' => 8,
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-12-31',
            'is_active' => true,
        ]);

        // Attempt to create another active tax setting that overlaps (July 1, 2026 to June 30, 2027)
        $this->expectException(ValidationException::class);
        TaxSetting::create([
            'standard_rate' => 11,
            'reduced_rate' => 9,
            'effective_from' => '2026-07-01',
            'effective_until' => '2027-06-30',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_allows_non_overlapping_active_tax_settings()
    {
        // Create an active tax setting for January 1 to December 31, 2026
        TaxSetting::create([
            'standard_rate' => 10,
            'reduced_rate' => 8,
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-12-31',
            'is_active' => true,
        ]);

        // Create another active tax setting that starts after the first ends (January 1, 2027)
        $taxSetting = TaxSetting::create([
            'standard_rate' => 11,
            'reduced_rate' => 9,
            'effective_from' => '2027-01-01',
            'effective_until' => '2027-12-31',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('tax_settings', [
            'id' => $taxSetting->id,
            'standard_rate' => 11,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_allows_overlap_if_one_is_inactive()
    {
        // Create an active tax setting for January 1 to December 31, 2026
        TaxSetting::create([
            'standard_rate' => 10,
            'reduced_rate' => 8,
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-12-31',
            'is_active' => true,
        ]);

        // Create an inactive tax setting that overlaps (should be allowed)
        $taxSetting = TaxSetting::create([
            'standard_rate' => 11,
            'reduced_rate' => 9,
            'effective_from' => '2026-07-01',
            'effective_until' => '2027-06-30',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('tax_settings', [
            'id' => $taxSetting->id,
            'is_active' => false,
        ]);
    }

    /** @test */
    public function it_allows_updating_tax_setting_without_changing_dates()
    {
        $taxSetting = TaxSetting::create([
            'standard_rate' => 10,
            'reduced_rate' => 8,
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-12-31',
            'is_active' => true,
        ]);

        // Update the tax setting (change the rate) without changing dates
        $taxSetting->update([
            'standard_rate' => 12,
        ]);

        $this->assertDatabaseHas('tax_settings', [
            'id' => $taxSetting->id,
            'standard_rate' => 12,
        ]);
    }

    /** @test */
    public function it_allows_updating_tax_setting_to_an_active_state_if_no_overlap()
    {
        // Create an active tax setting for January 1 to June 30, 2026
        TaxSetting::create([
            'standard_rate' => 10,
            'reduced_rate' => 8,
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-06-30',
            'is_active' => true,
        ]);

        // Create an inactive tax setting for July 1 to December 31, 2026
        $taxSetting = TaxSetting::create([
            'standard_rate' => 11,
            'reduced_rate' => 9,
            'effective_from' => '2026-07-01',
            'effective_until' => '2026-12-31',
            'is_active' => false,
        ]);

        // Now activate the second tax setting (should be allowed because the first ends on June 30 and the second starts July 1)
        $taxSetting->update([
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('tax_settings', [
            'id' => $taxSetting->id,
            'is_active' => true,
        ]);
    }
}
