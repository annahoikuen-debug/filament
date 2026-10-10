<?php

use App\Services\Chatbot\FuzzyMatcher;

beforeEach(function () {
    $this->matcher = new FuzzyMatcher;
});

test('完全一致は類似度1.0を返すこと', function () {
    expect($this->matcher->similarity('山田太郎', '山田太郎'))->toBe(1.0)
        ->and($this->matcher->similarity('佐藤', '佐藤'))->toBe(1.0);
});

test('1文字誤字・違いはしきい値（0.7）以上を返すこと', function () {
    // 4文字中1文字誤字: 類似度 1 - (1/4) = 0.75 >= 0.7
    expect($this->matcher->similarity('山田太朗', '山田太郎'))->toBeGreaterThanOrEqual(0.7)
        ->and($this->matcher->similarity('鈴木一郎', '鈴木一朗'))->toBeGreaterThanOrEqual(0.7);
});

test('全く違う名前はしきい値（0.7）未満を返すこと', function () {
    expect($this->matcher->similarity('田中花子', '山田太郎'))->toBeLessThan(0.7)
        ->and($this->matcher->similarity('高橋', '佐藤'))->toBeLessThan(0.7);
});

test('カナ正規化で全角半角・空白・長音が統一されること', function () {
    // 半角カタカナ -> 全角カタカナ
    expect($this->matcher->normalizeKana('ﾔﾏﾀﾞ'))->toBe('ヤマダ');

    // ひらがな -> カタカナ
    expect($this->matcher->normalizeKana('やまだ たろう'))->toBe('ヤマダタロウ');

    // 長音記号統一
    expect($this->matcher->normalizeKana('サ〜ビス'))->toBe('サービス')
        ->and($this->matcher->normalizeKana('セーラー'))->toBe('セーラー');
});

test('空文字同士または片方空文字の境界値', function () {
    expect($this->matcher->similarity('', ''))->toBe(1.0)
        ->and($this->matcher->similarity('', '山田'))->toBe(0.0);
});
