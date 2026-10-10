<?php

use App\Models\ChatbotFaq;
use App\Models\Facility;
use App\Services\Chatbot\BigramTokenizer;
use App\Services\Chatbot\FaqResponder;

beforeEach(function () {
    $this->facility = Facility::factory()->create();
    $this->tokenizer = new BigramTokenizer;
    $this->responder = new FaqResponder($this->tokenizer);
});

test('BigramTokenizer が正しく2文字トークンを生成し類似度を計算すること', function () {
    $tokens = $this->tokenizer->tokenize('領収書発行');
    expect($tokens)->toBe(['領収', '収書', '書発', '発行']);

    $similarity = $this->tokenizer->similarity('領収書発行', '領収書の発行');
    expect($similarity)->toBeGreaterThanOrEqual(0.5);
});

test('語順の入れ替えや助詞の違いでも Bi-gram によりFAQがヒットすること', function () {
    $faq = ChatbotFaq::create([
        'facility_id' => $this->facility->id,
        'category' => '請求',
        'question' => '領収書の再発行手順を教えてください',
        'answer' => '管理画面の請求書一覧から再発行可能です。',
        'keywords' => ['領収書', '再発行'],
        'is_active' => true,
    ]);

    // 「再発行 領収書の手順」のように語順が逆でもヒットすること
    $results = $this->responder->search('再発行の領収書について手順を知りたい', $this->facility->id);

    expect($results)->not()->toBeEmpty()
        ->and($results->first()->id)->toBe($faq->id);
});

test('無関係な質問は閾値未満でヒットしないこと', function () {
    ChatbotFaq::create([
        'facility_id' => $this->facility->id,
        'category' => '施設',
        'question' => '面会時間は何時から何時までですか',
        'answer' => '面会時間は10時から17時までです。',
        'keywords' => ['面会', '面会時間'],
        'is_active' => true,
    ]);

    $results = $this->responder->search('駐車場の利用料金はいくらですか', $this->facility->id);
    expect($results)->toBeEmpty();
});
