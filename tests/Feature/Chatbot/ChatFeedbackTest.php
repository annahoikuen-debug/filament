<?php

use App\Models\ChatLog;
use App\Models\Facility;
use App\Models\User;

beforeEach(function () {
    $this->facilityA = Facility::factory()->create();
    $this->facilityB = Facility::factory()->create();

    $this->userA = User::factory()->facilityAdmin()->create([
        'facility_id' => $this->facilityA->id,
    ]);

    $this->userB = User::factory()->facilityAdmin()->create([
        'facility_id' => $this->facilityB->id,
    ]);

    $this->corporateAdmin = User::factory()->corporateAdmin()->create();

    $this->logA = ChatLog::create([
        'user_id' => $this->userA->id,
        'session_id' => 'session-a',
        'user_message' => 'テスト質問',
        'intent' => 'faq',
        'bot_reply' => 'テスト回答',
        'facility_id' => $this->facilityA->id,
    ]);
});

test('正常なフィードバック (helpful) で chat_logs が更新されること', function () {
    $response = $this->actingAs($this->userA)->postJson('/api/chatbot/feedback', [
        'chat_log_id' => $this->logA->id,
        'feedback' => 'helpful',
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'feedback' => 'helpful',
        ]);

    $this->assertDatabaseHas('chat_logs', [
        'id' => $this->logA->id,
        'feedback' => 'helpful',
    ]);

    expect($this->logA->fresh()->feedback_at)->not->toBeNull();
});

test('unhelpful フィードバックでも正常に記録されること', function () {
    $response = $this->actingAs($this->userA)->postJson('/api/chatbot/feedback', [
        'chat_log_id' => $this->logA->id,
        'feedback' => 'unhelpful',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('chat_logs', [
        'id' => $this->logA->id,
        'feedback' => 'unhelpful',
    ]);
});

test('未認証ユーザーは401になること', function () {
    $response = $this->postJson('/api/chatbot/feedback', [
        'chat_log_id' => $this->logA->id,
        'feedback' => 'helpful',
    ]);

    $response->assertStatus(401);
});

test('is_admin=false のユーザーは403になること', function () {
    $normalUser = User::factory()->unadmin()->create();

    $response = $this->actingAs($normalUser)->postJson('/api/chatbot/feedback', [
        'chat_log_id' => $this->logA->id,
        'feedback' => 'helpful',
    ]);

    $response->assertStatus(403);
});

test('他施設ユーザーからのフィードバック更新は404で拒絶されること', function () {
    $response = $this->actingAs($this->userB)->postJson('/api/chatbot/feedback', [
        'chat_log_id' => $this->logA->id,
        'feedback' => 'helpful',
    ]);

    $response->assertStatus(404);

    expect($this->logA->fresh()->feedback)->toBeNull();
});

test('corporateAdmin は全施設のログにフィードバックできること', function () {
    $response = $this->actingAs($this->corporateAdmin)->postJson('/api/chatbot/feedback', [
        'chat_log_id' => $this->logA->id,
        'feedback' => 'helpful',
    ]);

    $response->assertOk();
    expect($this->logA->fresh()->feedback)->toBe('helpful');
});

test('無効な feedback 値は422になること', function () {
    $response = $this->actingAs($this->userA)->postJson('/api/chatbot/feedback', [
        'chat_log_id' => $this->logA->id,
        'feedback' => 'invalid_value',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['feedback']);
});
