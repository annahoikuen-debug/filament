<?php

namespace App\Services\Chatbot;

class FuzzyMatcher
{
    /**
     * 2つの文字列間の類似度（0.0 〜 1.0）を計算する
     * マルチバイト文字列に対応したレーベンシュタイン距離
     */
    public function similarity(string $a, string $b): float
    {
        $a = trim($a);
        $b = trim($b);

        if ($a === $b) {
            return 1.0;
        }

        $lenA = mb_strlen($a);
        $lenB = mb_strlen($b);

        if ($lenA === 0 || $lenB === 0) {
            return 0.0;
        }

        $distance = $this->mbLevenshtein($a, $b);
        $maxLen = max($lenA, $lenB);

        return max(0.0, 1.0 - ($distance / $maxLen));
    }

    /**
     * カナ・全角半角・空白・濁点を正規化する
     */
    public function normalizeKana(string $text): string
    {
        // 全角英数字・スペースを半角に変換、ひらがなを全角カタカナに変換（K: 半角カタカナを全角カタカナに、V: 濁点付きを1文字に、c: ひらがなをカタカナに、s: 全角スペースを半角に）
        $converted = mb_convert_kana($text, 'KVCs', 'UTF-8');

        // 空白を除去
        $converted = preg_replace('/\s+/u', '', $converted) ?? $converted;

        // 長音符・ダッシュ類の統一
        return preg_replace('/[ー−―‐〜~]/u', 'ー', $converted) ?? $converted;
    }

    /**
     * マルチバイト対応のレーベンシュタイン距離
     */
    private function mbLevenshtein(string $str1, string $str2): int
    {
        $chars1 = mb_str_split($str1);
        $chars2 = mb_str_split($str2);

        $len1 = count($chars1);
        $len2 = count($chars2);

        $d = [];

        for ($i = 0; $i <= $len1; $i++) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $len2; $j++) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $len1; $i++) {
            for ($j = 1; $j <= $len2; $j++) {
                $cost = ($chars1[$i - 1] === $chars2[$j - 1]) ? 0 : 1;

                $d[$i][$j] = min(
                    $d[$i - 1][$j] + 1,       // 削除
                    $d[$i][$j - 1] + 1,       // 挿入
                    $d[$i - 1][$j - 1] + $cost // 置換
                );
            }
        }

        return $d[$len1][$len2];
    }
}
