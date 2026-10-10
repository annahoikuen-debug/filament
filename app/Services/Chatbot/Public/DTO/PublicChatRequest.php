<?php

namespace App\Services\Chatbot\Public\DTO;

/**
 * 公開チャットリクエストDTO（visitor_id / channel を含む）
 */
final class PublicChatRequest
{
    public function __construct(
        public readonly string $message,
        public readonly string $channel = 'public',
        public readonly ?string $visitorId = null,
        public readonly string $sessionId = '',
    ) {}
}
