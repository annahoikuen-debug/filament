<?php

namespace App\Services\Chatbot;

class BigramTokenizer
{
    /**
     * 文字列から Bi-gram（2文字組）トークンの配列を抽出する
     *
     * @return array<int, string>
     */
    public function tokenize(string $text): array
    {
        // 空白・句読点を正規化して除去
        $clean = preg_replace('/[\s\p{P}]+/u', '', $text) ?? $text;
        $len = mb_strlen($clean);

        if ($len < 2) {
            return $len === 1 ? [$clean] : [];
        }

        $tokens = [];
        for ($i = 0; $i < $len - 1; $i++) {
            $tokens[] = mb_substr($clean, $i, 2);
        }

        return array_values(array_unique($tokens));
    }

    /**
     * 2つの文字列間の Bi-gram Jaccard類似度（0.0 〜 1.0）を計算する
     */
    public function similarity(string $textA, string $textB): float
    {
        $tokensA = $this->tokenize($textA);
        $tokensB = $this->tokenize($textB);

        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }

        $intersection = array_intersect($tokensA, $tokensB);
        $union = array_unique(array_merge($tokensA, $tokensB));

        if (empty($union)) {
            return 0.0;
        }

        return count($intersection) / count($union);
    }
}
