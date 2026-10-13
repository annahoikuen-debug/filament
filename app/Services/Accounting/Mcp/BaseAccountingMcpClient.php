<?php

namespace App\Services\Accounting\Mcp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseAccountingMcpClient implements AccountingMcpClientInterface
{
    public function __construct(
        protected string $baseUrl,
        protected string $accessToken,
        protected array $options = [],
    ) {}

    abstract public function getProviderName(): string;

    abstract protected function transformJournalEntry(array $entry): array;

    abstract protected function doCreateJournalEntry(array $entry): array;

    abstract protected function extractExternalId(array $response): string;

    abstract protected function doTestConnection(): void;

    abstract protected function doGetChartOfAccounts(): array;

    abstract protected function doGetDepartments(): array;

    abstract protected function doGetSubAccounts(): array;

    abstract protected function doGetTaxCodes(): array;

    public function testConnection(): bool
    {
        try {
            $this->doTestConnection();

            return true;
        } catch (\Throwable $e) {
            Log::warning("[{$this->getProviderName()}] Connection test failed: {$e->getMessage()}");

            return false;
        }
    }

    public function syncJournalEntries(array $entries): array
    {
        if (empty($entries)) {
            return [
                'success' => true,
                'synced_count' => 0,
                'failed_count' => 0,
                'errors' => [],
                'external_ids' => [],
            ];
        }

        // 接続テスト
        if (! $this->testConnection()) {
            return [
                'success' => false,
                'synced_count' => 0,
                'failed_count' => count($entries),
                'errors' => ["{$this->getProviderName()} への接続テストに失敗しました。認証情報を確認してください。"],
                'external_ids' => [],
            ];
        }

        $syncedCount = 0;
        $failedCount = 0;
        $errors = [];
        $externalIds = [];

        foreach ($entries as $index => $entry) {
            try {
                $payload = $this->transformJournalEntry($entry);
                $response = $this->doCreateJournalEntry($payload);
                $externalId = $this->extractExternalId($response);

                $externalIds[] = $externalId;
                $syncedCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                $errors[] = "仕訳 #{$index} 同期失敗: {$e->getMessage()}";
                Log::error("[{$this->getProviderName()}] Failed to sync entry #{$index}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => $failedCount === 0,
            'synced_count' => $syncedCount,
            'failed_count' => $failedCount,
            'errors' => $errors,
            'external_ids' => $externalIds,
        ];
    }

    public function getChartOfAccounts(): array
    {
        return $this->doGetChartOfAccounts();
    }

    public function getDepartments(): array
    {
        return $this->doGetDepartments();
    }

    public function getSubAccounts(): array
    {
        return $this->doGetSubAccounts();
    }

    public function getTaxCodes(): array
    {
        return $this->doGetTaxCodes();
    }

    protected function getHttpClient()
    {
        $timeout = $this->options['timeout'] ?? 30;

        return Http::baseUrl($this->baseUrl)
            ->withToken($this->accessToken)
            ->timeout($timeout)
            ->acceptJson();
    }

    protected function get(string $path, array $query = []): array
    {
        $response = $this->getHttpClient()->get($path, $query);

        if ($response->failed()) {
            throw new \RuntimeException("[{$this->getProviderName()}] GET {$path} failed ({$response->status()}): {$response->body()}");
        }

        return $response->json() ?? [];
    }

    protected function post(string $path, array $data = []): array
    {
        $response = $this->getHttpClient()->post($path, $data);

        if ($response->failed()) {
            throw new \RuntimeException("[{$this->getProviderName()}] POST {$path} failed ({$response->status()}): {$response->body()}");
        }

        return $response->json() ?? [];
    }

    protected function put(string $path, array $data = []): array
    {
        $response = $this->getHttpClient()->put($path, $data);

        if ($response->failed()) {
            throw new \RuntimeException("[{$this->getProviderName()}] PUT {$path} failed ({$response->status()}): {$response->body()}");
        }

        return $response->json() ?? [];
    }

    protected function delete(string $path): bool
    {
        $response = $this->getHttpClient()->delete($path);

        if ($response->failed()) {
            throw new \RuntimeException("[{$this->getProviderName()}] DELETE {$path} failed ({$response->status()}): {$response->body()}");
        }

        return true;
    }
}
