<?php

namespace App\Providers;

use App\Models\MonthlyInvoice;
use App\Policies\MonthlyInvoicePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->validateFacilityConfig();

        // ポリシー登録
        Gate::policy(MonthlyInvoice::class, MonthlyInvoicePolicy::class);
    }

    /**
     * 施設設定 (config/facility.php) の起動時バリデーション
     */
    protected function validateFacilityConfig(): void
    {
        $facility = config('facility');

        if (empty($facility)) {
            return;
        }

        // 請求書登録番号のバリデーション（T + 13桁数字）
        if (! empty($facility['invoice_registration_number'])) {
            if (! preg_match('/^T\d{13}$/', $facility['invoice_registration_number'])) {
                throw new RuntimeException(
                    '施設登録番号の形式が不正です。Tから始まる13桁の数字である必要があります。'
                );
            }
        }

        // 銀行口座情報の基本バリデーション
        if (! empty($facility['bank'])) {
            $bank = $facility['bank'];

            if (empty($bank['name']) || empty($bank['account_number']) || empty($bank['account_holder'])) {
                throw new RuntimeException('銀行情報に必須項目が不足しています。');
            }
        }
    }
}
