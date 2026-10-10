<?php

namespace Tests\Unit\Chatbot;

use App\Models\ChatbotFaq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Facades\CauserResolver;
use Tests\TestCase;

class ChatbotFaqPublicScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_public_returns_only_public_faqs(): void
    {
        ChatbotFaq::factory()->create(['question' => '公開FAQ', 'is_public' => true]);
        ChatbotFaq::factory()->create(['question' => '内部FAQ', 'is_public' => false]);

        $results = ChatbotFaq::query()->public()->get();

        $this->assertCount(1, $results);
        $this->assertSame('公開FAQ', $results->first()->question);
    }

    public function test_non_public_faqs_are_excluded_from_public_scope(): void
    {
        ChatbotFaq::factory()->create(['question' => '内部専用FAQ', 'is_public' => false]);
        ChatbotFaq::factory()->create(['question' => '内部専用FAQ2', 'is_public' => false]);

        $results = ChatbotFaq::query()->public()->get();

        $this->assertCount(0, $results);
    }

    public function test_inactive_public_faq_is_excluded_when_combined_with_active_scope(): void
    {
        ChatbotFaq::factory()->create(['question' => '無効な公開FAQ', 'is_public' => true, 'is_active' => false]);
        ChatbotFaq::factory()->create(['question' => '有効な公開FAQ', 'is_public' => true, 'is_active' => true]);

        $results = ChatbotFaq::query()->public()->active()->get();

        $this->assertCount(1, $results);
        $this->assertSame('有効な公開FAQ', $results->first()->question);
    }

    public function test_is_public_change_is_logged_in_activity_log(): void
    {
        Event::fake([\Spatie\Activitylog\Contracts\Activity::class]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $faq = ChatbotFaq::factory()->create(['is_public' => false]);
        $faq->update(['is_public' => true]);

        Event::dispatched(\Spatie\Activitylog\ModelActivityEvent::class, function ($event) {
            return str_contains($event->model::class, 'ChatbotFaq');
        }) || true;

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => ChatbotFaq::class,
            'subject_id' => $faq->id,
        ]);

        $activity = \Spatie\Activitylog\Models\Activity::query()
            ->where('subject_type', ChatbotFaq::class)
            ->where('subject_id', $faq->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals(true, $activity->changes['attributes']['is_public'] ?? null);
    }
}
