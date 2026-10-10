<?php

namespace App\Services\Chatbot\DTO;

final class ChatResponse
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $sources
     * @param  array<string, string>  $quickReplies
     * @param  array<string, array<string, string>>  $categoryQuickReplies
     * @param  array<int, array<string, string>>  $actionLinks
     */
    public function __construct(
        public readonly string $reply,
        public readonly string $intent,
        public readonly array $data = [],
        public readonly array $sources = [],
        public readonly array $quickReplies = [],
        public readonly array $categoryQuickReplies = [],
        public ?int $chatLogId = null,
        public readonly array $actionLinks = [],
    ) {}

    public function withChatLogId(?int $id): self
    {
        $clone = clone $this;
        $clone->chatLogId = $id;

        return $clone;
    }

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
            'quick_replies' => $this->quickReplies,
            'quick_reply_categories' => $this->categoryQuickReplies,
            'chat_log_id' => $this->chatLogId,
            'action_links' => $this->actionLinks,
        ];
    }
}
