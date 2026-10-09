<?php

namespace Tests\Feature\InvoicePreviewTest;

use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\Facility;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvoicePreviewTest extends TestCase
{
    use RefreshDatabase;

    private Resident $resident;
    private MonthlyInvoice $invoice;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facility = Facility::factory()->create([
            'name' => 'テスト施設',
            'operator' => 'テスト運営',
            'postal_code' => '123-4567',
            'address' => '東京都テスト区',
            'phone' => '03-1234-5678',
            'fax' => '03-1234-5679',
            'invoice_registration_number' => 'T1234567890123',
            'bank' => [
                'name' => 'テスト銀行',
                'branch_name' => 'テスト支店',
                'account_type' => '普通',
                'account_number' => '1234567',
                'account_holder' => 'テスト施設',
            ],
            'billing' => [
                'direct_debit_day' => 27,
            ],
        ]);

        $this->resident = Resident::factory()->create([
            'room_number' => '101',
            'name' => 'プレビューテスト入居者',
            'move_in_date' => '2026-01-01',
            'facility_id' => $this->facility->id,
        ]);

        $this->invoice = MonthlyInvoice::factory()->create([
            'billing_year_month' => '2026-10',
            'resident_id' => $this->resident->id,
            'facility_id' => $this->facility->id,
            'rent_subtotal' => 65000,
            'management_fee_subtotal' => 30000,
            'service_subtotal' => 3000,
            'status' => InvoiceStatus::Unbilled,
        ]);
    }

    public function test_invoice_preview_returns_html()
    {
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('invoices.preview', now()->addMinutes(30), ['invoice' => $this->invoice->id, 'type' => 'invoice']);
        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        
        $html = $response->getContent();
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('御 請 求 書', $html);
        $this->assertStringContainsString('プレビューテスト入居者', $html);
        $this->assertStringContainsString('101', $html);
        $this->assertStringContainsString('2026-10', $html);
    }

    public function test_receipt_preview_returns_html_when_paid()
    {
        $this->invoice->markAsPaid(PaymentMethod::BankTransfer);

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('invoices.preview', now()->addMinutes(30), ['invoice' => $this->invoice->id, 'type' => 'receipt']);
        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        
        $html = $response->getContent();
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('領収証', $html);
        $this->assertStringContainsString('プレビューテスト入居者', $html);
        $this->assertStringContainsString('REC-', $html);
    }

    public function test_receipt_preview_404_when_not_paid()
    {
        // Unpaid invoice should 404 for receipt preview
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('invoices.preview', now()->addMinutes(30), ['invoice' => $this->invoice->id, 'type' => 'receipt']);
        $response = $this->get($url);

        $response->assertStatus(404);
    }

    public function test_invalid_type_returns_404()
    {
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('invoices.preview', now()->addMinutes(30), ['invoice' => $this->invoice->id, 'type' => 'invalid']);
        $response = $this->get($url);

        $response->assertStatus(404);
    }

    public function test_unsigned_url_is_rejected()
    {
        // 署名なしURLは403で拒否される（個人情報保護）
        $response = $this->get(route('invoices.preview', ['invoice' => $this->invoice->id, 'type' => 'invoice']));

        $response->assertStatus(403);
    }

    public function test_preview_uses_same_templates_as_pdf()
    {
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('invoices.preview', now()->addMinutes(30), ['invoice' => $this->invoice->id, 'type' => 'invoice']);
        $response = $this->get($url);

        $html = $response->getContent();

        // Check key components exist in HTML (same as PDF template)
        $this->assertStringContainsString('ご請求先', $html);
        $this->assertStringContainsString('発行者情報', $html);
        $this->assertStringContainsString('ご請求金額', $html);
        $this->assertStringContainsString('ご請求サマリー', $html);
        $this->assertStringContainsString('支払期限', $html);
        $this->assertStringContainsString('振込先', $html);
        $this->assertStringContainsString('口座名義', $html);
        $this->assertStringContainsString('口座振替', $html);
    }
}