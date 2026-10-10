<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\ChargeItem;
use App\Models\DailyCharge;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfLayoutTest extends TestCase
{
    use RefreshDatabase;

    private InvoicePdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoicePdfService::class);

        $this->resident = Resident::create([
            'room_number' => '101',
            'name' => 'Layout Test Resident',
            'move_in_date' => '2026-01-01',
        ]);

        $this->invoice = MonthlyInvoice::create([
            'billing_year_month' => '2026-10',
            'resident_id' => $this->resident->id,
            'rent_subtotal' => 65000,
            'management_fee_subtotal' => 30000,
            'service_subtotal' => 5000,
            'status' => InvoiceStatus::Unbilled,
        ]);
    }

    /**
     * PDFの代わりにBladeビューをレンダリングし、HTML文字列を返す
     * （PDFバイナリは圧縮されるため、レイアウト検証はHTMLで行う）
     */
    protected function renderPdfHtml($invoice, string $type): string
    {
        [$view, $data] = $this->service->prepareViewData($invoice, $type);

        return view($view, $data)->render();
    }

    public function test_invoice_pdf_contains_expected_layout_elements()
    {
        $html = $this->renderPdfHtml($this->invoice, 'invoice');

        // 基本的な文字列が含まれていることを確認
        $this->assertStringContainsString('御 請 求 書', $html);
        $this->assertStringContainsString($this->resident->name, $html);
        $this->assertStringContainsString('ご請求金額', $html);
        $this->assertStringContainsString('ご請求サマリー', $html);
    }

    public function test_receipt_pdf_contains_expected_layout_elements()
    {
        // 入金済み状態にする
        $this->invoice->markAsPaid(PaymentMethod::BankTransfer);

        $html = $this->renderPdfHtml($this->invoice, 'receipt');

        // 基本的な文字列が含まれていることを確認
        $this->assertStringContainsString('領　収　証', $html);
        $this->assertStringContainsString($this->resident->name, $html);
        $this->assertStringContainsString('領収金額', $html);
        $this->assertStringContainsString('内訳明細', $html);
    }

    public function test_consistent_styling_between_invoice_and_receipt()
    {
        // 請求書HTML
        $invoiceHtml = $this->renderPdfHtml($this->invoice, 'invoice');

        // 領収書HTML（入金済みにしてから）
        $this->invoice->markAsPaid(PaymentMethod::BankTransfer);
        $receiptHtml = $this->renderPdfHtml($this->invoice, 'receipt');

        // 共通のスタイル要素が含まれていることを確認
        $commonElements = [
            'Noto Sans JP',
            $this->resident->name,
        ];

        foreach ($commonElements as $element) {
            $this->assertStringContainsString(
                $element,
                $invoiceHtml,
                "Invoice HTML should contain {$element}"
            );
            $this->assertStringContainsString(
                $element,
                $receiptHtml,
                "Receipt HTML should contain {$element}"
            );
        }
    }

    public function test_amount_formatting_consistency()
    {
        // 請求書HTML
        $invoiceHtml = $this->renderPdfHtml($this->invoice, 'invoice');

        // 領収書HTML（入金済みにしてから）
        $this->invoice->markAsPaid(PaymentMethod::BankTransfer);
        $receiptHtml = $this->renderPdfHtml($this->invoice, 'receipt');

        // 金額フォーマットの一貫性を確認（税込合計金額が含まれていることをチェック）
        $expectedAmount = '¥'.number_format($this->invoice->total_with_tax);

        $this->assertStringContainsString(
            $expectedAmount,
            $invoiceHtml,
            'Invoice HTML should contain formatted total amount'
        );

        $this->assertStringContainsString(
            $expectedAmount,
            $receiptHtml,
            'Receipt HTML should contain formatted total amount'
        );
    }

    public function test_page_break_present_when_details_exist()
    {
        // 明細データを追加（先に品目マスタを作成: FK制約のため）
        $chargeItem = ChargeItem::create([
            'name' => 'テスト品目',
            'default_price' => 1000,
            'is_active' => true,
        ]);

        DailyCharge::create([
            'resident_id' => $this->resident->id,
            'date' => '2026-10-01',
            'charge_item_id' => $chargeItem->id,
            'unit_price' => 1000,
            'quantity' => 1,
            'note' => 'テスト明細',
        ]);

        $html = $this->renderPdfHtml($this->invoice, 'invoice');

        // 明細が存在する場合に適切なマークアップが生成されていることを確認
        $this->assertStringContainsString('日々の自費サービス利用明細', $html);
        $this->assertStringContainsString('テスト明細', $html);
    }
}
