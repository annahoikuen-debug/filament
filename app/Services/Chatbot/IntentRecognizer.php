<?php

namespace App\Services\Chatbot;

use App\Services\Chatbot\DTO\IntentDTO;

class IntentRecognizer
{
    /** 主語省略とみなすパターン（年月のみ / 照会語のみの質問） */
    private const ELLIPTICAL_PERIOD_PATTERN = '/^(?:先々月|先月|今月|当月|\d{4}[\/\-年]\d{1,2}月?|\d{1,2}月)は?[？?。.!！]*$/u';

    private const ELLIPTICAL_INQUIRY_PATTERNS = [
        'invoice_amount' => '/^(?:請求額|請求)(?:は|について)?[？?。.!！]*$/u',
        'invoice_status' => '/^(?:未払い|支払い状況|入金状況)(?:は|について)?[？?。.!！]*$/u',
        'daily_charge_total' => '/^(?:月額利用料|利用料|自費)(?:は|について)?[？?。.!！]*$/u',
    ];

    /** weights 未定義時のデフォルト重み */
    private const DEFAULT_WEIGHT = 5;

    /** エンティティ 1 個あたりの加算スコア */
    private const ENTITY_BONUS = 2;

    /** escalate は明確な依頼のため競合時にブースト */
    private const ESCALATE_BOOST = 3;

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

        // 全 intent × 全 pattern をスキャンしスコアリング
        $best = null; // ['intent' => string, 'score' => int, 'entities' => array, 'order' => int]
        $order = 0;

        foreach ($this->intents as $intent => $definition) {
            $patterns = $definition['patterns'] ?? [];
            $weights = $definition['weights'] ?? [];

            foreach (array_values($patterns) as $index => $pattern) {
                if (preg_match($pattern, $normalizedMessage, $matches) !== 1) {
                    continue;
                }

                $entities = array_filter(
                    $matches,
                    fn ($key) => is_string($key),
                    ARRAY_FILTER_USE_KEY
                );

                if ($yearMonth !== null) {
                    $entities['year_month'] = $yearMonth;
                }

                $weight = is_numeric($weights[$index] ?? null) ? (int) $weights[$index] : self::DEFAULT_WEIGHT;
                $score = $weight + (count($entities) * self::ENTITY_BONUS);
                if ($intent === 'escalate') {
                    $score += self::ESCALATE_BOOST;
                }

                // 同点時は先に定義された intent を優先（現行動作のタイブレーク）
                if ($best === null || $score > $best['score']) {
                    $best = ['intent' => $intent, 'score' => $score, 'entities' => $entities, 'order' => $order];
                }
            }
            $order++;
        }

        if ($best !== null) {
            return new IntentDTO(intent: $best['intent'], entities: $best['entities']);
        }

        $entities = [];
        if ($yearMonth !== null) {
            $entities['year_month'] = $yearMonth;
        }

        // 主語省略検知: 名前・部屋番号が無く、年月や照会語だけの質問
        if ($this->isElliptical($normalizedMessage, $entities)) {
            $intent = $this->guessEllipticalIntent($normalizedMessage);
            if ($intent !== null) {
                return new IntentDTO(intent: $intent, entities: $entities, elliptical: true);
            }
        }

        return new IntentDTO(intent: 'faq', entities: $entities);
    }

    /**
     * 名前・部屋番号を含まない省略形の質問かどうか
     *
     * @param  array<string, string>  $entities
     */
    private function isElliptical(string $message, array $entities): bool
    {
        if (isset($entities['name']) || isset($entities['room'])) {
            return false;
        }

        if (preg_match(self::ELLIPTICAL_PERIOD_PATTERN, $message) === 1) {
            return true;
        }

        foreach (self::ELLIPTICAL_INQUIRY_PATTERNS as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * 省略形質問から照会 intent を推定する（文脈判断は ChatbotService 側で行う）
     */
    private function guessEllipticalIntent(string $message): ?string
    {
        foreach (self::ELLIPTICAL_INQUIRY_PATTERNS as $intent => $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return $intent;
            }
        }

        return null; // 年月のみ → 呼び出し側が last_intent を参照
    }
}
