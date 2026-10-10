<?php

namespace Tests\Feature\Regression;

use App\Models\Facility;
use App\Models\MonthlyInvoice;
use App\Models\Resident;
use App\Services\Pdf\PdfPasswordProtector;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * PDFパスワード保護のリグレッションテスト
 *
 * 監査指摘 P0-1「請求書PDFにパスワード保護なし」への対応。
 */
class PdfPasswordProtectionTest extends TestCase
{
    use RefreshDatabase;

    private PdfPasswordProtector $protector;

    private MonthlyInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->protector = app(PdfPasswordProtector::class);

        $facility = Facility::factory()->create();
        $resident = Resident::factory()->create([
            'facility_id' => $facility->id,
            'birth_date' => '1945-03-15',
        ]);
        $this->invoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
            'facility_id' => $facility->id,
        ]);
    }

    public function test_保護機能で_pd_fが暗号化されること(): void
    {
        $plainPdf = $this->createSamplePdf();

        $protected = $this->protector->protect($plainPdf, 'test1234', 'owner-secret');

        $this->assertNotSame($plainPdf, $protected);
        $this->assertStringStartsWith('%PDF-', $protected);
        // 暗号化PDFには /Encrypt ディクショナリが含まれる
        $this->assertStringContainsString('/Encrypt', $protected);
    }

    public function test_無効な入力で例外が発生すること(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->protector->protect('', 'test1234');
    }

    public function test_保護無効時は元のバイナリがそのまま返ること(): void
    {
        Config::set('pdf.password.enabled', false);
        $plainPdf = $this->createSamplePdf();

        $result = $this->protector->protectInvoice($plainPdf, $this->invoice);

        $this->assertSame($plainPdf, $result);
    }

    public function test_fixedモードで固定パスワードが適用されること(): void
    {
        Config::set('pdf.password.enabled', true);
        Config::set('pdf.password.mode', 'fixed');
        Config::set('pdf.password.fixed_password', 'seikyu2026');

        $plainPdf = $this->createSamplePdf();
        $protected = $this->protector->protectInvoice($plainPdf, $this->invoice);

        $this->assertStringContainsString('/Encrypt', $protected);
    }

    public function test_resident_birthdayモードで生年月日8桁がパスワードになること(): void
    {
        Config::set('pdf.password.enabled', true);
        Config::set('pdf.password.mode', 'resident_birthday');

        $password = $this->protector->resolvePasswordForInvoice($this->invoice);

        $this->assertSame('19450315', $password);
    }

    public function test_resident_birthdayモードで生年月日が無い場合はfixedにフォールバックすること(): void
    {
        Config::set('pdf.password.enabled', true);
        Config::set('pdf.password.mode', 'resident_birthday');
        Config::set('pdf.password.fixed_password', 'fallback-pass');

        $resident = Resident::factory()->create([
            'birth_date' => null,
        ]);
        $invoice = MonthlyInvoice::factory()->create([
            'resident_id' => $resident->id,
        ]);

        $password = $this->protector->resolvePasswordForInvoice($invoice);

        $this->assertSame('fallback-pass', $password);
    }

    public function test_保護済み_pd_fは元_pd_fと異なるバイト列であること(): void
    {
        Config::set('pdf.password.enabled', true);
        Config::set('pdf.password.mode', 'fixed');
        Config::set('pdf.password.fixed_password', 'pw12345');

        $plainPdf = $this->createSamplePdf();
        $protected = $this->protector->protectInvoice($plainPdf, $this->invoice);

        // 元PDFと異なるバイト列かつ暗号化ディクショナリが含まれること
        // （TCPDFは作成日時・UUIDを埋め込むため同一性の再現性は検証しない）
        $this->assertNotSame($plainPdf, $protected);
        $this->assertStringContainsString('/Encrypt', $protected);
    }

    /**
     * DomPDFで1ページのサンプルPDFを生成する
     */
    private function createSamplePdf(): string
    {
        $options = new Options([
            'font_dir' => storage_path('fonts'),
            'font_cache' => storage_path('fonts'),
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'ipaexg',
        ]);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml('<html><body><p>テスト請求書</p></body></html>');
        $dompdf->render();

        return $dompdf->output();
    }
}
