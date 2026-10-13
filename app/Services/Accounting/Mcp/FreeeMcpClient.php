<?php

namespace App\Services\Accounting\Mcp;

use Carbon\Carbon;

class FreeeMcpClient extends BaseAccountingMcpClient
{
    protected int $companyId;

    public function __construct(string $accessToken, int $companyId, array $options = [])
    {
        parent::__construct('https://api.freee.co.jp/api/1', $accessToken, $options);
        $this->companyId = $companyId;
    }

    public function getProviderName(): string
    {
        return 'freee';
    }

    /**
     * freee API用に仕訳データを変換
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
            'side' => 'debit',
            'description' => $entry['description'] ?? '',
        ];

        // 貸方
        $details[] = [
            'account_item_id' => $this->resolveAccountItemId($entry['credit_account_code']),
            'amount' => (int) $entry['amount'],
            'side' => 'credit',
            'description' => $entry['description'] ?? '',
        ];

        // 補助科目がある場合
        if (! empty($entry['debit_sub_account_code'])) {
            $details[0]['partner_id'] = $this->resolvePartnerId($entry['debit_sub_account_code']);
        }
        if (! empty($entry['credit_sub_account_code'])) {
            $details[1]['partner_id'] = $this->resolvePartnerId($entry['credit_sub_account_code']);
        }

        // 部門がある場合
        if (! empty($entry['debit_department_code'])) {
            $details[0]['section_id'] = $this->resolveSectionId($entry['debit_department_code']);
        }
        if (! empty($entry['credit_department_code'])) {
            $details[1]['section_id'] = $this->resolveSectionId($entry['credit_department_code']);
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
            'company_id' => $this->companyId,
            'issue_date' => $entry['date'],
            'details' => $details,
            'description' => $entry['description'] ?? '',
            'tags' => $tagIds,
        ];
    }

    /**
     * 勘定科目コードから freee の account_item_id を解決
     * 実際の実装ではキャッシュやマスタAPIを使用してマッピングする
     */
    protected function resolveAccountItemId(string $accountCode): int
    {
        // ここでは簡易的にコードをそのまま使用（実際はIDマッピングが必要）
        // 本番では getChartOfAccounts() で取得したマスタから検索する
        return (int) $accountCode;
    }

    /**
     * 補助科目コードから partner_id を解決
     */
    protected function resolvePartnerId(string $subAccountCode): int
    {
        return (int) $subAccountCode;
    }

    /**
     * 部門コードから section_id を解決
     */
    protected function resolveSectionId(string $departmentCode): int
    {
        return (int) $departmentCode;
    }

    /**
     * APIレスポンスから外部IDを抽出
     */
    protected function extractExternalId(array $response): string
    {
        return (string) ($response['deal']['id'] ?? $response['id'] ?? '');
    }

    /**
     * 実際の仕訳作成API呼び出し
     */
    protected function doCreateJournalEntry(array $entry): array
    {
        $response = $this->post('/deals', [
            'deal' => $entry,
        ]);

        return $response;
    }

    /**
     * 実際の仕訳更新API呼び出し
     */
    protected function doUpdateJournalEntry(string $externalId, array $entry): array
    {
        $response = $this->put("/deals/{$externalId}", [
            'deal' => $entry,
        ]);

        return $response;
    }

    /**
     * 実際の仕訳削除API呼び出し
     */
    protected function doDeleteJournalEntry(string $externalId): bool
    {
        $this->delete("/deals/{$externalId}");

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
            'company_id' => $this->companyId,
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
        $response = $this->get('/sections', [
            'company_id' => $this->companyId,
        ]);

        return $response['sections'] ?? [];
    }

    /**
     * 実際のタグ取得API呼び出し
     *
     * @return array<int, array<string, mixed>>
     */
    protected function doGetTags(): array
    {
        $response = $this->get('/tags', [
            'company_id' => $this->companyId,
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
        // freeeでは補助科目は「取引先」として扱われる
        $response = $this->get('/partners', [
            'company_id' => $this->companyId,
        ]);

        return $response['partners'] ?? [];
    }

    /**
     * 実際の税区分取得API呼び出し
     *
     * @return array<int, array<string, mixed>>
     */
    protected function doGetTaxCodes(): array
    {
        $response = $this->get('/tax_codes', [
            'company_id' => $this->companyId,
        ]);

        return $response['tax_codes'] ?? [];
    }

    /**
     * 実際の接続テスト
     */
    protected function doTestConnection(): void
    {
        $this->get("/companies/{$this->companyId}");
    }

    /**
     * トークンリフレッシュ（外部から呼び出し用）
     */
    public function refreshToken(string $clientId, string $clientSecret, string $refreshToken): array
    {
        $response = Http::asForm()->post('https://accounts.freee.co.jp/public_api/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('freee トークンリフレッシュ失敗: '.$response->body());
        }

        $data = $response->json();
        $data['expires_at'] = Carbon::now()->addSeconds($data['expires_in'] ?? 86400)->toDateTimeString();

        return $data;
    }
}
