<?php

use App\Services\Chatbot\SynonymExpander;

test('未登録のメッセージはそのまま返ること', function () {
    $expander = new SynonymExpander;
    expect($expander->expand('山田太郎さんの請求書'))->toBe('山田太郎さんの請求書');
});

test('シノニム辞書の定義語が標準語に置換されること', function () {
    $expander = new SynonymExpander([
        '引き落とし' => '口座振替',
        '未払い' => '未入金',
        '滞納' => '未入金',
    ]);

    expect($expander->expand('佐藤さんの未払い状況と引き落とし日'))->toBe('佐藤さんの未入金状況と口座振替日')
        ->and($expander->expand('家賃の滞納はありますか'))->toBe('家賃の未入金はありますか');
});

test('空文字列の入力でもエラーにならず空文字列が返ること', function () {
    $expander = new SynonymExpander;
    expect($expander->expand(''))->toBe('');
});
