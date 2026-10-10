<?php

namespace App\Services\Chatbot\DTO;

final class ChatResponse
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $sources
     */
    public function __construct(
        public readonly string $reply,
        public readonly string $intent,
        public readonly array $data = [],
        public readonly array $sources = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reply' => $this->reply,
            'intent' => $this->intent,
            'data' => $this->data,
            'sources' => $this->sources,
        ];
    }
}
