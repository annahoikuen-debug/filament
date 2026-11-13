<?php

namespace App\Services\Ops;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * 職員向け通知の宛先解決。
 *
 * 施設スコープを厳守し、送信対象ユーザーを施設単位で絞り込む。
 * 法人管理者（corporate_admin / is_admin）は全施設、施設管理者は自施設のみ。
 */
class NotificationTargetResolver
{
    /**
     * 指定施設に紐づく通知対象ユーザーを返す。
     *
     * @param  int|null  $facilityId  null なら法人管理者のみ返す
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function resolveForFacility(?int $facilityId): \Illuminate\Database\Eloquent\Collection
    {
        $query = User::query()
            ->where('is_admin', true)
            ->whereNotNull('email');

        if ($facilityId) {
            // 施設管理者は自施設のみ。施設ID指定時はその施設の管理者＋法人管理者
            $query->where(function ($q) use ($facilityId) {
                $q->where('facility_id', $facilityId)
                    ->orWhere('role', 'corporate_admin');
            });
        } else {
            // 施設指定なし（全施設対象）は法人管理者のみ
            $query->where('role', 'corporate_admin');
        }

        return $query->get();
    }

    /**
     * 現在認証中のユーザーが通知を送信できる施設IDを返す。
     * 施設管理者なら自施設、法人管理者なら null（全施設）。
     */
    public function resolveCurrentUserFacility(): ?int
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        if ($user->canAccessAllFacilities()) {
            return null;
        }

        return (int) $user->facility_id;
    }

    /**
     * 全施設の通知対象ユーザー（法人管理者のみ）を返す。
     */
    public function resolveAllCorporateAdmins(): \Illuminate\Database\Eloquent\Collection
    {
        return User::query()
            ->where('role', 'corporate_admin')
            ->whereNotNull('email')
            ->get();
    }
}
