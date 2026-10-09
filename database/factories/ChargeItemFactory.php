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
        $names = ['おむつ', '理美容', '立替', '介護用品', 'リハビリ'];
        $displayNames = ['おむつ代', '理美容代', '立替金', '介護用品費', 'リハビリ費'];
        
        return [
            'name' => fake()->randomElement($names),
            'display_name' => fake()->randomElement($displayNames),
            'description' => fake()->optional(0.5)->sentence(),
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