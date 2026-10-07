<?php

namespace Database\Factories;

use App\Models\ChargeItem;
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
        ];
    }
}