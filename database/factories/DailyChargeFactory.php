<?php

namespace Database\Factories;

use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\Facility;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyChargeFactory extends Factory
{
    protected $model = DailyCharge::class;

    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'resident_id' => Resident::factory(),
            'charge_item_id' => ChargeItem::factory(),
            'date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_price' => fake()->numberBetween(100, 10000),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
