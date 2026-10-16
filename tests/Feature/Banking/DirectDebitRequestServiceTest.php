<?php

namespace Tests\Feature\Banking;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\ResidentBankAccount;
use App\Services\Banking\DirectDebitRequestService;
use App\Services\Banking\ZenginFormatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectDebitRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DirectDebitRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DirectDebitRequestService(new ZenginFormatService);
    }

    public function test_口座振替対象の請求のみ抽出される(): void
    {
        [$facility, $resident] = $this->createFacilityAndResident();
        ResidentBankAccount::create([
            'resident_id' => $resident->id,
            'bank_code' => '0001',
            'branch_code' => '001',
            'account_type' => 1,
            'account_number' => '1234567',
            'account_holder_kana' => 'テストタロウ',
            'is_active' => true,
        ]);

        $target = $this->createInvoice($facility, $resident, 'unbilled', 'direct_debit');

        // 除外対象: 口座引落以外（別入居者）
        [$facility2, $r2] = $this->createFacilityAndResident('0002');
        $this->createInvoice($facility2, $r2, 'unbilled', 'bank_transfer');
        // 除外対象: 未請求以外（別入居者）
        [$facility3, $r3] = $this->createFacilityAndResident('0003');
        $this->createInvoice($facility3, $r3, 'paid', 'direct_debit');
        // 除外対象: 口座がない入居者
        [$facility4, $other] = $this->createFacilityAndResident('0004');
        $this->createInvoice($facility4, $other, 'unbilled', 'direct_debit');

        $entries = $this->service->collectEntries('2026-10');

        $this->assertCount(1, $entries);
        $this->assertSame($target->id, $entries[0]['invoice']->id);
        $this->assertSame($resident->id, $entries[0]['account']->resident_id);
    }

    public function test_無効な口座は除外される(): void
    {
        [$facility, $resident] = $this->createFacilityAndResident();
        ResidentBankAccount::create([
            'resident_id' => $resident->id,
            'bank_code' => '0001',
            'branch_code' => '001',
            'account_type' => 1,
            'account_number' => '1234567',
            'account_holder_kana' => 'テストタロウ',
            'is_active' => false,
        ]);
        $this->createInvoice($facility, $resident, 'unbilled', 'direct_debit');

        $this->assertCount(0, $this->service->collectEntries('2026-10'));
    }

    public function test_施設IDによるフィルタが機能する(): void
    {
        [$facility1, $resident1] = $this->createFacilityAndResident('0001');
        [$facility2, $resident2] = $this->createFacilityAndResident('0002');

        foreach ([$resident1, $resident2] as $r) {
            ResidentBankAccount::create([
                'resident_id' => $r->id,
                'bank_code' => '0001',
                'branch_code' => '001',
                'account_type' => 1,
                'account_number' => '1234567',
                'account_holder_kana' => 'テストタロウ',
                'is_active' => true,
            ]);
        }
        $this->createInvoice($facility1, $resident1, 'unbilled', 'direct_debit');
        $this->createInvoice($facility2, $resident2, 'unbilled', 'direct_debit');

        $entries = $this->service->collectEntries('2026-10', $facility1->id);

        $this->assertCount(1, $entries);
        $this->assertSame($facility1->id, $entries[0]['invoice']->facility_id);
    }

    public function test_全銀ファイルが生成される(): void
    {
        [$facility, $resident] = $this->createFacilityAndResident();
        ResidentBankAccount::create([
            'resident_id' => $resident->id,
            'bank_code' => '0001',
            'branch_code' => '001',
            'account_type' => 1,
            'account_number' => '1234567',
            'account_holder_kana' => 'テストタロウ',
            'is_active' => true,
        ]);
        $this->createInvoice($facility, $resident, 'unbilled', 'direct_debit');

        $content = $this->service->generate('2026-10', $facility, '20261027');

        $lines = explode("\r\n", $content);
        // ヘッダー + データ + トレーラー + エンド + 末尾空
        $this->assertSame('1', $lines[0][0]);
        $this->assertSame('2', $lines[1][0]);
        $this->assertSame('8', $lines[2][0]);
        $this->assertSame('9', $lines[3][0]);
        // 各レコード120バイト
        $this->assertSame(120, strlen($lines[0]));
        $this->assertSame(120, strlen($lines[1]));
        $this->assertSame(120, strlen($lines[2]));
    }

    public function test_対象0件でもエンドレコードのみのファイルが生成される(): void
    {
        $facility = Facility::factory()->create();

        $content = $this->service->generate('2026-10', $facility, '20261027');

        $lines = explode("\r\n", $content);
        $this->assertSame('1', $lines[0][0]);
        $this->assertSame('8', $lines[1][0]);
        $this->assertSame('9', $lines[2][0]);
    }

    public function test_振替日のデフォルト計算(): void
    {
        $facility = Facility::factory()->create();

        $content = $this->service->generate('2026-10', $facility);

        $lines = explode("\r\n", $content);
        // ヘッダー構成: 種別1 + 種別コード2 + 依頼コード1 + 委託者コード10 + 委託者名40 = 54バイト目から引落日8バイト
        $transferDate = substr($lines[0], 54, 8);
        $this->assertMatchesRegularExpression('/^\d{8}$/', $transferDate);
        // 27日締め: 現在日が27日未満なら当月27日、以降なら翌月27日
        $expected = now()->day < 27
            ? now()->setDay(27)->format('Ymd')
            : now()->addMonthNoOverflow()->setDay(27)->format('Ymd');
        $this->assertSame($expected, $transferDate);
    }

    // ------------------------------------------------------------------

    /**
     * @return array{0: Facility, 1: Resident}
     */
    protected function createFacilityAndResident(string $seed = '0001'): array
    {
        $facility = Facility::factory()->create();
        $resident = Resident::create([
            'facility_id' => $facility->id,
            'room_number' => '10'.$seed,
            'name' => '入居者'.$seed,
            'base_rent' => 100000,
            'base_management_fee' => 20000,
            'status' => 'active',
        ]);

        return [$facility, $resident];
    }

    protected function createInvoice(Facility $facility, Resident $resident, string $status, string $paymentMethod): MonthlyInvoice
    {
        return MonthlyInvoice::create([
            'resident_id' => $resident->id,
            'facility_id' => $facility->id,
            'billing_year_month' => '2026-10',
            'rent_subtotal' => 100000,
            'management_fee_subtotal' => 20000,
            'service_subtotal' => 0,
            'status' => $status,
            'payment_method' => $paymentMethod,
            'tax_rate' => 10,
        ]);
    }
}
