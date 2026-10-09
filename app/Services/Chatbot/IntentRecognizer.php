<?php

namespace App\Services\Chatbot;

use App\Services\Chatbot\DTO\IntentDTO;

class IntentRecognizer
{
    /** @var array<string, array<string, mixed>> */
    private array $intents;

    public function __construct()
    {
        $this->intents = config('chatbot.intents', []);
    }

    public function recognize(string $message): IntentDTO
    {
        foreach ($this->intents as $intent => $definition) {
            $patterns = $definition['patterns'] ?? [];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $message, $matches) === 1) {
                    $entities = array_filter(
                        $matches,
                        fn ($key) => is_string($key),
                        ARRAY_FILTER_USE_KEY
                    );

                    return new IntentDTO(intent: $intent, entities: $entities);
                }
            }
        }

        return new IntentDTO(intent: 'faq');
    }
}
