<?php

use App\Models\ChatLog;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\DTO\ChatRequest;

beforeEach(function () {
    $this->facility = Facility::factory()->create();
    $this->corporateAdmin = User::factory()->corporateAdmin()->create();
    $this->service = app(ChatbotService::class);
});

test('会話がログに記録されること', function () {
    $this->service->handle(
        new ChatRequest('テストメッセージ', 'session-1'),
        $this->corporateAdmin
    );

    $log = ChatLog::latest()->first();

    expect($log)->not->toBeNull()
        ->and($log->user_message)->toBe('テストメッセージ')
        ->and($log->session_id)->toBe('session-1')
        ->and($log->intent)->toBeString();
});

test('保持期間超過ログが削除されること', function () {
    ChatLog::factory()->create([
        'created_at' => now()->subDays(91),
    ]);
    ChatLog::factory()->create([
        'created_at' => now()->subDays(10),
    ]);

    $retentionDays = (int) config('chatbot.retention_days', 90);
    $deleted = ChatLog::where('created_at', '<', now()->subDays($retentionDays))->delete();

    expect($deleted)->toBe(1)
        ->and(ChatLog::count())->toBe(1);
});

test('mask_names=true で氏名がマスキングされること', function () {
    config(['chatbot.mask_names' => true]);

    $resident = Resident::factory()->create([
        'facility_id' => $this->facility->id,
        'name' => '山田太郎',
    ]);

    $this->service->handle(
        new ChatRequest('山田太郎の情報を教えてください'),
        $this->corporateAdmin
    );

    $log = ChatLog::latest()->first();

    expect($log->user_message)->not->toContain('山田太郎')
        ->and($log->user_message)->toContain('***');
});

test('mask_names=false で氏名が保持されること', function () {
    config(['chatbot.mask_names' => false]);

    Resident::factory()->create([
        'facility_id' => $this->facility->id,
        'name' => '山田太郎',
    ]);

    $this->service->handle(
        new ChatRequest('山田太郎の情報を教えてください'),
        $this->corporateAdmin
    );

    $log = ChatLog::latest()->first();

    expect($log->user_message)->toContain('山田太郎');
});
