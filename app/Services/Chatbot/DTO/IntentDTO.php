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
    ) {
    }
}
