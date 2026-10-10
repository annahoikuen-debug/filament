<?php

namespace Tests\Unit;

use App\Models\ChatLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatLogPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_save_public_log_with_visitor_id(): void
    {
        $visitorId = fake()->uuid();

        $log = ChatLog::create([
            'session_id' => 'sess-public-1',
            'channel' => 'public',
            'visitor_id' => $visitorId,
            'user_message' => '料金を教えてください',
            'intent' => 'pricing',
            'bot_reply' => '料金はFAQをご確認ください。',
            'faq_matched' => true,
        ]);

        $this->assertDatabaseHas('chat_logs', [
            'id' => $log->id,
            'channel' => 'public',
            'visitor_id' => $visitorId,
        ]);
    }

    public function test_scope_channel_separates_public_and_internal(): void
    {
        ChatLog::create([
            'session_id' => 'sess-a',
            'channel' => 'public',
            'intent' => 'pricing',
            'user_message' => 'public msg',
            'bot_reply' => 'ok',
        ]);
        ChatLog::create([
            'session_id' => 'sess-b',
            'channel' => 'internal',
            'intent' => 'invoice_amount',
            'user_message' => 'internal msg',
            'bot_reply' => 'ok',
        ]);

        $this->assertSame(1, ChatLog::query()->channel('public')->count());
        $this->assertSame(1, ChatLog::query()->channel('internal')->count());
        $this->assertSame('public msg', ChatLog::query()->channel('public')->first()->user_message);
    }

    public function test_existing_logs_without_channel_default_to_internal(): void
    {
        $log = ChatLog::create([
            'session_id' => 'sess-legacy',
            'intent' => 'faq',
            'user_message' => '古いログ',
            'bot_reply' => 'ok',
        ]);

        $this->assertSame('internal', $log->fresh()->channel);
    }
}
