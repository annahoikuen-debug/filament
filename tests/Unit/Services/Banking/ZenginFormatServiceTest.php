<?php

namespace Tests\Unit\Services\Banking;

use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\ResidentBankAccount;
use App\Services\Banking\ZenginFormatService;
use App\Services\Banking\ZenginResultParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZenginFormatServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ZenginFormatService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ZenginFormatService;
    }

    public function test_120バイト固定長でエンドレコードが生成される(): void
    {
        $content = $this->service->generate($this->header(), []);

        $lines = explode("\r\n", mb_convert_encoding($content, 'UTF-8', 'CP932'));
        $endLine = trim($lines[2]);

        $this->assertSame(120, strlen($endLine));
        $this->assertStringStartsWith('9', $endLine);
    }

    public function test_ヘッダーデータ_トレーラー_エンドレコードの構成(): void
    {
        [$invoice, $account] = $this->createEntry();

        $content = $this->service->generate($this->header(), [compact('invoice', 'account')]);

        // 先に全行UTF-8化するとマルチバイト拡張でバイト位置がずれるため、生バイトで検証する
        $lines = array_values(array_filter(explode("\r\n", $content), fn ($l) => $l !== ''));

        $this->assertCount(4, $lines);
        $this->assertSame('1', $lines[0][0]);
        $this->assertSame('2', $lines[1][0]);
        $this->assertSame('8', $lines[2][0]);
        $this->assertSame('9', $lines[3][0]);
        foreach ($lines as $line) {
            $this->assertSame(120, strlen($line));
        }
    }

    public function test_データレコードの数値は右詰めゼロ埋め(): void
    {
        [$invoice, $account] = $this->createEntry();

        $content = $this->service->generate($this->header(), [compact('invoice', 'account')]);
        $line = explode("\r\n", $content)[1]; // 生バイト（CP932）

        // 金額フィールド（78-87バイト目: 10桁 / レコード種別+銀行4+カナ15+支店3+カナ15+種別1+口座7+ダミー1+名義30 = 77バイト目まで）
        $amountField = substr($line, 77, 10);
        $this->assertMatchesRegularExpression('/^\d{10}$/', $amountField);
        $this->assertSame((string) $invoice->total_with_tax, ltrim($amountField, '0') ?: '0');
    }

    public function test_カナは左詰めスペース埋めでCP932エンコードされる(): void
    {
        [$invoice, $account] = $this->createEntry(['account_holder_kana' => 'ヤマダタロウ']);

        $content = $this->service->generate($this->header(), [compact('invoice', 'account')]);
        $line = explode("\r\n", mb_convert_encoding($content, 'UTF-8', 'CP932'))[1];

        // 名義フィールド（48-77バイト目: 30バイト）
        $holder = rtrim(substr($line, 47, 30));
        $this->assertSame('ヤマダタロウ', $holder);
    }

    public function test_CRLF改行コードが使用される(): void
    {
        [$invoice, $account] = $this->createEntry();

        $content = $this->service->generate($this->header(), [compact('invoice', 'account')]);

        $this->assertStringContainsString("\r\n", $content);
        $this->assertStringNotContainsString("\n", str_replace("\r\n", '', $content));
    }

    public function test_トレーラーに合計件数と合計金額が設定される(): void
    {
        $entries = [];
        for ($i = 0; $i < 2; $i++) {
            $entries[] = $this->createEntry();
        }

        $content = $this->service->generate($this->header(), array_map(fn ($e) => ['invoice' => $e[0], 'account' => $e[1]], $entries));
        $trailer = explode("\r\n", mb_convert_encoding($content, 'UTF-8', 'CP932'))[count($entries) + 1];

        $this->assertSame('8', $trailer[0]);
        $this->assertSame(2, (int) substr($trailer, 1, 6));

        $expectedTotal = array_sum(array_map(fn ($e) => $e[0]->total_with_tax, $entries));
        $this->assertSame($expectedTotal, (int) substr($trailer, 7, 12));
    }

    // ------------------------------------------------------------------

    protected function header(): array
    {
        return [
            'delegate_code' => '1234567890',
            'delegate_name_kana' => 'テストシセツ',
            'transfer_date' => '20261027',
            'bank_code' => '0001',
            'bank_name_kana' => 'テストギンコウ',
            'branch_code' => '001',
            'branch_name_kana' => 'ホンテン',
            'account_type' => '1',
            'account_number' => '1234567',
        ];
    }

    protected function createEntry(array $accountOverrides = []): array
    {
        $facility = Facility::factory()->create();
        $resident = Resident::create([
            'facility_id' => $facility->id,
            'room_number' => '101',
            'name' => '山田太郎',
            'name_kana' => 'ヤマダタロウ',
            'base_rent' => 100000,
            'base_management_fee' => 20000,
            'status' => 'active',
        ]);

        $account = ResidentBankAccount::create(array_merge([
            'resident_id' => $resident->id,
            'bank_code' => '0001',
            'branch_code' => '001',
            'account_type' => 1,
            'account_number' => '7654321',
            'account_holder_kana' => 'ヤマダタロウ',
            'is_active' => true,
        ], $accountOverrides));

        $invoice = MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'facility_id' => $facility->id,
            'billing_year_month' => '2026-10',
            'rent_subtotal' => 100000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 0,
            'status' => 'unbilled',
            'payment_method' => 'direct_debit',
            'tax_rate' => 10,
        ]);

        return [$invoice, $account];
    }
}
