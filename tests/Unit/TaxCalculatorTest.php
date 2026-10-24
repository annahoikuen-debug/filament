<?php

namespace Tests\Unit;

use App\Services\Invoice\TaxCalculator;
use Tests\TestCase;

class TaxCalculatorTest extends TestCase
{
    /** @test */
    public function it_calculates_consumption_tax_with_floor_rounding()
    {
        $calculator = new TaxCalculator(10.0, 8.0); // 10% standard, 8% reduced

        // Test case 1: Standard tax calculation with fraction
        // 1000 yen at 10% should be exactly 100 yen
        $result = $calculator->calculate(1000, 0, 0);
        $this->assertEquals(100, $result['standard_tax']);
        $this->assertEquals(0, $result['reduced_tax']);
        $this->assertEquals(100, $result['total_tax']);

        // Test case 2: Standard tax calculation requiring rounding down
        // 1001 yen at 10% should be 100.1 yen -> rounded down to 100 yen
        $result = $calculator->calculate(1001, 0, 0);
        $this->assertEquals(100, $result['standard_tax']); // 1001 * 0.1 = 100.1 -> floor to 100
        $this->assertEquals(0, $result['reduced_tax']);
        $this->assertEquals(100, $result['total_tax']);

        // Test case 3: Reduced tax calculation requiring rounding down
        // 1001 yen at 8% should be 80.08 yen -> rounded down to 80 yen
        $result = $calculator->calculate(0, 1001, 0);
        $this->assertEquals(0, $result['standard_tax']);
        $this->assertEquals(80, $result['reduced_tax']); // 1001 * 0.08 = 80.08 -> floor to 80
        $this->assertEquals(80, $result['total_tax']);

        // Test case 4: Both taxes with rounding down
        // Standard: 1001 yen at 10% = 100.1 -> 100 yen
        // Reduced: 1001 yen at 8% = 80.08 -> 80 yen
        // Total tax: 180 yen
        $result = $calculator->calculate(1001, 1001, 0);
        $this->assertEquals(100, $result['standard_tax']);
        $this->assertEquals(80, $result['reduced_tax']);
        $this->assertEquals(180, $result['total_tax']);

        // Test case 5: Edge case with .5 (should round down, not up)
        // 5 yen at 10% = 0.5 yen -> should round down to 0 yen (not up to 1)
        $result = $calculator->calculate(5, 0, 0);
        $this->assertEquals(0, $result['standard_tax']); // 5 * 0.1 = 0.5 -> floor to 0
        $this->assertEquals(0, $result['reduced_tax']);
        $this->assertEquals(0, $result['total_tax']);

        // Test case 6: Another edge case
        // 15 yen at 10% = 1.5 yen -> should round down to 1 yen
        $result = $calculator->calculate(15, 0, 0);
        $this->assertEquals(1, $result['standard_tax']); // 15 * 0.1 = 1.5 -> floor to 1
        $this->assertEquals(0, $result['reduced_tax']);
        $this->assertEquals(1, $result['total_tax']);
    }

    /** @test */
    public function it_returns_correct_tax_breakdown()
    {
        $calculator = new TaxCalculator(10.0, 8.0);

        $result = $calculator->calculate(1000, 500, 0);

        // Check standard tax breakdown
        $this->assertEquals(1000, $result['tax_breakdown']['standard']['taxable_amount']);
        $this->assertEquals(10.0, $result['tax_breakdown']['standard']['rate']);
        $this->assertEquals(100, $result['tax_breakdown']['standard']['tax_amount']); // 1000 * 0.1 = 100

        // Check reduced tax breakdown
        $this->assertEquals(500, $result['tax_breakdown']['reduced']['taxable_amount']);
        $this->assertEquals(8.0, $result['tax_breakdown']['reduced']['rate']);
        $this->assertEquals(40, $result['tax_breakdown']['reduced']['tax_amount']); // 500 * 0.08 = 40

        // Check non-taxable breakdown
        $this->assertEquals(0, $result['tax_breakdown']['non_taxable']['amount']);
    }

    /** @test */
    public function it_handles_zero_amounts_correctly()
    {
        $calculator = new TaxCalculator(10.0, 8.0);

        $result = $calculator->calculate(0, 0, 0);

        $this->assertEquals(0, $result['standard_tax']);
        $this->assertEquals(0, $result['reduced_tax']);
        $this->assertEquals(0, $result['total_tax']);

        $this->assertEquals(0, $result['tax_breakdown']['standard']['taxable_amount']);
        $this->assertEquals(0, $result['tax_breakdown']['standard']['tax_amount']);
        $this->assertEquals(0, $result['tax_breakdown']['reduced']['taxable_amount']);
        $this->assertEquals(0, $result['tax_breakdown']['reduced']['tax_amount']);
        $this->assertEquals(0, $result['tax_breakdown']['non_taxable']['amount']);
    }
}
