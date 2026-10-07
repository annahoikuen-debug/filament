<?php

namespace App\Services;

use App\Models\MonthlyInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use ZipArchive;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Writer;
use Illuminate\Support\Carbon;

class InvoicePdfService
{
    /**
     * 日本語フォント設定（Windows環境で利用可能なフォントを優先し、Noto Sans JPをフォールバックに）
     */
    private const FONT_FAMILY = 'YuMincho, "MS Gothic", "Meiryo", "Noto Sans JP", sans-serif';

    private const NOTO_SANS_JP_DIR = 'noto-sans-jp';

    /**
     * Noto Sans JPフォントファイルのダウンロードURL
     */
    private const NOTO_FONTS = [
        'Regular' => 'https://github.com/googlefonts/noto-fonts/raw/main/hinted/ttf/NotoSansJP/NotoSansJP-Regular.ttf',
        'Bold' => 'https://github.com/googlefonts/noto-fonts/raw/main/hinted/ttf/NotoSansJP/NotoSansJP-Bold.ttf',
        'Medium' => 'https://github.com/googlefonts/noto-fonts/raw/main/hinted/ttf/NotoSansJP/NotoSansJP-Medium.ttf',
    ];

    /**
     * 請求書PDFを生成する
     */
    public function generateInvoicePdf(MonthlyInvoice $invoice, ?array $facility = null): DomPdfInstance
    {
        return $this->generatePdfFromInvoice($invoice, 'invoice', $facility);
    }

    /**
     * 共通PDF生成メソッド（請求書・領収書を統合）
     *
     * @param  string  $type  'invoice' または 'receipt'
     */
    public function generatePdfFromInvoice(MonthlyInvoice $invoice, string $type = 'invoice', ?array $facility = null): DomPdfInstance
    {
        @ini_set('memory_limit', '512M');

        $invoice->load([
            'resident.dailyCharges' => function ($query) use ($invoice) {
                $query->forYearMonth($invoice->billing_year_month)
                    ->with('chargeItem')
                    ->orderBy('date');
            },
        ]);

        $view = $type === 'invoice' ? 'invoices.pdf' : 'invoices.receipt';

        // テンプレート設定を取得
        $templateConfig = $this->getTemplateConfig($type);

        // QRコードデータを設定（設定で有効になっている場合）
        if ($templateConfig['show_qr_code'] ?? false) {
            if ($type === 'invoice') {
                // 請求書の場合は振込用QRコード
                $qrCodeData = $this->getBankTransferQrCodeData($invoice, $facility ?? config('facility'));
                $templateConfig['qr_code_data'] = $this->generatePaymentQrCode($qrCodeData);
            } else {
                // 領収書の場合は検証用QRコード
                $qrCodeData = $this->getReceiptVerificationQrCodeData($invoice);
                $templateConfig['qr_code_data'] = $this->generatePaymentQrCode($qrCodeData);
            }
        }

        $pdf = Pdf::loadView($view, [
            'invoice' => $invoice,
            'resident' => $invoice->resident,
            'dailyCharges' => $type === 'invoice' ? $invoice->resident->dailyCharges : null,
            'facility' => $facility ?? config('facility'),
            // 税情報を追加
            'tax_info' => [
                'non_taxable' => $invoice->non_taxable_amount,
                'taxable' => $invoice->taxable_amount,
                'tax_rate' => $invoice->tax_rate,
                'tax_amount' => $invoice->tax_amount,
                'total_with_tax' => $invoice->total_with_tax,
            ],
            // テンプレート設定（CSS変数等用）
            'template' => $templateConfig,
        ]);

        // 日本語フォント設定を適用
        $this->applyJapaneseFontSettings($pdf, $templateConfig);

        // 余白・用紙設定を適用
        $this->applyMargins($pdf, $templateConfig);

        return $pdf;
    }

    /**
     * テンプレート設定を取得（config からマージ）
     */
    /**
     * テンプレート設定を取得（config からマージ）
     */
    private function getTemplateConfig(string $type): array
    {
        $baseConfig = config('pdf.default', []);
        $typeConfig = config("pdf.{$type}", []);

        // Merge base with type-specific config
        return array_merge($baseConfig, $typeConfig);
    }

