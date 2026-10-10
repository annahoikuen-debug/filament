<?php

namespace Tests\Unit\Chatbot\Public;

use App\Services\Chatbot\Public\PublicIntentRecognizer;
use Tests\TestCase;

class PublicIntentRecognizerTest extends TestCase
{
    private PublicIntentRecognizer $recognizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recognizer = new PublicIntentRecognizer;
    }

    public function test_recognizes_pricing_intent(): void
    {
        $this->assertSame('pricing', $this->recognizer->recognize('料金はいくら？')->intent);
    }

    public function test_recognizes_features_intent(): void
    {
        $this->assertSame('features', $this->recognizer->recognize('どんな機能がありますか')->intent);
    }

    public function test_recognizes_demo_request_intent(): void
    {
        $this->assertSame('demo_request', $this->recognizer->recognize('デモを体験したい')->intent);
    }

    public function test_recognizes_catalog_request_intent(): void
    {
        $this->assertSame('catalog_request', $this->recognizer->recognize('資料をダウンロードしたい')->intent);
    }

    public function test_pii_question_falls_back_to_faq_and_never_resident_lookup(): void
    {
        // 内部インテント相当のPII質問は faq フォールバックになる
        $this->assertSame('faq', $this->recognizer->recognize('山田さんの請求額を教えて')->intent);
        $this->assertSame('faq', $this->recognizer->recognize('101号室の入居者の支払い状況')->intent);
        $this->assertNotSame('resident_lookup', $this->recognizer->recognize('山田さん')->intent);
    }
}
