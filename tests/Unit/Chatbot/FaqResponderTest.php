<?php

use App\Models\ChatbotFaq;
use App\Models\Facility;
use App\Models\User;
use App\Services\Chatbot\FaqResponder;

beforeEach(function () {
    $this->responder = new FaqResponder;
    $this->facility = Facility::factory()->create();
    $this->corporateAdmin = User::factory()->corporateAdmin()->create();
    $this->facilityAdmin = User::factory()->facilityAdmin()->create(['facility_id' => $this->facility->id]);
});

test('キーワード一致でFAQが検索されること', function () {
    ChatbotFaq::create([
        'question' => '利用料の支払い方法を教えてください',
        'keywords' => ['支払い', '振込', '入金'],
        'answer' => '利用料は毎月25日に口座振込でのお支払いです。',
        'category' => '支払い',
    ]);

    $results = $this->responder->search('支払い方法について', $this->facility->id);

    expect($results)->toHaveCount(1)
        ->and($results->first()->answer)->toContain('口座振込');
});

test('無効なFAQは検索対象外になること', function () {
    ChatbotFaq::create([
        'question' => '利用料の支払い方法を教えてください',
        'keywords' => ['支払い'],
        'answer' => '利用料は毎月25日に口座振込でのお支払いです。',
        'is_active' => false,
    ]);

    $results = $this->responder->search('支払い方法について', $this->facility->id);

    expect($results)->toHaveCount(0);
});

test('施設固有FAQは他施設から検索されないこと', function () {
    $otherFacility = Facility::factory()->create();

    ChatbotFaq::create([
        'question' => '施設A独自の案内',
        'keywords' => ['独自'],
        'answer' => '施設A独自の回答です。',
        'facility_id' => $this->facility->id,
    ]);

    $results = $this->responder->search('独自の案内', $otherFacility->id);

    expect($results)->toHaveCount(0);
});

test('共通FAQは全施設から検索されること', function () {
    ChatbotFaq::create([
        'question' => '共通の案内',
        'keywords' => ['共通'],
        'answer' => '共通の回答です。',
        'facility_id' => null,
    ]);

    $results = $this->responder->search('共通の案内', $this->facility->id);

    expect($results)->toHaveCount(1);
});

test('一致なしは空コレクションを返すこと', function () {
    ChatbotFaq::create([
        'question' => '利用料の支払い方法',
        'keywords' => ['支払い'],
        'answer' => '利用料は毎月25日に口座振込です。',
    ]);

    $results = $this->responder->search('まったく関係のない質問', $this->facility->id);

    expect($results)->toHaveCount(0);
});

test('キーワード一致数が多いほど上位に表示されること', function () {
    ChatbotFaq::create([
        'question' => '質問A',
        'keywords' => ['支払い'],
        'answer' => '回答A',
        'sort_order' => 1,
    ]);
    ChatbotFaq::create([
        'question' => '質問B',
        'keywords' => ['支払い', '振込', '入金'],
        'answer' => '回答B',
        'sort_order' => 2,
    ]);

    $results = $this->responder->search('支払い 振込 入金', $this->facility->id);

    expect($results->first()->answer)->toBe('回答B');
});
