<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'facility_id',
        'item_type',
        'account_side',
        'account_code',
        'account_name',
        'sub_account_code',
        'sub_account_name',
        'tax_code',
        'department_code',
        'department_name',
        'tag_codes',
        'is_active',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'tag_codes' => 'array',
    ];

    /**
     * 品目タイプ
     */
    public const ITEM_TYPE_RENT = 'rent';

    public const ITEM_TYPE_MANAGEMENT_FEE = 'management_fee';

    public const ITEM_TYPE_SERVICE = 'service';

    public const ITEM_TYPE_ADVANCE_PAYMENT = 'advance_payment';

    /**
     * 勘定方向
     */
    public const ACCOUNT_SIDE_DEBIT = 'debit';

    public const ACCOUNT_SIDE_CREDIT = 'credit';

    /**
     * 施設リレーション
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * タグコードのアクセサ
     */
    protected function tagCodes(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : [],
            set: fn ($value) => json_encode($value),
        );
    }

    /**
     * 指定施設・品目・方向の勘定科目を取得
     */
    public static function getAccount(int $facilityId, string $itemType, string $accountSide): ?self
    {
        return self::where('facility_id', $facilityId)
            ->where('item_type', $itemType)
            ->where('account_side', $accountSide)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * 施設の全勘定科目を取得（品目×方向でグルーピング）
     */
    public static function getAllForFacility(int $facilityId): array
    {
        return self::where('facility_id', $facilityId)
            ->where('is_active', true)
            ->orderBy('item_type')
            ->orderBy('account_side')
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn ($item) => "{$item->item_type}.{$item->account_side}")
            ->map(fn ($group) => $group->first())
            ->toArray();
    }

    /**
     * デフォルト勘定科目マスタを施設に作成
     */
    public static function createDefaultsForFacility(int $facilityId): void
    {
        $defaults = [
            // 家賃: 借方=売掛金、貸方=賃貸料収入
            ['item_type' => self::ITEM_TYPE_RENT, 'account_side' => self::ACCOUNT_SIDE_DEBIT, 'account_code' => '1100', 'account_name' => '売掛金', 'tax_code' => 'tax_exempt', 'sort_order' => 1],
            ['item_type' => self::ITEM_TYPE_RENT, 'account_side' => self::ACCOUNT_SIDE_CREDIT, 'account_code' => '4110', 'account_name' => '賃貸料収入', 'tax_code' => 'tax_exempt', 'sort_order' => 1],

            // 管理費: 借方=売掛金、貸方=施設管理費収入
            ['item_type' => self::ITEM_TYPE_MANAGEMENT_FEE, 'account_side' => self::ACCOUNT_SIDE_DEBIT, 'account_code' => '1100', 'account_name' => '売掛金', 'tax_code' => 'taxable_10', 'sort_order' => 2],
            ['item_type' => self::ITEM_TYPE_MANAGEMENT_FEE, 'account_side' => self::ACCOUNT_SIDE_CREDIT, 'account_code' => '4120', 'account_name' => '施設管理費収入', 'tax_code' => 'taxable_10', 'sort_order' => 2],

            // 自費サービス: 借方=売掛金、貸方=自費・立替金収入
            ['item_type' => self::ITEM_TYPE_SERVICE, 'account_side' => self::ACCOUNT_SIDE_DEBIT, 'account_code' => '1100', 'account_name' => '売掛金', 'tax_code' => 'taxable_10', 'sort_order' => 3],
            ['item_type' => self::ITEM_TYPE_SERVICE, 'account_side' => self::ACCOUNT_SIDE_CREDIT, 'account_code' => '4130', 'account_name' => '自費・立替金収入', 'tax_code' => 'taxable_10', 'sort_order' => 3],

            // 立替金: 借方=売掛金、貸方=立替金収入
            ['item_type' => self::ITEM_TYPE_ADVANCE_PAYMENT, 'account_side' => self::ACCOUNT_SIDE_DEBIT, 'account_code' => '1100', 'account_name' => '売掛金', 'tax_code' => 'taxable_10', 'sort_order' => 4],
            ['item_type' => self::ITEM_TYPE_ADVANCE_PAYMENT, 'account_side' => self::ACCOUNT_SIDE_CREDIT, 'account_code' => '4140', 'account_name' => '立替金収入', 'tax_code' => 'taxable_10', 'sort_order' => 4],
        ];

        foreach ($defaults as $default) {
            self::updateOrCreate(
                [
                    'facility_id' => $facilityId,
                    'item_type' => $default['item_type'],
                    'account_side' => $default['account_side'],
                ],
                array_merge($default, ['facility_id' => $facilityId, 'is_active' => true])
            );
        }
    }
}
