<?php

namespace Tests\Feature\Accounting;

use App\Services\Accounting\Mcp\FreeeMcpClient;
use App\Services\Accounting\Mcp\MfMcpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccountingMcpClientTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // MfMcpClient
    // ------------------------------------------------------------------

    public function test_mf_接続テストが成功する(): void
    {
        Http::fake([
            'api.moneyforward.com/v1/offices/*' => Http::response(['id' => 1, 'name' => '事業所']),
        ]);

        $client = new MfMcpClient('token', 1);

        $this->assertTrue($client->testConnection());
        $this->assertSame('MFクラウド会計', $client->getProviderName());
    }

    public function test_mf_接続テストが失敗時にfalseを返す(): void
    {
        Http::fake([
            'api.moneyforward.com/v1/offices/*' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $client = new MfMcpClient('token', 1);

        $this->assertFalse($client->testConnection());
    }

    public function test_mf_仕訳同期が成功する(): void
    {
        Http::fake([
            'api.moneyforward.com/v1/offices/*' => Http::response(['id' => 1]),
            'api.moneyforward.com/v1/journals' => Http::response(['journal' => ['id' => 'J-1']], 201),
        ]);

        $client = new MfMcpClient('token', 1);

        $result = $client->syncJournalEntries([
            [
                'date' => '2026-10-01',
                'debit_account_code' => '101',
                'credit_account_code' => '201',
                'amount' => 100000,
                'description' => '10月分請求',
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['synced_count']);
        $this->assertSame(0, $result['failed_count']);
        $this->assertSame(['J-1'], $result['external_ids']);
    }

    public function test_mf_仕訳同期で補助科目_部門_タグが変換される(): void
    {
        Http::fake([
            'api.moneyforward.com/v1/offices/*' => Http::response(['id' => 1]),
            'api.moneyforward.com/v1/journals' => Http::response(['journal' => ['id' => 'J-2']], 201),
        ]);

        $client = new MfMcpClient('token', 1);

        $result = $client->syncJournalEntries([
            [
                'date' => '2026-10-01',
                'debit_account_code' => '101',
                'credit_account_code' => '201',
                'debit_sub_account_code' => '11',
                'credit_sub_account_code' => '22',
                'debit_department_code' => '5',
                'credit_department_code' => '6',
                'debit_tag_codes' => 'a, b',
                'credit_tag_codes' => 'b, c',
                'amount' => 50000,
                'description' => '補助科目テスト',
            ],
        ]);

        $this->assertTrue($result['success']);

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/journals')) {
                return false;
            }
            $journal = $request->data()['journal'];

            $this->assertSame(1, $journal['office_id']);
            $this->assertSame('2026-10-01', $journal['date']);
            $this->assertSame(2, count($journal['details']));
            $this->assertSame(11, $journal['details'][0]['sub_account_id']);
            $this->assertSame(22, $journal['details'][1]['sub_account_id']);
            $this->assertSame(5, $journal['details'][0]['department_id']);
            $this->assertSame(6, $journal['details'][1]['department_id']);
            // タグは重複排除・空除去
            $this->assertSame(['a', 'b', 'c'], array_values($journal['tag_ids']));

            return true;
        });
    }

    public function test_mf_仕訳同期で失敗が記録される(): void
    {
        Http::fake([
            'api.moneyforward.com/v1/offices/*' => Http::response(['id' => 1]),
            'api.moneyforward.com/v1/journals' => Http::response(['error' => 'bad request'], 400),
        ]);

        $client = new MfMcpClient('token', 1);

        $result = $client->syncJournalEntries([
            [
                'date' => '2026-10-01',
                'debit_account_code' => '101',
                'credit_account_code' => '201',
                'amount' => 100,
            ],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['synced_count']);
        $this->assertSame(1, $result['failed_count']);
        $this->assertCount(1, $result['errors']);
    }

    public function test_mf_空配列の同期は即時成功を返す(): void
    {
        $client = new MfMcpClient('token', 1);

        $result = $client->syncJournalEntries([]);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['synced_count']);
    }

    public function test_mf_マスタ取得系APIが動作する(): void
    {
        Http::fake([
            'api.moneyforward.com/v1/account_items*' => Http::response(['account_items' => [['id' => 1]]]),
            'api.moneyforward.com/v1/departments*' => Http::response(['departments' => [['id' => 2]]]),
            'api.moneyforward.com/v1/tags*' => Http::response(['tags' => [['id' => 3]]]),
            'api.moneyforward.com/v1/sub_accounts*' => Http::response(['sub_accounts' => [['id' => 4]]]),
            'api.moneyforward.com/v1/tax_codes*' => Http::response(['tax_codes' => [['id' => 5]]]),
        ]);

        $client = new MfMcpClient('token', 1);

        $this->assertSame([['id' => 1]], $client->getChartOfAccounts());
        $this->assertSame([['id' => 2]], $client->getDepartments());
        $this->assertSame([['id' => 4]], $client->getSubAccounts());
        $this->assertSame([['id' => 5]], $client->getTaxCodes());
    }

    public function test_mf_マスタ取得系APIは配列キーが無ければ空配列を返す(): void
    {
        Http::fake([
            'api.moneyforward.com/v1/*' => Http::response([]),
        ]);

        $client = new MfMcpClient('token', 1);

        $this->assertSame([], $client->getChartOfAccounts());
        $this->assertSame([], $client->getDepartments());
        $this->assertSame([], $client->getSubAccounts());
        $this->assertSame([], $client->getTaxCodes());
    }

    public function test_mf_トークンリフレッシュが成功する(): void
    {
        Http::fake([
            'api.moneyforward.com/oauth/token' => Http::response([
                'access_token' => 'new-token',
                'expires_in' => 3600,
            ]),
        ]);

        $client = new MfMcpClient('token', 1);

        $data = $client->refreshToken('cid', 'csecret', 'rtoken');

        $this->assertSame('new-token', $data['access_token']);
        $this->assertArrayHasKey('expires_at', $data);
    }

    public function test_mf_トークンリフレッシュが失敗時に例外を投げる(): void
    {
        Http::fake([
            'api.moneyforward.com/oauth/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $client = new MfMcpClient('token', 1);

        $this->expectException(\RuntimeException::class);
        $client->refreshToken('cid', 'csecret', 'rtoken');
    }

    // ------------------------------------------------------------------
    // FreeeMcpClient
    // ------------------------------------------------------------------

    public function test_freee_接続テストが成功する(): void
    {
        Http::fake([
            'api.freee.co.jp/api/1/companies/*' => Http::response(['company' => ['id' => 1]]),
        ]);

        $client = new FreeeMcpClient('token', 1);

        $this->assertTrue($client->testConnection());
        $this->assertSame('freee', $client->getProviderName());
    }

    public function test_freee_接続テストが失敗時にfalseを返す(): void
    {
        Http::fake([
            'api.freee.co.jp/api/1/companies/*' => Http::response([], 500),
        ]);

        $client = new FreeeMcpClient('token', 1);

        $this->assertFalse($client->testConnection());
    }

    public function test_freee_仕訳同期が成功する(): void
    {
        Http::fake([
            'api.freee.co.jp/api/1/companies/*' => Http::response(['company' => ['id' => 1]]),
            'api.freee.co.jp/api/1/deals' => Http::response(['deal' => ['id' => 999]], 201),
        ]);

        $client = new FreeeMcpClient('token', 1);

        $result = $client->syncJournalEntries([
            [
                'date' => '2026-10-01',
                'debit_account_code' => '101',
                'credit_account_code' => '201',
                'debit_sub_account_code' => '33',
                'credit_sub_account_code' => '44',
                'debit_department_code' => '7',
                'credit_department_code' => '8',
                'debit_tag_codes' => 'x, y',
                'credit_tag_codes' => 'y, z',
                'amount' => 30000,
                'description' => 'freeeテスト',
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(['999'], $result['external_ids']);

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), '/deals')) {
                return false;
            }
            $deal = $request->data()['deal'];

            $this->assertSame(1, $deal['company_id']);
            $this->assertSame('2026-10-01', $deal['issue_date']);
            $this->assertSame(33, $deal['details'][0]['partner_id']);
            $this->assertSame(44, $deal['details'][1]['partner_id']);
            $this->assertSame(7, $deal['details'][0]['section_id']);
            $this->assertSame(8, $deal['details'][1]['section_id']);
            $this->assertSame(['x', 'y', 'z'], array_values($deal['tags']));

            return true;
        });
    }

    public function test_freee_仕訳同期で失敗が記録される(): void
    {
        Http::fake([
            'api.freee.co.jp/api/1/companies/*' => Http::response(['company' => ['id' => 1]]),
            'api.freee.co.jp/api/1/deals' => Http::response([], 422),
        ]);

        $client = new FreeeMcpClient('token', 1);

        $result = $client->syncJournalEntries([
            [
                'date' => '2026-10-01',
                'debit_account_code' => '101',
                'credit_account_code' => '201',
                'amount' => 100,
            ],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(1, $result['failed_count']);
    }

    public function test_freee_マスタ取得系APIが動作する(): void
    {
        Http::fake([
            'api.freee.co.jp/api/1/account_items*' => Http::response(['account_items' => [['id' => 1]]]),
            'api.freee.co.jp/api/1/sections*' => Http::response(['sections' => [['id' => 2]]]),
            'api.freee.co.jp/api/1/tags*' => Http::response(['tags' => [['id' => 3]]]),
            'api.freee.co.jp/api/1/partners*' => Http::response(['partners' => [['id' => 4]]]),
            'api.freee.co.jp/api/1/tax_codes*' => Http::response(['tax_codes' => [['id' => 5]]]),
        ]);

        $client = new FreeeMcpClient('token', 1);

        $this->assertSame([['id' => 1]], $client->getChartOfAccounts());
        $this->assertSame([['id' => 2]], $client->getDepartments());
        $this->assertSame([['id' => 4]], $client->getSubAccounts());
        $this->assertSame([['id' => 5]], $client->getTaxCodes());
    }

    public function test_freee_トークンリフレッシュが成功する(): void
    {
        Http::fake([
            'accounts.freee.co.jp/public_api/token' => Http::response([
                'access_token' => 'freee-new-token',
                'expires_in' => 7200,
            ]),
        ]);

        $client = new FreeeMcpClient('token', 1);

        $data = $client->refreshToken('cid', 'csecret', 'rtoken');

        $this->assertSame('freee-new-token', $data['access_token']);
        $this->assertArrayHasKey('expires_at', $data);
    }

    public function test_freee_トークンリフレッシュが失敗時に例外を投げる(): void
    {
        Http::fake([
            'accounts.freee.co.jp/public_api/token' => Http::response([], 401),
        ]);

        $client = new FreeeMcpClient('token', 1);

        $this->expectException(\RuntimeException::class);
        $client->refreshToken('cid', 'csecret', 'rtoken');
    }
}
