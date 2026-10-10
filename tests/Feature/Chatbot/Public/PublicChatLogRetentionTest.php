<?php

namespace Tests\Feature\Chatbot\Public;

use App\Models\ChatLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class PublicChatLogRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function makeLog(string $channel, int $daysAgo, array $extra = []): ChatLog
    {
        $log = ChatLog::create(array_merge([
            'channel' => $channel,
            'session_id' => 'sess-'.uniqid(),
            'intent' => 'faq',
            'user_message' => 'テストメッセージ',
            'bot_reply' => 'テスト返信',
            'visitor_id' => $channel === 'public' ? '00000000-0000-0000-0000-'.sprintf('%012d', $daysAgo) : null,
        ], $extra));

        // created_at は作成時に現在時刻で上書きされるため、日付は後から直接更新する
        $log->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();
        $log->refresh();

        return $log;
    }

    /**
     * 保持期間超過の公開チャネルログが削除対象になる
     */
    public function test_public_logs_past_retention_period_are_deleted(): void
    {
        config(['chatbot.public.retention_days' => 30]);

        $old = $this->makeLog('public', 31);
        $recent = $this->makeLog('public', 10);

        // 公開チャネルは30日保持
        $deleted = ChatLog::channel('public')
            ->where('created_at', '<', now()->subDays(30))
            ->delete();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('chat_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('chat_logs', ['id' => $recent->id]);
    }

    /**
     * 内部チャネルログは公開チャネルの保持期間では削除されない（90日設定）
     */
    public function test_internal_logs_are_not_deleted_by_public_retention(): void
    {
        config(['chatbot.retention_days' => 90]);
        config(['chatbot.public.retention_days' => 30]);

        $internalOld = $this->makeLog('internal', 60);
        $publicOld = $this->makeLog('public', 60);

        // 公開チャネル保持期間（30日）での削除
        ChatLog::channel('public')
            ->where('created_at', '<', now()->subDays(30))
            ->delete();

        // 内部60日のログは残る（90日以内）
        $this->assertDatabaseHas('chat_logs', ['id' => $internalOld->id]);
        $this->assertDatabaseMissing('chat_logs', ['id' => $publicOld->id]);

        // 内部チャネルは90日保持で削除
        $deleted = ChatLog::channel('internal')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();
        $this->assertSame(0, $deleted);
        $this->assertDatabaseHas('chat_logs', ['id' => $internalOld->id]);
    }

    /**
     * スケジューラにチャネル別クリーンアップが登録されている
     */
    public function test_retention_cleanup_is_scheduled(): void
    {
        $events = Schedule::events($this->app);
        $found = collect($events)->contains(function ($event) {
            return str_contains($event->description ?? '', '保持期間超過分を削除');
        });

        $this->assertTrue($found, '保持期間クリーンアップのスケジュールが登録されていること');
    }
}
