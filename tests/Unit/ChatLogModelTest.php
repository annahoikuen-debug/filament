<?php

namespace Tests\Unit;

use App\Models\ChatLog;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatLogModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_log_belongs_to_user_and_facility(): void
    {
        $facility = Facility::factory()->create();
        $user = User::factory()->facilityAdmin()->create(['facility_id' => $facility->id]);

        $log = ChatLog::create([
            'user_id' => $user->id,
            'session_id' => 'session-abc',
            'user_message' => '家賃はいくらですか？',
            'intent' => 'invoice_amount',
            'bot_reply' => '今月の請求額は70,000円です。',
            'facility_id' => $facility->id,
        ]);

        $this->assertSame($user->id, $log->user->id);
        $this->assertSame($facility->id, $log->facility->id);
        $this->assertSame('session-abc', $log->session_id);
        $this->assertSame('invoice_amount', $log->intent);
    }

    public function test_for_facility_scope_filters_logs(): void
    {
        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();
        $user = User::factory()->facilityAdmin()->create(['facility_id' => $facilityA->id]);

        ChatLog::create([
            'user_id' => $user->id,
            'session_id' => 's1',
            'user_message' => 'A施設の質問',
            'intent' => 'faq',
            'bot_reply' => '回答',
            'facility_id' => $facilityA->id,
        ]);
        ChatLog::create([
            'user_id' => $user->id,
            'session_id' => 's2',
            'user_message' => 'B施設の質問',
            'intent' => 'faq',
            'bot_reply' => '回答',
            'facility_id' => $facilityB->id,
        ]);

        $logsA = ChatLog::forFacility($facilityA->id)->get();
        $this->assertCount(1, $logsA);
        $this->assertSame('A施設の質問', $logsA->first()->user_message);
    }

    public function test_created_at_is_cast_to_datetime(): void
    {
        $facility = Facility::factory()->create();
        $user = User::factory()->facilityAdmin()->create(['facility_id' => $facility->id]);

        $log = ChatLog::create([
            'user_id' => $user->id,
            'session_id' => 's3',
            'user_message' => 'テスト',
            'intent' => 'unknown',
            'bot_reply' => '回答',
            'facility_id' => $facility->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $log->created_at);
    }
}
