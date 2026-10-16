<?php

namespace Tests\Feature\Banking;

use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\ResidentBankAccount;
use App\Services\Banking\ZenginFormatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZenginFormatEncryptedDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_zengin_format_generates_correct_fixed_length_records_from_encrypted_accounts(): void
    {
        $facility = Facility::factory()->create([
            'name' => 'テスト介護施設',
        ]);

        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'name' => '山田太郎',
            'name_kana' => 'ヤマダタロウ',
        ]);

        // 暗号化キャストを通して保存される口座情報
        $account = ResidentBankAccount::create([
            'resident_id' => $resident->id,
            'bank_code' => '0001',
            'branch_code' => '123',
            'account_type' => ResidentBankAccount::ACCOUNT_TYPE_ORDINARY,
            'account_number' => '1234567',
            'account_holder_kana' => 'ヤマダタロウ',
            'delegate_code' => '9999999999',
            'is_active' => true,
        ]);

        $invoice = MonthlyInvoice::factory()->create([
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'total_amount' => 50000,
            'tax_amount' => 5000,
        ]);

        $service = app(ZenginFormatService::class);

        $header = [
            'delegate_code' => '9999999999',
            'delegate_name_kana' => 'テストシセツ',
            'transfer_date' => '20261027',
            'bank_code' => '0001',
            'bank_name_kana' => 'ミズホ',
            'branch_code' => '001',
            'branch_name_kana' => 'ホンテン',
            'account_type' => '1',
            'account_number' => '7654321',
        ];

        $entries = [
            [
                'invoice' => $invoice,
                'account' => $account,
            ],
        ];

        $content = $service->generate($header, $entries);

        // 改行で分割して行単位でチェック（CRLF）
        $lines = explode("\r\n", rtrim($content, "\r\n"));

        // ヘッダー(1), データ(2), トレーラー(8), エンド(9) の4行
        $this->assertCount(4, $lines);

        // データレコードの検証（2行目）
        $dataRecord = $lines[1];

        // 全銀フォーマットは各行120バイト（CP932/ASCII）
        $this->assertEquals(ZenginFormatService::LINE_LENGTH, strlen($dataRecord));

        // 復号された銀行コード、支店コード、口座番号が含まれていること
        // [0:1] = レコード区分 '2'
        // [1:4] (offset 1, len 4) = 銀行コード '0001'
        // [5:15] (offset 5, len 15) = 銀行名カナ
        // [20:3] (offset 20, len 3) = 支店コード '123'
        // [23:15] (offset 23, len 15) = 支店名カナ
        // [38:1] (offset 38, len 1) = 預金種目 '1'
        // [39:7] (offset 39, len 7) = 口座番号 '1234567'
        $this->assertEquals('0001', substr($dataRecord, 1, 4)); // 銀行コード
        $this->assertEquals('123', substr($dataRecord, 20, 3));  // 支店コード
        $this->assertEquals('1234567', substr($dataRecord, 39, 7)); // 口座番号
        $this->assertStringContainsString(mb_convert_encoding('ヤマダタロウ', 'CP932', 'UTF-8'), substr($dataRecord, 47, 30));
    }
}
