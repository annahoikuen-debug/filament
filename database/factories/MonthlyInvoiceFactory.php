<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\MonthlyInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonthlyInvoiceFactory extends Factory
{
    protected $model = MonthlyInvoice::class;

    public function definition(): array
    {
        return [
            'facility_id' => \App\Models\Facility::factory(),
            'resident_id' => \App\Models\Resident::factory(),
            'billing_year_month' => now()->format('Y-m'),
            'rent_subtotal' => fake()->numberBetween(30000, 100000),
            'management_fee_subtotal' => fake()->numberBetween(10000, 30000),
            'service_subtotal' => 0,
            'total_amount' => 0,
            'status' => InvoiceStatus::Billed,
            'paid_at' => null,
            'payment_method' => null,
            'receipt_number' => null,
        ];
    }
}