<?php

namespace App\Services;

class LeadScoringService
{
    /**
     * 施設種別のスコア
     */
    private const FACILITY_TYPE_SCORES = [
        'special_nursing' => 30, // 特養
        'nursing_health' => 30,  // 老健
        'medical_care' => 30,    // 介護医療院
        'paid_elderly' => 25,    // 有料老人ホーム・サ高住
        'group_home' => 20,      // グループホーム
        'home_care' => 15,       // 訪問・通所系
        'other' => 10,
    ];

    /**
     * 入居定員のスコア
     */
    private const CAPACITY_SCORES = [
        'over_200' => 30,
        '100_200' => 25,
        '50_100' => 20,
        '30_50' => 15,
        'under_30' => 10,
        'unknown' => 5,
    ];

    /**
     * 予算感のスコア
     */
    private const BUDGET_SCORES = [
        'over_50k' => 20,
        '30k_50k' => 15,
        '10k_30k' => 10,
        'under_10k' => 5,
        'undecided' => 5,
    ];

    private const CHALLENGE_SCORE = 5;

    private const CHALLENGE_MAX = 25;

    private const SEED_SAMPLE_BONUS = 10;

    public const HOT_THRESHOLD = 80;

    public const WARM_THRESHOLD = 50;

    /**
     * リードスコアを算出（0〜100）
     */
    public function score(array $data): int
    {
        $score = 0;

        // 施設種別
        $score += self::FACILITY_TYPE_SCORES[$data['facility_type'] ?? ''] ?? 0;

        // 入居定員
        $score += self::CAPACITY_SCORES[$data['resident_capacity'] ?? ''] ?? 0;

        // 課題（上限あり）
        $challenges = $data['challenges'] ?? [];
        if (is_array($challenges)) {
            $score += min(count($challenges) * self::CHALLENGE_SCORE, self::CHALLENGE_MAX);
        }

        // 予算感
        $score += self::BUDGET_SCORES[$data['budget'] ?? ''] ?? 0;

        // サンプルデータ投入希望（導入意欲の指標）
        if (! empty($data['seed_sample_data'])) {
            $score += self::SEED_SAMPLE_BONUS;
        }

        return min(100, $score);
    }

    /**
     * スコア帯の判定
     */
    public function tier(int $score): string
    {
        if ($score >= self::HOT_THRESHOLD) {
            return 'hot';
        }

        if ($score >= self::WARM_THRESHOLD) {
            return 'warm';
        }

        return 'cold';
    }
}
