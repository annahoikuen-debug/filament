<?php

namespace App\Services\Chatbot;

use App\Services\Chatbot\DTO\IntentDTO;

class IntentRecognizer
{
    /** @var array<string, array<string, mixed>> */
    private array $intents;

    public function __construct(
        private readonly ?MonthParser $monthParser = null,
        private readonly ?SynonymExpander $synonymExpander = null,
    ) {
        $this->intents = config('chatbot.intents', []);
    }

    public function recognize(string $message): IntentDTO
    {
        $expander = $this->synonymExpander ?? new SynonymExpander;
        $normalizedMessage = $expander->expand($message);

        $parser = $this->monthParser ?? new MonthParser;
        $yearMonth = $parser->parse($normalizedMessage, now()->format('Y-m'));

        foreach ($this->intents as $intent => $definition) {
            $patterns = $definition['patterns'] ?? [];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $normalizedMessage, $matches) === 1) {
                    $entities = array_filter(
                        $matches,
                        fn ($key) => is_string($key),
                        ARRAY_FILTER_USE_KEY
                    );

                    if ($yearMonth !== null) {
                        $entities['year_month'] = $yearMonth;
                    }

                    return new IntentDTO(intent: $intent, entities: $entities);
                }
            }
        }

        $entities = [];
        if ($yearMonth !== null) {
            $entities['year_month'] = $yearMonth;
        }

        return new IntentDTO(intent: 'faq', entities: $entities);
    }
}
