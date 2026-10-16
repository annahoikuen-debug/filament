<?php

namespace App\Enums;

enum UserRole: string
{
    case CorporateAdmin = 'corporate_admin'; // 法人統括管理者（全施設・全権限）
    case FacilityAdmin = 'facility_admin';   // 施設長・施設管理者（自施設全権限）
    case Accountant = 'accountant';         // 経理担当（請求確定・全銀・会計出力可）
    case CareStaff = 'care_staff';           // 現場介護職（自費実績入力のみ・確定不可）

    public function getLabel(): string
    {
        return match ($this) {
            self::CorporateAdmin => '法人統括管理者',
            self::FacilityAdmin => '施設管理者',
            self::Accountant => '経理担当者',
            self::CareStaff => '現場スタッフ(介護職)',
        };
    }

    /**
     * 請求書確定・消込などの重要操作権限を持つか
     */
    public function canManageBilling(): bool
    {
        return in_array($this, [self::CorporateAdmin, self::FacilityAdmin, self::Accountant], true);
    }

    /**
     * 全銀・会計出力権限を持つか
     */
    public function canExportFinancialData(): bool
    {
        return in_array($this, [self::CorporateAdmin, self::FacilityAdmin, self::Accountant], true);
    }
}
