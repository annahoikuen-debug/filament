<?php

namespace App\Services\Accounting;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceInvoiceStatus;
use App\Models\AccountingExportProfile;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\ServiceInvoice;
use App\Services\Accounting\Mcp\AccountingMcpClientFactory;
use App\Services\InvoiceCsvExportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class AccountingSyncService
{
    public function __construct(
        private InvoiceCsvExportService $csvExportService,
    ) {}

    /**
     * 指定年月の仕訳を会計ソフトへ同期する
     *
     * @return array{success: bool, synced_count: int, failed_count: int, errors: array<int, string>, provider: string, facility_id: int, year_month: string}
     */
    public function syncForMonth(
        string $yearMonth,
        int $facilityId,
        ?string $softwareType = null,
        ?int $profileId = null,
        bool $includeCareServices = true
    ): array {
        $facility = Facility::findOrFail($facilityId);
        $softwareType = $softwareType ?? $this->resolveSoftwareType($facility, $profileId);

        $client = AccountingMcpClientFactory::createForFacility($facility, $softwareType);

        if (! $client) {
            $errorMsg = "会計APIクライアントの作成に失敗しました。認証情報を確認してください。 (software: {$softwareType})";
            Log::error('[AccountingSync] Client creation failed', [
                'facility_id' => $facilityId,
                'software_type' => $softwareType,
            ]);

            return [
                'success' => false,
                'synced_count' => 0,
                'failed_count' => 0,
                'errors' => [$errorMsg],
                'provider' => $softwareType,
                'facility_id' => $facilityId,
                'year_month' => $yearMonth,
            ];
        }

        // 仕訳データ生成
        $profile = $this->csvExportService->resolveProfilePublic($facilityId, $softwareType, $profileId);
        $journalEntries = $this->buildJournalEntriesForSync($yearMonth, $facilityId, $profile, $includeCareServices);

        if (empty($journalEntries)) {
            return [
                'success' => true,
                'synced_count' => 0,
                'failed_count' => 0,
                'errors' => [],
                'provider' => $softwareType,
                'facility_id' => $facilityId,
                'year_month' => $yearMonth,
            ];
        }

        // 接続テスト
        if (! $client->testConnection()) {
            $errorMsg = "{$client->getProviderName()} への接続テストに失敗しました。";
            Log::error('[AccountingSync] Connection test failed', [
                'facility_id' => $facilityId,
                'provider' => $client->getProviderName(),
            ]);

            return [
                'success' => false,
                'synced_count' => 0,
                'failed_count' => count($journalEntries),
                'errors' => [$errorMsg],
                'provider' => $softwareType,
                'facility_id' => $facilityId,
                'year_month' => $yearMonth,
            ];
        }

        // 同期実行
        Log::info('[AccountingSync] Starting sync', [
            'facility_id' => $facilityId,
            'provider' => $client->getProviderName(),
            'year_month' => $yearMonth,
            'entries_count' => count($journalEntries),
        ]);

        $result = $client->syncJournalEntries($journalEntries);

        // 活動ログ記録
        $this->logSyncActivity($facility, $yearMonth, $softwareType, $result);

        return array_merge($result, [
            'provider' => $softwareType,
            'facility_id' => $facilityId,
            'year_month' => $yearMonth,
        ]);
    }

    /**
     * 請求書ステータス変更時の増分同期（入金済みになった請求書のみ）
     */
    public function syncPaidInvoices(
        Collection $invoices,
        ?string $softwareType = null,
        ?int $profileId = null
    ): array {
        if ($invoices->isEmpty()) {
            return ['success' => true, 'synced_count' => 0, 'failed_count' => 0, 'errors' => []];
        }

        $facilityId = $invoices->first()->facility_id;
        $facility = Facility::findOrFail($facilityId);
        $softwareType = $softwareType ?? $this->resolveSoftwareType($facility, $profileId);

        $client = AccountingMcpClientFactory::createForFacility($facility, $softwareType);

        if (! $client) {
            return ['success' => false, 'synced_count' => 0, 'failed_count' => $invoices->count(), 'errors' => ['APIクライアント作成失敗']];
        }

        $profile = $this->csvExportService->resolveProfilePublic($facilityId, $softwareType, $profileId);
        $journalEntries = $this->buildJournalEntriesFromInvoices($invoices, $profile);

        return array_merge($client->syncJournalEntries($journalEntries), [
            'provider' => $softwareType,
            'facility_id' => $facilityId,
        ]);
    }

    /**
     * 利用可能な会計ソフト一覧を取得（施設ごと）
     */
    public function getAvailableProviders(int $facilityId): array
    {
        $facility = Facility::findOrFail($facilityId);
        $providers = [];

        if ($facility->hasFreeeCredentials()) {
            $providers[] = [
                'key' => Facility::SOFTWARE_FREEE,
                'name' => 'freee',
                'configured' => true,
                'token_valid' => $facility->isFreeeTokenValid(),
            ];
        } else {
            $providers[] = [
                'key' => Facility::SOFTWARE_FREEE,
                'name' => 'freee',
                'configured' => false,
                'token_valid' => false,
            ];
        }

        if ($facility->hasMfCredentials()) {
            $providers[] = [
                'key' => Facility::SOFTWARE_MF,
                'name' => 'MFクラウド会計',
                'configured' => true,
                'token_valid' => $facility->isMfTokenValid(),
            ];
        } else {
            $providers[] = [
                'key' => Facility::SOFTWARE_MF,
                'name' => 'MFクラウド会計',
                'configured' => false,
                'token_valid' => false,
            ];
        }

        return $providers;
    }

    /**
     * 会計ソフト種類を解決
     */
    private function resolveSoftwareType(Facility $facility, ?int $profileId): string
    {
        if ($profileId) {
            $profile = AccountingExportProfile::find($profileId);
            if ($profile) {
                return $profile->software_type;
            }
        }

        // デフォルト: 設定済みのものを優先
        if ($facility->hasFreeeCredentials()) {
            return Facility::SOFTWARE_FREEE;
        }
        if ($facility->hasMfCredentials()) {
            return Facility::SOFTWARE_MF;
        }

        return Facility::SOFTWARE_FREEE; // デフォルト
    }

    /**
     * 同期用仕訳データを構築（CSVエクスポートと同じロジックを使用）
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildJournalEntriesForSync(
        string $yearMonth,
        int $facilityId,
        AccountingExportProfile $profile,
        bool $includeCareServices
    ): array {
        // 請求データ取得（住居費）
        $query = MonthlyInvoice::with('resident.facility')
            ->forYearMonth($yearMonth)
            ->whereIn('status', [InvoiceStatus::Billed, InvoiceStatus::Paid])
            ->where('facility_id', $facilityId)
            ->orderBy('resident_id');

        $invoices = $query->get();

        // 介護サービス請求データ取得（オプション）
        $careServiceInvoices = new Collection;
        if ($includeCareServices) {
            $careQuery = ServiceInvoice::with('resident.facility')
                ->where('billing_year_month', $yearMonth)
                ->whereIn('status', [ServiceInvoiceStatus::Confirmed, ServiceInvoiceStatus::Sent])
                ->where('facility_id', $facilityId)
                ->orderBy('resident_id');

            $careServiceInvoices = $careQuery->get();
        }

        if ($invoices->isEmpty() && $careServiceInvoices->isEmpty()) {
            return [];
        }

        // 勘定科目マスタ取得
        $allFacilityIds = $invoices->pluck('facility_id')
            ->merge($careServiceInvoices->pluck('facility_id'))
            ->unique()
            ->toArray();
        $chartOfAccounts = $this->csvExportService->loadChartOfAccountsPublic($allFacilityIds);

        // 仕訳データ生成（InvoiceCsvExportServiceのロジックを再利用）
        return $this->csvExportService->buildJournalEntriesPublic($invoices, $careServiceInvoices, $chartOfAccounts, $profile);
    }

    /**
     * 指定請求書から仕訳データを構築
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildJournalEntriesFromInvoices(Collection $invoices, AccountingExportProfile $profile): array
    {
        if ($invoices->isEmpty()) {
            return [];
        }

        $facilityIds = $invoices->pluck('facility_id')->unique()->toArray();
        $chartOfAccounts = $this->csvExportService->loadChartOfAccountsPublic($facilityIds);

        $careServiceInvoices = new Collection; // 介護サービスは含めない

        return $this->csvExportService->buildJournalEntriesPublic($invoices, $careServiceInvoices, $chartOfAccounts, $profile);
    }

    /**
     * 同期活動ログを記録
     */
    private function logSyncActivity(Facility $facility, string $yearMonth, string $softwareType, array $result): void
    {
        $description = sprintf(
            '会計同期実施: %s 年月 %s, プロバイダ %s, 成功 %d件, 失敗 %d件',
            $yearMonth,
            $softwareType,
            $result['synced_count'],
            $result['failed_count']
        );

        activity('accounting_sync')
            ->performedOn($facility)
            ->withProperties([
                'year_month' => $yearMonth,
                'software_type' => $softwareType,
                'synced_count' => $result['synced_count'],
                'failed_count' => $result['failed_count'],
                'errors' => $result['errors'],
                'external_ids' => $result['external_ids'] ?? [],
            ])
            ->log($description);
    }
}
