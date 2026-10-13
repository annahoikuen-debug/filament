<?php

namespace App\Services\Accounting\Mcp;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class MfMcpClient extends BaseAccountingMcpClient
{
    protected int $officeId;

    public function __construct(string $accessToken, int $officeId, array $options = [])
    {
        parent::__construct('https://api.moneyforward.com/v1', $accessToken, $options);
        $this->officeId = $officeId;
    }

    public function getProviderName(): string
    {
        return 'MFクラウド会計';
    }

    /**
     * MF API用に仕訳データを変換
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    protected function transformJournalEntry(array $entry): array
    {
        $details = [];

        // 借方
        $details[] = [
            'account_item_id' => $this->resolveAccountItemId($entry['debit_account_code']),
            'amount' => (int) $entry['amount'],
            'dc' => 'debit',
            'description' => $entry['description'] ?? '',
        ];

        // 貸方
        $details[] = [
            'account_item_id' => $this->resolveAccountItemId($entry['credit_account_code']),
            'amount' => (int) $entry['amount'],
            'dc' => 'credit',
            'description' => $entry['description'] ?? '',
        ];

        // 補助科目がある場合
        if (! empty($entry['debit_sub_account_code'])) {
            $details[0]['sub_account_id'] = $this->resolveSubAccountId($entry['debit_sub_account_code']);
        }
        if (! empty($entry['credit_sub_account_code'])) {
            $details[1]['sub_account_id'] = $this->resolveSubAccountId($entry['credit_sub_account_code']);
        }

        // 部門がある場合
        if (! empty($entry['debit_department_code'])) {
            $details[0]['department_id'] = $this->resolveDepartmentId($entry['debit_department_code']);
        }
        if (! empty($entry['credit_department_code'])) {
            $details[1]['department_id'] = $this->resolveDepartmentId($entry['credit_department_code']);
        }

        // タグがある場合
        $tagIds = [];
        if (! empty($entry['debit_tag_codes'])) {
            $tagIds = array_merge($tagIds, array_map('trim', explode(',', $entry['debit_tag_codes'])));
        }
        if (! empty($entry['credit_tag_codes'])) {
            $tagIds = array_merge($tagIds, array_map('trim', explode(',', $entry['credit_tag_codes'])));
        }
        $tagIds = array_unique(array_filter($tagIds));

        return [
            'office_id' => $this->officeId,
            'date' => $entry['date'],
            'details' => $details,
            'description' => $entry['description'] ?? '',
            'tag_ids' => $tagIds,
        ];
    }

    /**
     * 勘定科目コードから MF の account_item_id を解決
     */
    protected function resolveAccountItemId(string $accountCode): int
    {
        return (int) $accountCode;
    }

    /**
     * 補助科目コードから sub_account_id を解決
     */
    protected function resolveSubAccountId(string $subAccountCode): int
    {
        return (int) $subAccountCode;
    }

    /**
     * 部門コードから department_id を解決
     */
    protected function resolveDepartmentId(string $departmentCode): int
    {
        return (int) $departmentCode;
    }

    /**
     * APIレスポンスから外部IDを抽出
     */
    protected function extractExternalId(array $response): string
    {
        return (string) ($response['journal']['id'] ?? $response['id'] ?? '');
    }

    /**
     * 実際の仕訳作成API呼び出し
     */
    protected function doCreateJournalEntry(array $entry): array
    {
        $response = $this->post('/journals', [
            'journal' => $entry,
        ]);

        return $response;
    }

    /**
     * 実際の仕訳更新API呼び出し
     */
    protected function doUpdateJournalEntry(string $externalId, array $entry): array
    {
        $response = $this->put("/journals/{$externalId}", [
            'journal' => $entry,
        ]);

        return $response;
    }

    /**
     * 実際の仕訳削除API呼び出し
     */
    protected function doDeleteJournalEntry(string $externalId): bool
    {
        $this->delete("/journals/{$externalId}");

        return true;
    }

    /**
     * 実際の勘定科目取得API呼び出し
     *
     * @return array<int, array<string, mixed>>
     */
    protected function doGetChartOfAccounts(): array
    {
        $response = $this->get('/account_items', [
            'office_id' => $this->officeId,
        ]);

        return $response['account_items'] ?? [];
    }

    /**
     * 実際の部門取得API呼び出し
     *
     * @return array<int, array<string, mixed>>
     */
    protected function doGetDepartments(): array
    {
        $response = $this->get('/departments', [
            'office_id' => $this->officeId,
        ]);

        return $response['departments'] ?? [];
    }

    /**
     * 実際のタグ取得API呼び出し
     *
     * @return array<int, array<string, mixed>>
     */
    protected function doGetTags(): array
    {
        $response = $this->get('/tags', [
            'office_id' => $this->officeId,
        ]);

        return $response['tags'] ?? [];
    }

    /**
     * 実際の補助科目取得API呼び出し
     *
     * @return array<int, array<string, mixed>>
     */
    protected function doGetSubAccounts(): array
    {
        $response = $this->get('/sub_accounts', [
            'office_id' => $this->officeId,
        ]);

        return $response['sub_accounts'] ?? [];
    }

    /**
     * 実際の税区分取得API呼び出し
     *
     * @return array<int, array<string, mixed>>
     */
    protected function doGetTaxCodes(): array
    {
        $response = $this->get('/tax_codes', [
            'office_id' => $this->officeId,
        ]);

        return $response['tax_codes'] ?? [];
    }

    /**
     * 実際の接続テスト
     */
    protected function doTestConnection(): void
    {
        $this->get("/offices/{$this->officeId}");
    }

    /**
     * トークンリフレッシュ（外部から呼び出し用）
     */
    public function refreshToken(string $clientId, string $clientSecret, string $refreshToken): array
    {
        $response = Http::asForm()->post('https://api.moneyforward.com/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('MF トークンリフレッシュ失敗: '.$response->body());
        }

        $data = $response->json();
        $data['expires_at'] = Carbon::now()->addSeconds($data['expires_in'] ?? 86400)->toDateTimeString();

        return $data;
    }
}
