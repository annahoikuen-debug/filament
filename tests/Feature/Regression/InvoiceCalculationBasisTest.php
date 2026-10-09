<?php

namespace Tests\Feature\Regression;

use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Models\PdfTemplateSettings;
use App\Services\Pdf\InvoicePdfGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class InvoiceCalculationBasisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // デフォルトテンプレートを作成（計算根拠表示有効）
        PdfTemplateSettings::create([
            'key' => 'invoice',
            'locale' => 'ja',
            'theme' => 'standard',
            'name' => 'デフォルト請求書テンプレート',
            'is_default' => true,
            'is_active' => true,
            'show_calculation_basis' => true,
            'paper_size' => 'A4',
            'paper_orientation' => 'portrait',
            'font_family' => 'ipaexg',
            'font_size' => 10.5,
            'line_height' => 1.6,
        ]);
    }

    /** @test */
    public function 通常月は月額そのまま表示で按分行なし(): void
    {
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'base_rent' => 50000,
            'base_management_fee' => 30000,
            'move_in_date' => '2025-01-01',
            'move_out_date' => null,
        ]);

        $invoice = MonthlyInvoice::factory()->create([
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-06',
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 30000,
            'service_subtotal' => 10000,
            'taxable_amount' => 40000,
            'tax_amount' => 4000,
            'tax_rate' => 10,
            'tax_breakdown' => [
                'standard' => ['taxable_amount' => 40000, 'rate' => 10, 'tax_amount' => 4000],
                'reduced' => ['taxable_amount' => 0, 'rate' => 8, 'tax_amount' => 0],
                'non_taxable' => ['amount' => 50000],
            ],
        ]);

        $generator = app(InvoicePdfGenerator::class);
        $html = $generator->previewInvoice($invoice);

        // 計算根拠セクションが含まれていること
        $this->assertStringContainsString('【計算根拠】', $html);
        // 「満月在籍のため月額そのまま適用」の文言
        $this->assertStringContainsString('満月在籍のため月額そのまま適用', $html);
        // 「日割り計算（月中途入居・退去）」の文言が含まれていないこと（別文言「満月在籍のため月額そのまま適用」が表示される）
        $this->assertStringNotContainsString('日割り計算（月中途入居・退去）', $html);
        // サマリーテーブルで家賃・管理費の金額が表示されている
        // 基本家賃: ¥50,000 (非課税), 基本管理費＋自費サービス: ¥40,000 (課税 = 30,000 + 10,000)
        $this->assertStringContainsString('基本家賃', $html);
        $this->assertStringContainsString('¥50,000', $html);
        $this->assertStringContainsString('基本管理費＋自費サービス', $html);
        $this->assertStringContainsString('¥40,000', $html);
        // 税額内訳が表示されている（計算根拠セクション内）
        $this->assertStringContainsString('消費税内訳', $html);
        $this->assertStringContainsString('標準税率対象', $html);
        $this->assertStringContainsString('非課税', $html);
        // 合計（税込）が表示されている
        $this->assertStringContainsString('合計（税込）', $html);
        $this->assertStringContainsString('¥94,000', $html);
    }

    /** @test */
    public function 月中途入居は日割り計算行が表示される(): void
    {
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'base_rent' => 50000,
            'base_management_fee' => 30000,
            'move_in_date' => '2026-06-15', // 月中途入居
            'move_out_date' => null,
        ]);

        $invoice = MonthlyInvoice::factory()->create([
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-06',
            'rent_subtotal' => 26667, // 50000 / 30 * 16日
            'management_fee_subtotal' => 16000, // 30000 / 30 * 16日
            'service_subtotal' => 5000,
            'taxable_amount' => 21000,
            'tax_amount' => 2100,
            'tax_rate' => 10,
            'tax_breakdown' => [
                'standard' => ['taxable_amount' => 21000, 'rate' => 10, 'tax_amount' => 2100],
                'reduced' => ['taxable_amount' => 0, 'rate' => 8, 'tax_amount' => 0],
                'non_taxable' => ['amount' => 26667],
            ],
        ]);

        $generator = app(InvoicePdfGenerator::class);
        $html = $generator->previewInvoice($invoice);

        // 計算根拠セクションが含まれていること
        $this->assertStringContainsString('【計算根拠】', $html);
        // 日割り計算の文言（見出し）
        $this->assertStringContainsString('日割り計算', $html);
        // 家賃の月額・日数・按分額が表示されている（¥プレフィックス付き）
        $this->assertStringContainsString('基本家賃（月額）', $html);
        $this->assertStringContainsString('¥50,000', $html);
        $this->assertStringContainsString('月日数：30日 × 在籍日数：16日', $html);
        $this->assertStringContainsString('¥26,667', $html);
        // 管理費の按分計算
        $this->assertStringContainsString('基本管理費（月額）', $html);
        $this->assertStringContainsString('¥30,000', $html);
        $this->assertStringContainsString('¥16,000', $html);
        // 「月中途入居・退去のため日割り計算を適用」の文言
        $this->assertStringContainsString('月中途入居・退去のため日割り計算を適用', $html);
    }

    /** @test */
    public function 月中退去は日割り計算行が表示される(): void
    {
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'base_rent' => 50000,
            'base_management_fee' => 30000,
            'move_in_date' => '2025-01-01',
            'move_out_date' => '2026-06-20', // 月中退去
        ]);

        $invoice = MonthlyInvoice::factory()->create([
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-06',
            'rent_subtotal' => 33333, // 50000 / 30 * 20日
            'management_fee_subtotal' => 20000, // 30000 / 30 * 20日
            'service_subtotal' => 5000,
            'taxable_amount' => 25000,
            'tax_amount' => 2500,
            'tax_rate' => 10,
            'tax_breakdown' => [
                'standard' => ['taxable_amount' => 25000, 'rate' => 10, 'tax_amount' => 2500],
                'reduced' => ['taxable_amount' => 0, 'rate' => 8, 'tax_amount' => 0],
                'non_taxable' => ['amount' => 33333],
            ],
        ]);

        $generator = app(InvoicePdfGenerator::class);
        $html = $generator->previewInvoice($invoice);

        // 日割り計算の文言
        $this->assertStringContainsString('日割り計算', $html);
        // 在籍日数が20日
        $this->assertStringContainsString('月日数：30日 × 在籍日数：20日', $html);
        // 按分額（¥プレフィックス付き）
        $this->assertStringContainsString('¥33,333', $html); // 家賃按分
        $this->assertStringContainsString('¥20,000', $html); // 管理費按分
    }

    /** @test */
    public function 税額内訳が正しく表示される_標準軽減非課税(): void
    {
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'base_rent' => 50000,
            'base_management_fee' => 30000,
        ]);

        $invoice = MonthlyInvoice::factory()->create([
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-06',
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 30000,
            'service_subtotal' => 20000,
            'taxable_amount' => 50000,
            'tax_amount' => 5000,
            'tax_rate' => 10,
            'tax_breakdown' => [
                'standard' => ['taxable_amount' => 45000, 'rate' => 10, 'tax_amount' => 4500],
                'reduced' => ['taxable_amount' => 5000, 'rate' => 8, 'tax_amount' => 400],
                'non_taxable' => ['amount' => 50000],
            ],
        ]);

        $generator = app(InvoicePdfGenerator::class);
        $html = $generator->previewInvoice($invoice);

        // 標準税率10%
        $this->assertStringContainsString('標準税率対象', $html);
        $this->assertStringContainsString('45,000', $html);
        $this->assertStringContainsString('10%', $html);
        $this->assertStringContainsString('4,500', $html);
        // 軽減税率8%
        $this->assertStringContainsString('軽減税率対象', $html);
        $this->assertStringContainsString('5,000', $html);
        $this->assertStringContainsString('8%', $html);
        $this->assertStringContainsString('400', $html);
        // 非課税
        $this->assertStringContainsString('非課税（家賃等）', $html);
        $this->assertStringContainsString('50,000', $html);
        $this->assertStringContainsString('非課税', $html);
        $this->assertStringContainsString('0', $html); // 税額0
    }

    /** @test */
    public function show_calculation_basis_falseの施設では非表示(): void
    {
        // テンプレート設定で計算根拠表示を無効化
        PdfTemplateSettings::where('key', 'invoice')
            ->where('locale', 'ja')
            ->where('is_default', true)
            ->update(['show_calculation_basis' => false]);
        
        // キャッシュをクリア
        app(\App\Services\Pdf\TemplateSettingsService::class)->clearCache('invoice', 'ja', 'standard');

        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'base_rent' => 50000,
            'base_management_fee' => 30000,
            'move_in_date' => '2026-06-15', // 月中途入居
        ]);

        $invoice = MonthlyInvoice::factory()->create([
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-06',
            'rent_subtotal' => 26667,
            'management_fee_subtotal' => 16000,
            'service_subtotal' => 5000,
            'taxable_amount' => 21000,
            'tax_amount' => 2100,
            'tax_rate' => 10,
            'tax_breakdown' => [
                'standard' => ['taxable_amount' => 21000, 'rate' => 10, 'tax_amount' => 2100],
                'reduced' => ['taxable_amount' => 0, 'rate' => 8, 'tax_amount' => 0],
                'non_taxable' => ['amount' => 26667],
            ],
        ]);

        $generator = app(InvoicePdfGenerator::class);
        $html = $generator->previewInvoice($invoice);

        // 計算根拠セクションが含まれていないこと
        $this->assertStringNotContainsString('【計算根拠】', $html);
        $this->assertStringNotContainsString('日割り計算', $html);
        $this->assertStringNotContainsString('消費税内訳', $html);
    }

    /** @test */
    public function 既存PDFレイアウトテストへの影響なし(): void
    {
        // 既存のPDFレイアウトテストと同じデータで生成し、主要要素が保持されているか確認
        $facility = Facility::factory()->create([
            'name' => 'テスト施設',
            'address' => '東京都渋谷区',
        ]);
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'name' => '山田太郎',
            'room_number' => '101',
        ]);

        $invoice = MonthlyInvoice::factory()->create([
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-06',
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 30000,
            'service_subtotal' => 10000,
            'taxable_amount' => 40000,
            'tax_amount' => 4000,
            'tax_rate' => 10,
            'tax_breakdown' => [
                'standard' => ['taxable_amount' => 40000, 'rate' => 10, 'tax_amount' => 4000],
                'reduced' => ['taxable_amount' => 0, 'rate' => 8, 'tax_amount' => 0],
                'non_taxable' => ['amount' => 50000],
            ],
        ]);

        $generator = app(InvoicePdfGenerator::class);
        $html = $generator->previewInvoice($invoice);

        // 既存の必須要素が保持されていること
        $this->assertStringContainsString('請求書', $html);
        $this->assertStringContainsString('テスト施設', $html);
        $this->assertStringContainsString('山田太郎', $html);
        $this->assertStringContainsString('101', $html);
        $this->assertStringContainsString('【ご請求サマリー】', $html);
        $this->assertStringContainsString('基本家賃', $html);
        $this->assertStringContainsString('基本管理費＋自費サービス', $html);
        $this->assertStringContainsString('合計', $html);
        // 新規追加の計算根拠も表示されていること（設定がtrueなので）
        $this->assertStringContainsString('【計算根拠】', $html);
    }

    /** @test */
    public function 領収書PDFには計算根拠が表示されない(): void
    {
        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'base_rent' => 50000,
            'base_management_fee' => 30000,
            'name' => '山田太郎',
        ]);

        $invoice = MonthlyInvoice::factory()->create([
            'facility_id' => $facility->id,
            'resident_id' => $resident->id,
            'billing_year_month' => '2026-06',
            'rent_subtotal' => 50000,
            'management_fee_subtotal' => 30000,
            'service_subtotal' => 10000,
            'taxable_amount' => 40000,
            'tax_amount' => 4000,
            'tax_rate' => 10,
            'tax_breakdown' => [
                'standard' => ['taxable_amount' => 40000, 'rate' => 10, 'tax_amount' => 4000],
                'reduced' => ['taxable_amount' => 0, 'rate' => 8, 'tax_amount' => 0],
                'non_taxable' => ['amount' => 50000],
            ],
            'status' => \App\Enums\InvoiceStatus::Paid,
        ]);

        $generator = app(InvoicePdfGenerator::class);
        $html = $generator->previewReceipt($invoice);

        // 領収書には計算根拠セクションが含まれていないこと
        $this->assertStringNotContainsString('【計算根拠】', $html);
        // 既存の領収書要素は保持（テンプレートでは「領収証」と表記）
        $this->assertStringContainsString('領収証', $html);
        $this->assertStringContainsString('山田太郎', $html);
    }
}