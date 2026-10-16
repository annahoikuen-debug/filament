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
     * @param  array<int, array<int|string, string>>  $rows  table 表示用データ行（columns と順序対応）
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
        public readonly string $displayType = 'text', // 'text' | 'list' | 'table' | 'candidates'
        public readonly array $columns = [],
        public readonly array $rows = [],
    ) {}

    public function withChatLogId(?int $id): self
    {
        $clone = clone $this;
        $clone->chatLogId = $id;

        return $clone;
    }

    /**
     * displayType / columns / rows を差し替えたクローンを返す
     */
    public function withDisplay(string $displayType, array $columns = [], array $rows = []): self
    {
        return new self(
            reply: $this->reply,
            intent: $this->intent,
            data: $this->data,
            sources: $this->sources,
            quickReplies: $this->quickReplies,
            categoryQuickReplies: $this->categoryQuickReplies,
            chatLogId: $this->chatLogId,
            actionLinks: $this->actionLinks,
            displayType: $displayType,
            columns: $columns,
            rows: $rows,
        );
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
            'display_type' => $this->displayType,
            'columns' => $this->columns,
            'rows' => $this->rows,
        ];
    }
}
