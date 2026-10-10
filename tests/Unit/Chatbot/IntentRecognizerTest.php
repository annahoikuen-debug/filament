<?php

use App\Services\Chatbot\IntentRecognizer;

beforeEach(function () {
    $this->recognizer = new IntentRecognizer;
});

test('入居者名を含む質問が resident_lookup に分類されること', function () {
    $intent = $this->recognizer->recognize('山田太郎の情報を教えてください');

    expect($intent->intent)->toBe('resident_lookup')
        ->and($intent->entities['name'])->toBe('山田太郎');
});

test('部屋番号の質問が resident_lookup に分類されること', function () {
    $intent = $this->recognizer->recognize('101号室の入居者は誰ですか');

    expect($intent->intent)->toBe('resident_lookup')
        ->and($intent->entities['room'])->toBe('101');
});

test('請求額の質問が invoice_amount に分類されること', function () {
    $intent = $this->recognizer->recognize('山田太郎の今月の請求額はいくらですか');

    expect($intent->intent)->toBe('invoice_amount');
});

test('支払い状況の質問が invoice_status に分類されること', function () {
    $intent = $this->recognizer->recognize('山田太郎の支払い状況を教えてください');

    expect($intent->intent)->toBe('invoice_status');
});

test('利用料の質問が daily_charge_total に分類されること', function () {
    $intent = $this->recognizer->recognize('山田太郎の月額利用料はいくらですか');

    expect($intent->intent)->toBe('daily_charge_total');
});

test('曖昧な質問は faq に分類されること', function () {
    $intent = $this->recognizer->recognize('利用規約について教えてください');

    expect($intent->intent)->toBe('faq');
});

test('空のメッセージは faq に分類されること', function () {
    $intent = $this->recognizer->recognize('');

    expect($intent->intent)->toBe('faq');
});
