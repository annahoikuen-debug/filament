<?php

use App\Models\Facility;
use App\Models\User;

beforeEach(function () {
    $this->facility = Facility::factory()->create();
    $this->corporateAdmin = User::factory()->corporateAdmin()->create();
});

test('未認証アクセスは401を返すこと', function () {
    $response = $this->postJson('/api/chatbot/message', [
        'message' => 'テスト',
    ]);

    $response->assertStatus(401);
});

test('is_admin=false のユーザーは403を返すこと', function () {
    $user = User::factory()->unadmin()->create();

    $response = $this->actingAs($user)->postJson('/api/chatbot/message', [
        'message' => 'テスト',
    ]);

    $response->assertStatus(403);
});

test('正規のメッセージでJSON応答が返ること', function () {
    $response = $this->actingAs($this->corporateAdmin)->postJson('/api/chatbot/message', [
        'message' => '利用規約について教えてください',
        'session_id' => 'test-session',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['reply', 'intent', 'data', 'sources']);
});

test('空メッセージはバリデーションエラーになること', function () {
    $response = $this->actingAs($this->corporateAdmin)->postJson('/api/chatbot/message', [
        'message' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['message']);
});

test('長すぎるメッセージはバリデーションエラーになること', function () {
    $response = $this->actingAs($this->corporateAdmin)->postJson('/api/chatbot/message', [
        'message' => str_repeat('あ', 501),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['message']);
});

test('レート制限が動作すること', function () {
    $response = $this->actingAs($this->corporateAdmin)->postJson('/api/chatbot/message', [
        'message' => 'テストメッセージ',
    ]);
    $response->assertStatus(200);

    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($this->corporateAdmin)->postJson('/api/chatbot/message', [
            'message' => 'テストメッセージ'.$i,
        ]);
    }

    $response = $this->actingAs($this->corporateAdmin)->postJson('/api/chatbot/message', [
        'message' => '制限超過メッセージ',
    ]);

    $response->assertStatus(429);
});

test('サジェスト候補のメッセージ送信でも正常にJSON応答が返ること', function () {
    $suggestions = config('chatbot.suggestions', []);
    expect($suggestions)->not->toBeEmpty();

    $firstSuggestion = $suggestions[0];

    $response = $this->actingAs($this->corporateAdmin)->postJson('/api/chatbot/message', [
        'message' => $firstSuggestion,
        'session_id' => 'suggestion-test-session',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['reply', 'intent', 'data', 'sources']);
});
