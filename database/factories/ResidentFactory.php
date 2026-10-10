<?php

namespace Database\Factories;

use App\Enums\ResidentStatus;
use App\Models\Facility;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResidentFactory extends Factory
{
    protected $model = Resident::class;

    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'room_number' => fake()->numerify('###'),
            'name' => fake()->name(),
            'name_kana' => fake()->lastName().' '.fake()->firstName(), // Use basic name methods since kana not available
            'base_rent' => fake()->numberBetween(30000, 100000),
            'base_management_fee' => fake()->numberBetween(10000, 30000),
            'status' => ResidentStatus::Active,
            'move_in_date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'move_out_date' => null,
        ];
    }
}
