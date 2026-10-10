<?php

namespace App\Services\Chatbot;

use Carbon\Carbon;

class MonthParser
{
    /**
     * メッセージ文中から対象年月（YYYY-MM）を抽出・解決する
     *
     * @param string $message
     * @param string $currentYearMonth "YYYY-MM" 形式
     * @return string|null "YYYY-MM" または null
     */
    public function parse(string $message, string $currentYearMonth): ?string
    {
        $baseDate = Carbon::createFromFormat('Y-m', $currentYearMonth)->startOfMonth();

        // 1. 相対表現の判定（先々月、先月、今月、当月）
        $relativeMap = config('chatbot.periods.relative', [
            '先月' => -1,
            '先々月' => -2,
            '今月' => 0,
            '当月' => 0,
        ]);

        // マッチング優先度: 長い文字列から（先々月 -> 先月）
        uksort($relativeMap, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($relativeMap as $keyword => $offset) {
            if (mb_strpos($message, $keyword) !== false) {
                return $baseDate->copy()->addMonthsNoOverflow($offset)->format('Y-m');
            }
        }

        // 2. 年月形式（YYYY年M月, YYYY/M, YYYY-M）
        if (preg_match('/(?<year>\d{4})[\/\-年](?<month>\d{1,2})月?/u', $message, $matches)) {
            $year = (int) $matches['year'];
            $month = (int) $matches['month'];

            if ($month < 1 || $month > 12) {
                return null;
            }

            return sprintf('%04d-%02d', $year, $month);
        }

        // 3. 単独月形式（M月） - 基準日の年を採用
        if (preg_match('/(?<!\d)(?<month>\d{1,2})月/u', $message, $matches)) {
            $month = (int) $matches['month'];

            if ($month < 1 || $month > 12) {
                return null;
            }

            return sprintf('%04d-%02d', $baseDate->year, $month);
        }

        return null;
    }
}
