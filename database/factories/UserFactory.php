<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_admin' => true,
            'role' => 'corporate_admin',
        ];
    }

    public function unadmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => false,
        ]);
    }

    public function corporateAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
            'role' => 'corporate_admin',
            'facility_id' => null,
        ]);
    }

    public function facilityAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
            'role' => 'facility_admin',
        ]);
    }
}
