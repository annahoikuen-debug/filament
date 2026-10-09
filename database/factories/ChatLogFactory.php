<?php

namespace Database\Factories;

use App\Models\ChatLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatLogFactory extends Factory
{
    protected $model = ChatLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => fake()->uuid(),
            'user_message' => fake()->sentence(),
            'intent' => 'faq',
            'bot_reply' => fake()->sentence(),
            'facility_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
