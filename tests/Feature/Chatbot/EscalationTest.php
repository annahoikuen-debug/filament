<?php

use App\Models\ChatEscalation;
use App\Models\ChatLog;
use App\Models\Facility;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\DTO\ChatRequest;
use Carbon\Carbon;

beforeEach(function () {
    $this->facility = Facility::factory()->create();
    $this->user = User::factory()->facilityAdmin()->create(['facility_id' => $this->facility->id]);
    $this->service = app(ChatbotService::class);
});

test('「担当者」「サポート」等のメッセージでエスカレーション意図が判定されること', function () {
    $response = $this->service->handle(
        new ChatRequest('担当者に代わってください'),
        $this->user
    );

    expect($response->intent)->toBe('escalate')
        ->and($response->reply)->toContain('担当者に引き継ぎました');
});

test('chat_escalations テーブルに会話コンテキストが保存されること', function () {
    $sessionId = 'session-test-1234';

    // 過去ログを作成
    ChatLog::create([
        'user_id' => $this->user->id,
        'session_id' => $sessionId,
        'user_message' => '請求書の再発行がしたいです',
        'intent' => 'faq',
        'bot_reply' => '回答が見つかりませんでした',
        'facility_id' => $this->facility->id,
    ]);

    $response = $this->service->handle(
        new ChatRequest('担当者に相談したい', $sessionId),
        $this->user
    );

    expect($response->intent)->toBe('escalate');

    $escalation = ChatEscalation::where('session_id', $sessionId)->first();
    expect($escalation)->not()->toBeNull()
        ->and($escalation->user_id)->toBe($this->user->id)
        ->and($escalation->facility_id)->toBe($this->facility->id)
        ->and($escalation->status)->toBe('pending')
        ->and($escalation->summary)->toContain('請求書の再発行がしたいです')
        ->and($escalation->summary)->toContain('担当者に相談したい');
});

test('facility_id が正しく記録されること', function () {
    $response = $this->service->handle(
        new ChatRequest('ヘルプをお願いします'),
        $this->user
    );

    $escalation = ChatEscalation::where('user_id', $this->user->id)->latest('id')->first();
    expect($escalation->facility_id)->toBe($this->facility->id);
});

test('1日5件を超えるエスカレーションはレート制限されること', function () {
    // 5件作成
    for ($i = 0; $i < 5; $i++) {
        ChatEscalation::create([
            'user_id' => $this->user->id,
            'session_id' => "session-{$i}",
            'summary' => "要約{$i}",
            'facility_id' => $this->facility->id,
            'status' => 'pending',
        ]);
    }

    $response = $this->service->handle(
        new ChatRequest('対応してください'),
        $this->user
    );

    expect($response->intent)->toBe('escalate')
        ->and($response->reply)->toContain('上限（5件）に達しました');
});

test('未認証ユーザーはエスカレーションAPIにアクセスできないこと', function () {
    $this->postJson(route('chatbot.message'), [
        'message' => '担当者に引き継いでください',
    ])->assertUnauthorized();
});
