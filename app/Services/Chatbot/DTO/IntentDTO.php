<?php

namespace App\Services\Chatbot\DTO;

/**
 * @param  array<string, string>  $entities
 */
final class IntentDTO
{
    public function __construct(
        public readonly string $intent,
        public readonly array $entities = [],
        public readonly bool $elliptical = false,
    ) {}

    /**
     * エンティティを追加した新しいインスタンスを返す
     *
     * @param  array<string, string>  $entities
     */
    public function withEntities(array $entities): self
    {
        return new self(
            intent: $this->intent,
            entities: array_merge($this->entities, $entities),
            elliptical: $this->elliptical,
        );
    }
}
