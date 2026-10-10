<?php

namespace Tests\Feature\Chatbot\Public;

use Tests\TestCase;

class WidgetAssetTest extends TestCase
{
    public function test_widget_js_returns_200_with_js_content(): void
    {
        $response = $this->get('/chatbot/widget.js');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/javascript', $response->headers->get('Content-Type'));
        $content = $response->getContent();
        $this->assertStringContainsString('attachShadow', $content);
    }

    public function test_response_has_cache_control_header_and_api_endpoint(): void
    {
        $response = $this->get('/chatbot/widget.js');

        $response->assertStatus(200);
        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('/api/public/chatbot/message', $response->getContent());
    }
}
