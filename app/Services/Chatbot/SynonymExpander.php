<?php

namespace App\Services\Chatbot;

class SynonymExpander
{
    /** @var array<string, string> */
    private array $synonyms;

    /**
     * @param  array<string, string>|null  $synonyms
     */
    public function __construct(?array $synonyms = null)
    {
        $this->synonyms = $synonyms ?? config('chatbot.synonyms', []);
        // 置換の優先順位として文字数の長いキーから順に適用（最長一致）
        uksort($this->synonyms, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    }

    /**
     * メッセージ内の同義語を正規表現・意図判定で統一された標準語に展開・置換する
     */
    public function expand(string $message): string
    {
        if (empty($message) || empty($this->synonyms)) {
            return $message;
        }

        return strtr($message, $this->synonyms);
    }
}
