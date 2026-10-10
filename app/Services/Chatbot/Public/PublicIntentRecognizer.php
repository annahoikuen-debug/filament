<?php

namespace App\Services\Chatbot\Public;

use App\Services\Chatbot\DTO\IntentDTO;
use App\Services\Chatbot\SynonymExpander;

/**
 * 公開サイト向けインテント認識器（内部 IntentRecognizer とは完全分離）
 *
 * PII質問（入居者・請求額照会など）は決して内部インテントに分類されず、
 * faq フォールバックとして扱われる。
 */
class PublicIntentRecognizer
{
    /** @var array<string, array<int, string>> */
    private array $intents;

    private SynonymExpander $synonymExpander;

    public function __construct(?SynonymExpander $synonymExpander = null)
    {
        $this->intents = config('chatbot.public.intents', []);
        $this->synonymExpander = $synonymExpander
            ?? new SynonymExpander(config('chatbot.public.synonyms', []));
    }

    public function recognize(string $message): IntentDTO
    {
        $expanded = $this->synonymExpander->expand(trim($message));

        foreach ($this->intents as $intent => $definition) {
            foreach ($definition['patterns'] ?? [] as $pattern) {
                if (preg_match($pattern, $expanded)) {
                    return new IntentDTO(intent: $intent);
                }
            }
        }

        return new IntentDTO(intent: 'faq');
    }
}
