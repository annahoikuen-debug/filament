<?php

namespace App\Services;

use App\Models\Trial;

class QuoteService
{
    public const PLAN_NAMES = [
        'starter' => 'スターター',
        'standard' => 'スタンダード',
        'enterprise' => 'エンタープライズ',
    ];

    public const STARTER_PRICE = 15000;
    public const STANDARD_PRICE = 35000;

    /**
     * 入居定員から推奨プランと月額を算出
     * （pricing.html の料金体系に基づく）
     */
    public function quote(Trial $trial): array
    {
        $capacity = $trial->resident_capacity;

        // 200名以上または不明・複数施設はエンタープライズ（要見積）
        if (in_array($capacity, ['over_200', 'unknown'], true)) {
            return [
                'plan' => 'enterprise',
                'plan_name' => self::PLAN_NAMES['enterprise'],
                'monthly_price' => 0,
                'note' => 'エンタープライズプランは施設規模・導入形態により個別見積りとなります。営業担当よりご連絡いたします。',
            ];
        }

        // 50名以上はスタンダード
        if (in_array($capacity, ['50_100', '100_200'], true)) {
            return [
                'plan' => 'standard',
                'plan_name' => self::PLAN_NAMES['standard'],
                'monthly_price' => self::STANDARD_PRICE,
                'note' => '会計CSV連携・API読取専用を含む全機能をご利用いただけます。',
            ];
        }

        // 30名以下はスターター
        return [
            'plan' => 'starter',
            'plan_name' => self::PLAN_NAMES['starter'],
            'monthly_price' => self::STARTER_PRICE,
            'note' => '単一施設・30名までの基本機能（請求生成・PDF出力・入金管理）をご利用いただけます。',
        ];
    }
}
