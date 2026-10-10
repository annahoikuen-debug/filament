<?php

namespace Database\Factories;

use App\Models\ChargeItem;
use App\Models\ChargeItemPrice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChargeItemPriceFactory extends Factory
{
    protected $model = ChargeItemPrice::class;

    public function definition(): array
    {
        $effectiveFrom = Carbon::today()->subDays(fake()->numberBetween(0, 30));
        $hasEndDate = fake()->boolean(30); // 30% chance of having an end date

        return [
            'charge_item_id' => ChargeItem::factory(),
            'price' => fake()->numberBetween(500, 10000),
            'effective_from' => $effectiveFrom,
            'effective_until' => $hasEndDate
                ? $effectiveFrom->copy()->addDays(fake()->numberBetween(1, 30))
                : null,
        ];
    }
}
