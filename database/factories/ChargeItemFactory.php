<?php

namespace Database\Factories;

use App\Models\ChargeItem;
use App\Models\ChargeItemPrice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChargeItemFactory extends Factory
{
    protected $model = ChargeItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['おむつ', '理美容', '立替', '介護用品', 'リハビリ']),
            'default_price' => fake()->numberBetween(100, 5000),
            'is_active' => true,
            'tax_type' => \App\Enums\TaxType::Standard,
        ];
    }

    public function withPriceHistory(int $count = 3): static
    {
        return $this->has(
            ChargeItemPrice::factory()->count($count)->sequence(
                fn (int $index) => [
                    'effective_from' => Carbon::today()->subDays($index * 10),
                    'effective_until' => $index === 0 ? null : Carbon::today()->subDays(($index - 1) * 10),
                ]
            ),
            'priceHistory'
        );
    }
}