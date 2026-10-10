<?php

use App\Services\Chatbot\MonthParser;

beforeEach(function () {
    $this->parser = new MonthParser;
});

test('「先月」が現在年月-1を返すこと', function () {
    expect($this->parser->parse('山田太郎の先月の請求額', '2026-10'))->toBe('2026-09');
});

test('「先々月」が現在年月-2を返すこと', function () {
    expect($this->parser->parse('山田太郎の先々月の請求額', '2026-10'))->toBe('2026-08');
});

test('「今月」「当月」など特定月指定がない場合は null（最新月対象）を返すこと', function () {
    expect($this->parser->parse('山田太郎の今月の請求額', '2026-10'))->toBeNull()
        ->and($this->parser->parse('山田太郎の当月の利用料', '2026-10'))->toBeNull();
});

test('「2026年8月」が 2026-08 を返すこと', function () {
    expect($this->parser->parse('山田太郎の2026年8月の請求額', '2026-10'))->toBe('2026-08')
        ->and($this->parser->parse('2026/8の利用料', '2026-10'))->toBe('2026-08');
});

test('「8月」が当年の8月を返すこと', function () {
    expect($this->parser->parse('山田太郎の8月の請求額', '2026-10'))->toBe('2026-08');
});

test('1月の「先月」が前年12月を返すこと（年末年跨ぎ）', function () {
    expect($this->parser->parse('先月の請求額を教えて', '2026-01'))->toBe('2025-12');
});

test('1月の「先々月」が前年11月を返すこと（年末年跨ぎ）', function () {
    expect($this->parser->parse('先々月の請求額を教えて', '2026-01'))->toBe('2025-11');
});

test('無効な月（13月）は null を返すこと', function () {
    expect($this->parser->parse('2026年13月の請求額', '2026-10'))->toBeNull()
        ->and($this->parser->parse('13月の請求額', '2026-10'))->toBeNull();
});

test('期間指定なしは null を返すこと', function () {
    expect($this->parser->parse('山田太郎の請求額はいくらですか', '2026-10'))->toBeNull();
});
