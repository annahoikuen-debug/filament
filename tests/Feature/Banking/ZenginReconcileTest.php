<?php

namespace Tests\Feature\Banking;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\Banking\ZenginFormatService;
use App\Services\Banking\ZenginReconcileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZenginReconcileTest extends TestCase
{
    use RefreshDatabase;

    protected ZenginReconcileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ZenginReconcileService(new \App\Services\Banking\ZenginResultParser);
    }

    public function test_振替成功分は入金済に更新され領収書番号が採番される(): void
    {
        $invoice = $this->createInvoice('1001');

        $content = $this->buildResultFile([
            ['resident_id' => $invoice->resident_id, 'amount' => $invoice->total_with_tax, 'code' => '0'],
        ]);

        $result = $this->service->reconcile($content, '2026-10', '2026-10-27');

        $this->assertSame(1, $result['success_count']);
        $this->assertSame(0, $result['failed_count']);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(PaymentMethod::DirectDebit, $invoice->payment_method);
        $this->assertNotNull($invoice->receipt_number);
    }

    public function test_振替不能分は請求済に戻り理由が記録される(): void
    {
        $invoice = $this->createInvoice('1002');

        $content = $this->buildResultFile([
            ['resident_id' => $invoice->resident_id, 'amount' => $invoice->total_with_tax, 'code' => '1'],
        ]);

        $result = $this->service->reconcile($content, '2026-10', '2026-10-27');

        $this->assertSame(0, $result['success_count']);
        $this->assertSame(1, $result['failed_count']);

        $invoice->refresh();
        $this->assertNotSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('資金不足', $result['details']['failed'][0]['reason']);
    }

    public function test_成功と不能が混在する結果ファイルを正しく処理する(): void
    {
        $inv1 = $this->createInvoice('2001');
        $inv2 = $this->createInvoice('2002');

        $content = $this->buildResultFile([
            ['resident_id' => $inv1->resident_id, 'amount' => $inv1->total_with_tax, 'code' => '0'],
            ['resident_id' => $inv2->resident_id, 'amount' => $inv2->total_with_tax, 'code' => '2'],
        ]);

        $result = $this->service->reconcile($content, '2026-10', '2026-10-27');

        $this->assertSame(1, $result['success_count']);
        $this->assertSame(1, $result['failed_count']);

        $inv1->refresh();
        $inv2->refresh();
        $this->assertSame(InvoiceStatus::Paid, $inv1->status);
        $this->assertSame(InvoiceStatus::Billed, $inv2->status);
    }

    public function test_不能コードマッピングが正しい(): void
    {
        $this->assertSame('資金不足', \App\Services\Banking\ZenginResultParser::FAILURE_REASONS['1']);
        $this->assertSame('取引なし', \App\Services\Banking\ZenginResultParser::FAILURE_REASONS['2']);
        $this->assertSame('預金者都合', \App\Services\Banking\ZenginResultParser::FAILURE_REASONS['3']);
        $this->assertSame('委託者都合', \App\Services\Banking\ZenginResultParser::FAILURE_REASONS['8']);
    }

    // ------------------------------------------------------------------

    protected function createInvoice(string $seed): MonthlyInvoice
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

        return MonthlyInvoice::create([
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
    }

    /**
     * 結果ファイル（データレコード）を構築する
     */
    protected function buildResultFile(array $entries): string
    {
        $lines = [];
        $total = 0;
        foreach ($entries as $entry) {
            $total += $entry['amount'];
            // パーサー仕様に合わせる: 金額@44-53, 顧客コード@54-63, 結果コード@64
            $line = '2'
                .str_repeat('0', 43)
                .str_pad((string) $entry['amount'], 10, '0', STR_PAD_LEFT)
                .str_pad((string) $entry['resident_id'], 10, '0', STR_PAD_LEFT)
                .$entry['code']
                .str_repeat(' ', 55);
            $lines[] = substr($line, 0, 120);
        }

        $lines[] = '8'.str_pad((string) count($entries), 6, '0', STR_PAD_LEFT)
            .str_pad((string) $total, 12, '0', STR_PAD_LEFT)
            .str_repeat(' ', 101);

        $lines[] = str_repeat('9', 120);

        return mb_convert_encoding(implode("\r\n", $lines)."\r\n", 'CP932', 'UTF-8');
    }
}
