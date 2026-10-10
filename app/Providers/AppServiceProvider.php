<?php

namespace App\Providers;

use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\PdfTemplateSetting;
use App\Models\TaxSetting;
use App\Policies\MonthlyInvoicePolicy;
use App\Services\FacilityConfigService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // FacilityConfigService をシングルトンとして登録
        $this->app->singleton(FacilityConfigService::class);
    }

    public function boot(): void
    {
        $this->loadFacilityConfigFromDatabase();
        $this->loadTaxConfigFromDatabase();
        $this->loadPdfTemplateConfigFromDatabase();
        $this->validateFacilityConfig();

        // ポリシー登録
        Gate::policy(MonthlyInvoice::class, MonthlyInvoicePolicy::class);

        $this->registerRateLimiters();
    }

    /**
     * 公開APIのレート制限定義（不正利用防止）
     */
    protected function registerRateLimiters(): void
    {
        RateLimiter::for('trial-create', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('trial-convert', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('booking-create', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('quote-send', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
    }

    /**
     * DBから施設設定を読み込み、config にマージする（後方互換性用・非推奨）
     *
     * @deprecated Use FacilityConfigService instead. This method is kept for backward compatibility
     *             during transition period. config('facility') will be removed in future versions.
     */
    protected function loadFacilityConfigFromDatabase(): void
    {
        // Skip during console commands and unit tests
        if ($this->app->runningInConsole() || $this->app->runningUnitTests()) {
            return;
        }

        try {
            // テーブル存在チェック（マイグレーション前対策）
            if (! $this->getConnection()->getSchemaBuilder()->hasTable('facilities')) {
                return;
            }

            $facility = Facility::current();
            if ($facility) {
                Config::set('facility', array_merge(Config::get('facility', []), $facility->toConfigArray()));
            }
        } catch (\Throwable $e) {
            // DB接続エラー等はログに出して継続（config/facility.php のデフォルト値が使われる）
            report($e);
        }
    }

    /**
     * DBから税率設定を読み込み、config にマージする
     */
    protected function loadTaxConfigFromDatabase(): void
    {
        if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
            // Webリクエスト時のみDBから読み込み
        }

        try {
            if (! $this->getConnection()->getSchemaBuilder()->hasTable('tax_settings')) {
                return;
            }

            $taxSetting = TaxSetting::current();
            if ($taxSetting) {
                Config::set('tax', array_merge(Config::get('tax', []), $taxSetting->toConfigArray()));
            }
        } catch (\Throwable $e) {
            if (! $this->app->runningInConsole()) {
                report($e);
            }
        }
    }

    /**
     * DBからPDFテンプレート設定を読み込み、config にマージする
     */
    protected function loadPdfTemplateConfigFromDatabase(): void
    {
        if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
            // Webリクエスト時のみDBから読み込み
        }

        try {
            if (! $this->getConnection()->getSchemaBuilder()->hasTable('pdf_template_settings')) {
                return;
            }

            // 請求書テンプレート
            $invoiceTemplate = PdfTemplateSetting::getForKey('invoice');
            if ($invoiceTemplate) {
                Config::set('pdf.invoice', array_merge(Config::get('pdf.invoice', []), $invoiceTemplate->toConfigArray()));
            }

            // 領収書テンプレート
            $receiptTemplate = PdfTemplateSetting::getForKey('receipt');
            if ($receiptTemplate) {
                Config::set('pdf.receipt', array_merge(Config::get('pdf.receipt', []), $receiptTemplate->toConfigArray()));
            }
        } catch (\Throwable $e) {
            if (! $this->app->runningInConsole()) {
                report($e);
            }
        }
    }

    /**
     * 施設設定のバリデーション（DB由来も含む）
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

    private function getConnection()
    {
        return $this->app['db']->connection();
    }
}
