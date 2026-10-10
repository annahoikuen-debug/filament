<?php

namespace Tests\Feature\Chatbot\Public;

use Tests\TestCase;

class WidgetIntegrationTest extends TestCase
{
    private string $widgetJs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->widgetJs = $this->get('/chatbot/widget.js')->getContent();
    }

    public function test_widget_uses_shadow_mode_and_textcontent_rendering(): void
    {
        // Shadow DOM カプセル化
        $this->assertStringContainsString("attachShadow({ mode: 'open' })", $this->widgetJs);

        // XSS回避の textContent 描画（innerHTML でメッセージ本文を描画しない）
        $this->assertStringContainsString('textContent', $this->widgetJs);
        $this->assertStringNotContainsString('.innerHTML = text', $this->widgetJs);
    }

    public function test_widget_does_not_reference_internal_api_endpoints(): void
    {
        // 内部API（認証付きチャットボットAPI）への参照が含まれてはならない
        $this->assertStringNotContainsString('/api/chatbot/', $this->widgetJs);
        $this->assertStringNotContainsString('/admin/', $this->widgetJs);

        // 公開APIのみ参照
        $this->assertStringContainsString('/api/public/chatbot/message', $this->widgetJs);
    }
}
