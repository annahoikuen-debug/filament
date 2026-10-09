<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ServiceType: string implements HasLabel
{
    case VisitingCare = 'visiting_care';
    case DayCare = 'day_care';
    case CarePlanning = 'care_planning';
    case HomeNursing = 'home_nursing';
    case ShortStay = 'short_stay';
    case WelfareEquipment = 'welfare_equipment';
    case HomeModification = 'home_modification';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::VisitingCare => '訪問介護',
            self::DayCare => '通所介護',
            self::CarePlanning => '居宅介護支援',
            self::HomeNursing => '訪問看護',
            self::ShortStay => '短期入所生活介護',
            self::WelfareEquipment => '福祉用具貸与',
            self::HomeModification => '居宅介護住宅改修',
            self::Other => 'その他',
        };
    }

    /**
     * 介護保険サービスかどうか
     */
    public function isInsuranceService(): bool
    {
        return in_array($this, [
            self::VisitingCare,
            self::DayCare,
            self::CarePlanning,
            self::HomeNursing,
            self::ShortStay,
            self::WelfareEquipment,
            self::HomeModification,
        ], true);
    }

    /**
     * 表示順序
     */
    public function getSortOrder(): int
    {
        return match ($this) {
            self::VisitingCare => 1,
            self::DayCare => 2,
            self::CarePlanning => 3,
            self::HomeNursing => 4,
            self::ShortStay => 5,
            self::WelfareEquipment => 6,
            self::HomeModification => 7,
            self::Other => 99,
        };
    }

    /**
     * 全種類を表示順で取得
     */
    public static function getOrderedCases(): array
    {
        return collect(self::cases())
            ->sortBy(fn ($case) => $case->getSortOrder())
            ->values()
            ->all();
    }
}