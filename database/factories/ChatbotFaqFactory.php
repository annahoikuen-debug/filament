<?php

namespace Database\Factories;

use App\Models\ChatbotFaq;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatbotFaqFactory extends Factory
{
    protected $model = ChatbotFaq::class;

    public function definition(): array
    {
        return [
            'question' => fake()->sentence(),
            'keywords' => ['テスト'],
            'answer' => fake()->paragraph(),
            'category' => '一般',
            'is_active' => true,
            'sort_order' => 0,
            'facility_id' => null,
        ];
    }
}