    /**
     * 日本語フォント設定を適用
     */
    private function applyJapaneseFontSettings(DomPdfInstance $pdf, array $templateConfig): void
    {
        // Noto Sans JPフォントディレクトリを優先（存在する場合）
        $notoFontDir = resource_path('fonts/'.self::NOTO_SANS_JP_DIR);
        $fontDir = File::exists($notoFontDir) ? $notoFontDir : storage_path('fonts');

        $fontFamily = $templateConfig['font_family'] ?? 'YuMincho, "MS Gothic", "Meiryo", "Noto Sans JP", sans-serif';
        $fontSize = $templateConfig['font_size'] ?? 11;
        $lineHeight = $templateConfig['line_height'] ?? 1.6;

        $options = [
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'fontHeightRatio' => (float) $lineHeight,
            'font_dir' => $fontDir,
            'font_cache' => storage_path('fonts'),
        ];

        $pdf->setOptions($options);
    }

    /**
     * 余白設定を適用
     */
    private function applyMargins(DomPdfInstance $pdf, array $templateConfig): void
    {
        $marginTop = ($templateConfig['margin_top'] ?? 15) . 'mm';
        $marginRight = ($templateConfig['margin_right'] ?? 15) . 'mm';
        $marginBottom = ($templateConfig['margin_bottom'] ?? 15) . 'mm';
        $marginLeft = ($templateConfig['margin_left'] ?? 15) . 'mm';

        // DomPDFのマージン設定はsetPaperで指定
        // setPaper(size, orientation, margins)
        $paperSize = $templateConfig['paper_size'] ?? 'a4';
        $paperOrientation = $templateConfig['paper_orientation'] ?? 'portrait';
        $pdf->setPaper(
            $paperSize,
            $paperOrientation,
            [
                (float) str_replace('mm', '', $marginLeft),
                (float) str_replace('mm', '', $marginTop),
                (float) str_replace('mm', '', $marginRight),
                (float) str_replace('mm', '', $marginBottom),
            ]
        );
    }

    /**
     * Noto Sans JPフォントをダウンロード・インストールする
     * 初回実行時やデプロイ時に実行推奨
     *
     * @return array<string, string> インストール結果
     */
    public function installNotoSansJpFonts(): array
    {
        $results = [];
        $targetDir = resource_path('fonts/'.self::NOTO_SANS_JP_DIR);
        File::ensureDirectoryExists($targetDir);

        foreach (self::NOTO_FONTS as $weight => $url) {
            $fileName = "NotoSansJP-{$weight}.ttf";
            $targetPath = $targetDir.DIRECTORY_SEPARATOR.$fileName;

            if (File::exists($targetPath)) {
                $results[$weight] = 'already_exists';

                continue;
            }

            try {
                $content = @file_get_contents($url);
                if ($content === false) {
                    $results[$weight] = 'download_failed';

                    continue;
                }

                File::put($targetPath, $content);
                $results[$weight] = 'installed';
            } catch (\Throwable $e) {
                $results[$weight] = 'error: '.$e->getMessage();
            }
        }

        // DomPDFフォントキャッシュをクリア
        $this->clearFontCache();

        return $results;
    }

    /**
     * QRコードを生成するヘルパーメソッド
     *
     * @param string $data エンコードするデータ
     * @return string base64エンコードされたSVGデータURI
     */
    private function generatePaymentQrCode(string $data): string
    {
        try {
            $rendererStyle = new \BaconQrCode\Renderer\RendererStyle\RendererStyle(70);
            $imageBackEnd = new \BaconQrCode\Renderer\Image\SvgImageBackEnd();
            $renderer = new \BaconQrCode\Renderer\ImageRenderer($rendererStyle, $imageBackEnd);
            $writer = new \BaconQrCode\Writer($renderer);
            $svg = $writer->writeString($data);
            
            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        } catch (\Throwable $e) {
            // QRコード生成に失敗してもPDF生成は続行
            return '';
        }
    }

    /**
     * 振込用QRコードデータを生成
     *
     * @param MonthlyInvoice $invoice 請求書データ
     * @param array $facility 施設情報
     * @return string QRコード用データ文字列
     */
    private function getBankTransferQrCodeData(MonthlyInvoice $invoice, array $facility): string
    {
        // 日本のQRコード規格（振込用）に準拠したデータを生成
        // 実際の実装では、銀行が指定するフォーマットに従う必要があるため、
        // ここでは簡易版を実装
        $data = sprintf(
            "STU{\\n振:%s\\n種:振込\\n金:%d\\n名:%s\\n  współ:%s %s\\n 口:%s %s\\n 住:%s\\nREF:%s-%s}",
            $facility['bank']['account_number'] ?? '012345',
            (int)$invoice->total_with_tax,
            $facility['bank']['account_holder'] ?? '',
            $facility['bank']['name'] ?? '',
            $facility['bank']['branch_name'] ?? '',
            $facility['bank']['account_type'] ?? '普通',
            $facility['bank']['account_number'] ?? '',
            $facility['address'] ?? '',
            str_replace('-', '', $invoice->billing_year_month),
            str_pad($invoice->resident->id, 3, '0', STR_PAD_LEFT)
        );
        
        return str_replace(["\r\n", "\n", "\r"], '', $data);
    }

