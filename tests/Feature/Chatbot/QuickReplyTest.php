<?php

use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\DTO\ChatRequest;
use App\Services\Chatbot\DTO\ChatResponse;

beforeEach(function () {
    $this->corporateAdmin = User::factory()->corporateAdmin()->create();
    $this->service = app(ChatbotService::class);
});

test('ChatResponse DTO に quick_replies が含まれ toArray で出力されること', function () {
    $response = new ChatResponse(
        reply: 'テスト応答',
        intent: 'faq',
        data: [],
        sources: [],
        quickReplies: ['質問1' => '質問1の内容'],
    );

    $array = $response->toArray();
    expect($array)->toHaveKey('quick_replies')
        ->and($array['quick_replies'])->toBe(['質問1' => '質問1の内容']);
});

test('FAQ不一致時に quick_replies が付与されること', function () {
    $response = $this->service->handle(
        new ChatRequest('まったく関係のない謎の質問です'),
        $this->corporateAdmin
    );

    expect($response->intent)->toBe('faq')
        ->and($response->quickReplies)->not()->toBeEmpty()
        ->and($response->quickReplies)->toHaveKey('請求額の確認');
});

test('quick_replies のラベルと質問内容が設定値と一致すること', function () {
    $configReplies = config('chatbot.quick_replies');

    $response = $this->service->handle(
        new ChatRequest('未定義の質問です'),
        $this->corporateAdmin
    );

    expect($response->quickReplies)->toBe($configReplies);
});

test('API経由でも quick_replies がJSONレスポンスに含まれること', function () {
    $this->actingAs($this->corporateAdmin)
        ->postJson(route('chatbot.message'), [
            'message' => '未定義の質問です',
        ])
        ->assertOk()
        ->assertJsonStructure([
            'reply',
            'intent',
            'data',
            'sources',
            'quick_replies',
        ])
        ->assertJsonPath('quick_replies.請求額の確認', '請求額を教えてください');
});

test('Bladeビューに quick_replies ボタンと aria-label、sendQuickReply 関数が存在すること', function () {
    $bladeContent = file_get_contents(resource_path('views/filament/pages/chatbot-assistant.blade.php'));

    expect($bladeContent)->toContain('sendQuickReply(')
        ->and($bladeContent)->toContain('aria-label')
        ->and($bladeContent)->toContain('message.quickReplies')
        ->and($bladeContent)->toContain('message.quickReplyCategories');
});

test('ChatResponse DTO に quick_reply_categories が含まれ toArray で正しくシリアライズされること', function () {
    $response = new ChatResponse(
        reply: 'カテゴリテスト',
        intent: 'faq',
        data: [],
        sources: [],
        quickReplies: ['質問1' => '質問1の内容'],
        categoryQuickReplies: [
            '請求・入金' => ['質問1' => '質問1の内容'],
        ],
    );

    $array = $response->toArray();
    expect($array)->toHaveKey('quick_reply_categories')
        ->and($array['quick_reply_categories'])->toBe([
            '請求・入金' => ['質問1' => '質問1の内容'],
        ])
        ->and($array['quick_replies'])->toBe(['質問1' => '質問1の内容']);
});

test('FAQ不一致時に quick_reply_categories が設定値通り付与されること', function () {
    $configCategories = config('chatbot.quick_reply_categories');

    $response = $this->service->handle(
        new ChatRequest('未知の問い合わせです'),
        $this->corporateAdmin
    );

    expect($response->categoryQuickReplies)->toBe($configCategories)
        ->and($response->categoryQuickReplies)->toHaveKey('請求・入金');
});

test('API経由でも quick_reply_categories がJSONレスポンスに含まれ、後方互換性が維持されること', function () {
    $this->actingAs($this->corporateAdmin)
        ->postJson(route('chatbot.message'), [
            'message' => '未知の問い合わせです',
        ])
        ->assertOk()
        ->assertJsonStructure([
            'reply',
            'intent',
            'data',
            'sources',
            'quick_replies',
            'quick_reply_categories',
        ])
        ->assertJsonPath('quick_reply_categories.請求・入金.請求額の確認', '請求額を教えてください')
        ->assertJsonPath('quick_replies.請求額の確認', '請求額を教えてください');
});
