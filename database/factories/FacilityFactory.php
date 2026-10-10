<?php

namespace Database\Factories;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class FacilityFactory extends Factory
{
    protected $model = Facility::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().'施設',
            'operator' => fake()->company(),
            'postal_code' => fake()->postcode(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'fax' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'invoice_registration_number' => 'T'.fake()->numerify('#############'),
            'bank' => [
                'name' => fake()->word().'銀行',
                'branch_name' => fake()->word().'支店',
                'account_type' => '普通',
                'account_number' => fake()->numerify('#######'),
                'account_holder' => fake()->name(),
            ],
            'billing' => [
                'direct_debit_day' => 27,
                'bank_transfer_due_days' => 30,
            ],
            'is_active' => true,
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
