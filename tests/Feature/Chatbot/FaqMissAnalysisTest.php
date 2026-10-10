<?php

use App\Models\ChatLog;
use App\Models\ChatbotFaq;
use App\Models\Facility;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\DTO\ChatRequest;
use Illuminate\Support\Facades\Schedule;

beforeEach(function () {
    $this->facility = Facility::factory()->create();
    $this->user = User::factory()->facilityAdmin()->create(['facility_id' => $this->facility->id]);
    $this->service = app(ChatbotService::class);
});

test('FAQ不一致時に faq_matched=false が chat_logs に記録されること', function () {
    $this->service->handle(
        new ChatRequest('未定義の質問です'),
        $this->user
    );

    $log = ChatLog::latest('id')->first();
    expect($log->intent)->toBe('faq')
        ->and($log->faq_matched)->toBeFalse();
});

test('FAQ一致時に faq_matched=true が chat_logs に記録されること', function () {
    ChatbotFaq::create([
        'question' => '面会時間を教えてください',
        'keywords' => ['面会', '時間'],
        'answer' => '面会時間は10:00〜18:00です。',
        'category' => '施設利用',
    ]);

    $this->service->handle(
        new ChatRequest('面会時間はいつですか？'),
        $this->user
    );

    $log = ChatLog::latest('id')->first();
    expect($log->intent)->toBe('faq')
        ->and($log->faq_matched)->toBeTrue();
});

test('集計コマンド chatbot:analyze-faq-misses がランキングを出力すること', function () {
    // 未回答ログを生成
    ChatLog::create([
        'user_id' => $this->user->id,
        'session_id' => 'session-1',
        'user_message' => 'Wi-Fiのパスワードは何ですか',
        'intent' => 'faq',
        'bot_reply' => '見つかりませんでした',
        'faq_matched' => false,
        'facility_id' => $this->facility->id,
        'created_at' => now(),
    ]);
    ChatLog::create([
        'user_id' => $this->user->id,
        'session_id' => 'session-2',
        'user_message' => 'Wi-Fiのパスワードは何ですか',
        'intent' => 'faq',
        'bot_reply' => '見つかりませんでした',
        'faq_matched' => false,
        'facility_id' => $this->facility->id,
        'created_at' => now(),
    ]);

    $this->artisan('chatbot:analyze-faq-misses', ['--days' => 7])
        ->expectsOutputToContain('FAQ未回答質問分析')
        ->expectsOutputToContain('Wi-Fiのパスワードは何ですか')
        ->assertSuccessful();
});

test('--days オプションで対象期間が絞られること', function () {
    ChatLog::query()->delete();

    // 40日前の未回答ログ
    $log = new ChatLog([
        'user_id' => $this->user->id,
        'session_id' => 'session-old',
        'user_message' => '古い質問',
        'intent' => 'faq',
        'bot_reply' => '見つかりませんでした',
        'faq_matched' => false,
        'facility_id' => $this->facility->id,
    ]);
    $log->created_at = now()->subDays(40);
    $log->updated_at = now()->subDays(40);
    $log->save();

    $this->artisan('chatbot:analyze-faq-misses', ['--days' => 10])
        ->expectsOutputToContain('未回答質問の総件数: 0件')
        ->assertSuccessful();
});

test('スケジューラに chatbot:analyze-faq-misses が登録されていること', function () {
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

    $hasCommand = collect($schedule->events())->contains(function ($event) {
        return str_contains($event->command ?? '', 'chatbot:analyze-faq-misses');
    });

    expect($hasCommand)->toBeTrue();
});

test('類似の未回答質問が自動クラスタリンググループとして出力されること', function () {
    ChatLog::query()->delete();

    // 類似する表現の未回答質問を複数登録
    ChatLog::create([
        'user_id' => $this->user->id,
        'session_id' => 'session-c1',
        'user_message' => 'Wi-Fiの接続方法を教えて',
        'intent' => 'faq',
        'bot_reply' => '見つかりませんでした',
        'faq_matched' => false,
        'facility_id' => $this->facility->id,
    ]);

    ChatLog::create([
        'user_id' => $this->user->id,
        'session_id' => 'session-c2',
        'user_message' => 'Wi-Fiの接続方法が知りたい',
        'intent' => 'faq',
        'bot_reply' => '見つかりませんでした',
        'faq_matched' => false,
        'facility_id' => $this->facility->id,
    ]);

    $this->artisan('chatbot:analyze-faq-misses', ['--days' => 7])
        ->expectsOutputToContain('類似未回答質問の自動グループ（クラスタリング）')
        ->expectsOutputToContain('Wi-Fiの接続方法')
        ->assertSuccessful();
});

