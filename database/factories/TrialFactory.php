<?php

namespace Database\Factories;

use App\Models\Trial;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrialFactory extends Factory
{
    protected $model = Trial::class;

    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'facility_type' => fake()->randomElement(['有料老人ホーム', '特別養護老人ホーム', '介護老人保健施設', 'グループホーム']),
            'resident_capacity' => fake()->numberBetween(10, 100),
            'status' => 'pending',
            'trial_started_at' => Carbon::today(),
            'trial_ends_at' => Carbon::today()->addDays(30),
            'trial_config' => [],
            'score' => fake()->numberBetween(0, 100),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
        ]);
    }
}
