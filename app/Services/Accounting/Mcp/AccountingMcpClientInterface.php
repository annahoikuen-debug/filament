<?php

namespace App\Services\Accounting\Mcp;

interface AccountingMcpClientInterface
{
    /**
     * プロバイダー名を取得
     */
    public function getProviderName(): string;

    /**
     * 接続テストを実行
     */
    public function testConnection(): bool;

    /**
     * 複数仕訳を一括同期
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array{success: bool, synced_count: int, failed_count: int, errors: array<int, string>, external_ids: array<int, string>}
     */
    public function syncJournalEntries(array $entries): array;

    /**
     * 勘定科目マスタを取得
     *
     * @return array<int, array<string, mixed>>
     */
    public function getChartOfAccounts(): array;

    /**
     * 部門マスタを取得
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDepartments(): array;

    /**
     * 補助科目マスタを取得
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSubAccounts(): array;

    /**
     * 税区分マスタを取得
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTaxCodes(): array;
}
