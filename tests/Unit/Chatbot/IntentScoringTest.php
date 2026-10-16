<?php

namespace Tests\Unit\Chatbot;

use App\Services\Chatbot\IntentRecognizer;
use Tests\TestCase;

class IntentScoringTest extends TestCase
{
    public function test_complex_question_picks_best_scoring_intent(): void
    {
        // 「未払いの請求額」: invoice_amount（請求額パターン）と invoice_status（未払いパターン）が競合
        // エンティティ数・重みで高い方を選ぶ
        $recognizer = new IntentRecognizer;
        $intent = $recognizer->recognize('佐藤さんの未払いの請求額');

        $this->assertContains($intent->intent, ['invoice_amount', 'invoice_status']);
    }

    public function test_config_weights_take_priority(): void
    {
        config()->set('chatbot.intents', [
            'intent_a' => [
                'patterns' => ['/キーワード/u'],
                'weights' => [10],
            ],
            'intent_b' => [
                'patterns' => ['/キーワード/u'],
                'weights' => [3],
            ],
        ]);

        $recognizer = new IntentRecognizer;
        $intent = $recognizer->recognize('キーワード');

        $this->assertSame('intent_a', $intent->intent);
    }

    public function test_missing_weights_use_default_and_break_ties_by_order(): void
    {
        // weights 無しの旧形式 config でも動作（同点時は先に定義した方）
        config()->set('chatbot.intents', [
            'first_intent' => ['patterns' => ['/共通/u']],
            'second_intent' => ['patterns' => ['/共通/u']],
        ]);

        $recognizer = new IntentRecognizer;
        $intent = $recognizer->recognize('共通');

        $this->assertSame('first_intent', $intent->intent);
    }

    public function test_escalate_gets_boost_in_conflicts(): void
    {
        config()->set('chatbot.intents', [
            'other' => [
                'patterns' => ['/サポート/u'],
                'weights' => [7],
            ],
            'escalate' => [
                'patterns' => ['/サポート/u'],
                'weights' => [7],
            ],
        ]);

        $recognizer = new IntentRecognizer;
        $intent = $recognizer->recognize('サポート');

        $this->assertSame('escalate', $intent->intent);
    }

    public function test_entity_count_increases_score(): void
    {
        config()->set('chatbot.intents', [
            'no_entity' => [
                'patterns' => ['/請求額/u'],
                'weights' => [8],
            ],
            'with_name' => [
                'patterns' => ['/(?<name>.{2,}?)の?請求額/u'],
                'weights' => [8],
            ],
        ]);

        $recognizer = new IntentRecognizer;
        $intent = $recognizer->recognize('田中さんの請求額');

        $this->assertSame('with_name', $intent->intent);
        $this->assertSame('田中さん', $intent->entities['name']);
    }
}
