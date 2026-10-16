<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotSynonym;
use Illuminate\Support\Facades\Cache;

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
     * DB 登録の同義語（全施設共通 + 指定施設）を config の同義語にマージして生成する
     *
     * config 側が優先。DB テーブルが未作成・取得失敗時は config のみで動作（段階的導入）。
     */
    public static function fromDatabase(?int $facilityId = null): self
    {
        $dbSynonyms = self::loadFromDatabase($facilityId);

        return new self(array_merge($dbSynonyms, config('chatbot.synonyms', [])));
    }

    /**
     * @return array<string, string>
     */
    private static function loadFromDatabase(?int $facilityId): array
    {
        $cacheKey = 'chatbot_synonyms:'.($facilityId ?? 'global');

        try {
            return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($facilityId) {
                return ChatbotSynonym::query()
                    ->active()
                    ->where(fn ($q) => $q->whereNull('facility_id')->orWhere('facility_id', $facilityId))
                    ->orderByDesc('facility_id') // 施設固有を優先（後から config で上書きされないよう先に並べる）
                    ->get()
                    // 施設固有を優先して重複排除
                    ->groupBy('from_word')
                    ->map(fn ($group) => $group->first()->to_word)
                    ->all();
            });
        } catch (\Throwable) {
            // テーブル未作成等の環境では config のみで動作
            return [];
        }
    }

    /**
     * DB 同義語のキャッシュを無効化する（CRUD 時に呼び出し）
     */
    public static function flushCache(): void
    {
        Cache::forget('chatbot_synonyms:global');
        Cache::forget('chatbot_synonyms:'.(auth()->user()?->facility_id ?? 'global'));
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
