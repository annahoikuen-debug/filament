<?php

namespace App\Services;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;

/**
 * 施設設定の統一アクセスサービス
 *
 * config/facility.php と facilities テーブルの二重管理を解消し、
 * 単一のインターフェースから施設設定を取得できるようにする。
 *
 * @deprecated 将来的には config/facility.php を廃止し、DBのみを信頼ソースとする
 */
class FacilityConfigService
{
    /**
     * 現在の施設設定を取得（DB優先、configフォールバック）
     *
     * @param  int|null  $facilityId  施設ID（指定時はその施設、未指定時は最初の有効施設）
     * @return array 設定配列（config/facility.php 互換形式）
     */
    public function getConfig(?int $facilityId = null): array
    {
        // DBから施設情報を取得
        $facility = Facility::current($facilityId);

        if ($facility) {
            return $facility->toConfigArray();
        }

        // DBに施設がない場合は config ファイルから取得（後方互換性・移行期間用）
        return Config::get('facility', []);
    }

    /**
     * 指定施設の設定を取得（DBのみ、見つからない場合は例外）
     *
     * @param  int  $facilityId  施設ID
     * @return array 設定配列
     *
     * @throws \RuntimeException 施設が見つからない場合
     */
    public function getConfigOrFail(int $facilityId): array
    {
        $facility = Facility::where('is_active', true)
            ->where('id', $facilityId)
            ->first();

        if (! $facility) {
            throw new \RuntimeException("施設ID {$facilityId} が見つかりません。");
        }

        return $facility->toConfigArray();
    }

    /**
     * 施設名を取得
     */
    public function getName(?int $facilityId = null): string
    {
        return $this->getConfig($facilityId)['name'] ?? '';
    }

    /**
     * 運営事業者名を取得
     */
    public function getOperator(?int $facilityId = null): string
    {
        return $this->getConfig($facilityId)['operator'] ?? '';
    }

    /**
     * 郵便番号を取得
     */
    public function getPostalCode(?int $facilityId = null): string
    {
        return $this->getConfig($facilityId)['postal_code'] ?? '';
    }

    /**
     * 住所を取得
     */
    public function getAddress(?int $facilityId = null): string
    {
        return $this->getConfig($facilityId)['address'] ?? '';
    }

    /**
     * 電話番号を取得
     */
    public function getPhone(?int $facilityId = null): string
    {
        return $this->getConfig($facilityId)['phone'] ?? '';
    }

    /**
     * FAX番号を取得
     */
    public function getFax(?int $facilityId = null): ?string
    {
        return $this->getConfig($facilityId)['fax'] ?? null;
    }

    /**
     * 適格請求書発行事業者登録番号を取得
     */
    public function getInvoiceRegistrationNumber(?int $facilityId = null): ?string
    {
        return $this->getConfig($facilityId)['invoice_registration_number'] ?? null;
    }

    /**
     * 銀行口座情報を取得
     *
     * @return array{name: string, branch_name: string, account_type: string, account_number: string, account_holder: string}
     */
    public function getBank(?int $facilityId = null): array
    {
        return $this->getConfig($facilityId)['bank'] ?? [
            'name' => '',
            'branch_name' => '',
            'account_type' => '普通',
            'account_number' => '',
            'account_holder' => '',
        ];
    }

    /**
     * 請求サイクル設定を取得
     *
     * @return array{direct_debit_day: int, bank_transfer_due_days: int}
     */
    public function getBilling(?int $facilityId = null): array
    {
        return $this->getConfig($facilityId)['billing'] ?? [
            'direct_debit_day' => 27,
            'bank_transfer_due_days' => 30,
        ];
    }

    /**
     * 印影パスを取得
     */
    public function getSealPath(?int $facilityId = null): ?string
    {
        return $this->getConfig($facilityId)['seal_path'] ?? null;
    }

    /**
     * ロゴパスを取得
     */
    public function getLogoPath(?int $facilityId = null): ?string
    {
        return $this->getConfig($facilityId)['logo_path'] ?? null;
    }

    /**
     * メールアドレスを取得
     */
    public function getEmail(?int $facilityId = null): ?string
    {
        return $this->getConfig($facilityId)['email'] ?? null;
    }

    /**
     * 施設モデルインスタンスを取得
     */
    public function getFacility(?int $facilityId = null): ?Facility
    {
        return Facility::current($facilityId);
    }

    /**
     * 全有効施設を取得
     *
     * @return Collection<int, Facility>
     */
    public function getAllActiveFacilities()
    {
        return Facility::where('is_active', true)->get();
    }

    /**
     * 設定がDB由来かどうかを判定
     */
    public function isFromDatabase(?int $facilityId = null): bool
    {
        return Facility::current($facilityId) !== null;
    }
}
