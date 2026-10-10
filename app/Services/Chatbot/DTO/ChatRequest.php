<?php

namespace App\Services\Chatbot\DTO;

final class ChatRequest
{
    public function __construct(
        public readonly string $message,
        public readonly ?string $sessionId = null,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            message: trim($data['message'] ?? ''),
            sessionId: $data['session_id'] ?? null,
        );
    }
}
