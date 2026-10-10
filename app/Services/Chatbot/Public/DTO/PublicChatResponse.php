<?php

namespace App\Services\Chatbot\Public\DTO;

/**
 * 公開チャット応答DTO
 *
 * @param  array<string, mixed>  $data
 * @param  array<int, array<string, string>>  $actionLinks
 * @param  array<string, string>  $quickReplies
 * @param  array<string, mixed>|null  $form  リード獲得ミニフォーム定義
 */
final class PublicChatResponse
{
    public function __construct(
        public readonly string $reply,
        public readonly string $intent,
        public readonly array $data = [],
        public readonly array $actionLinks = [],
        public readonly array $quickReplies = [],
        public readonly bool $faqMatched = false,
        public readonly ?array $form = null,
        public ?int $chatLogId = null,
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
            'action_links' => $this->actionLinks,
            'quick_replies' => $this->quickReplies,
            'faq_matched' => $this->faqMatched,
            'form' => $this->form,
            'chat_log_id' => $this->chatLogId,
        ];
    }
}