    /**
     * 領収書用検証QRコードデータを生成
     *
     * @param MonthlyInvoice $invoice 領収書データ
     * @return string QRコード用データ文字列
     */
    private function getReceiptVerificationQrCodeData(MonthlyInvoice $invoice): string
    {
        $data = json_encode([
            'type' => 'receipt_verification',
            'receipt_number' => $invoice->receipt_number,
            'resident_id' => $invoice->resident->id,
            'resident_name' => $invoice->resident->name,
            'amount' => $invoice->total_with_tax,
            'date' => $invoice->paid_at ? $invoice->paid_at->format('Y-m-d') : now()->format('Y-m-d'),
            'issued_by' => config('facility.name'),
            'timestamp' => now()->timestamp
        ], JSON_UNESCAPED_UNICODE);

        return base64_encode($data);
        }

        /**
         * DomPDFフォントキャッシュをクリア
         */
        private function clearFontCache(): void
        {
            $cacheDir = storage_path('fonts');
            if (File::exists($cacheDir)) {
                foreach (File::allFiles($cacheDir) as $file) {
                    if ($file->getExtension() === 'ufm' || $file->getExtension() === 'afm') {
                        File::delete($file->getPathname());
                    }
                }
            }
        }

        /**
         * 請求書PDFをHTTPレスポンスとしてダウンロードする
         */
    public function downloadPdf(MonthlyInvoice $invoice): Response
    {
        $pdf = $this->generatePdfFromInvoice($invoice, 'invoice', null);
        $fileName = sprintf(
            '請求書_%s_%s様_%s.pdf',
            $invoice->billing_year_month,
            $invoice->resident->name,
            $invoice->resident->room_number
        );

        return $pdf->download($fileName);
    }

    /**
     * 請求書PDFをブラウザ内でプレビュー表示する
     */
    public function streamPdf(MonthlyInvoice $invoice): Response
    {
        $pdf = $this->generatePdfFromInvoice($invoice, 'invoice', null);

        return $pdf->stream();
    }

    /**
     * 領収書PDFを生成する
     */
    public function generateReceiptPdf(MonthlyInvoice $invoice, ?array $facility = null): DomPdfInstance
    {
        return $this->generatePdfFromInvoice($invoice, 'receipt', $facility);
    }

    /**
     * 指定年月の全入居者分請求書PDFを一括ZIPファイルにアーカイブする
     *
     * @param  string  $yearMonth  'YYYY-MM'
     * @return string 作成されたZIPファイルの一時パス
     */
    public function generateMonthlyZip(string $yearMonth, ?int $facilityId = null): string
    {
        $query = MonthlyInvoice::with('resident')
            ->forYearMonth($yearMonth);
            
        if ($facilityId) {
            $query->whereHas('resident', function ($q) use ($facilityId) {
                $q->where('facility_id', $facilityId);
            });
        }

        $invoices = $query->get();

        $zipPath = storage_path("app/temp/請求書一括_{$yearMonth}.zip");

        File::ensureDirectoryExists(dirname($zipPath));

        if (File::exists($zipPath)) {
            File::delete($zipPath);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIPファイルの作成に失敗しました。');
        }

        try {
            foreach ($invoices as $invoice) {
                $pdf = $this->generateInvoicePdf($invoice, null);
                $fileName = sprintf(
                    '【%s号室】%s様_請求書_%s.pdf',
                    $invoice->resident->room_number,
                    $invoice->resident->name,
                    $invoice->billing_year_month
                );

                $zip->addFromString($fileName, $pdf->output());
            }

            $zip->close();

            return $zipPath;
        } catch (\Throwable $e) {
            $zip->close();
            if (File::exists($zipPath)) {
                File::delete($zipPath);
            }
            throw $e;
        }
    }
}