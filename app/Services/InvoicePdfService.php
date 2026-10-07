<?php

namespace App\Services;

use App\Models\MonthlyInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use ZipArchive;

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
    public function generateInvoicePdf(MonthlyInvoice $invoice): DomPdfInstance
    {
        return $this->generatePdfFromInvoice($invoice, 'invoice');
    }

    /**
     * 共通PDF生成メソッド（請求書・領収書を統合）
     *
     * @param  string  $type  'invoice' または 'receipt'
     */
    public function generatePdfFromInvoice(MonthlyInvoice $invoice, string $type = 'invoice'): DomPdfInstance
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

        $pdf = Pdf::loadView($view, [
            'invoice' => $invoice,
            'resident' => $invoice->resident,
            'dailyCharges' => $type === 'invoice' ? $invoice->resident->dailyCharges : null,
            'facility' => config('facility'),
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
    private function getTemplateConfig(string $type): array
    {
        $baseConfig = [
            'paper_size' => 'a4',
            'paper_orientation' => 'portrait',
            'margin_top' => 15,
            'margin_right' => 15,
            'margin_bottom' => 15,
            'margin_left' => 15,
            'font_family' => 'YuMincho, "MS Gothic", "Meiryo", "Noto Sans JP", sans-serif',
            'font_size' => 11,
            'line_height' => 1.6,
            'primary_color' => '#1f2937',
            'secondary_color' => '#4b5563',
            'accent_color' => '#dc2626',
            'background_color' => '#ffffff',
            'text_color' => '#111827',
            'border_color' => '#d1d5db',
            'header_bg_color' => '#f9fafb',
            'total_bg_color' => '#fef3c7',
            'tax_table_header_bg' => '#f3f4f6',
            'show_facility_logo' => false,
            'facility_logo_path' => null,
            'show_facility_info' => true,
            'show_tax_breakdown' => true,
            'show_daily_charges_detail' => true,
            'show_qr_code' => false,
            'qr_code_data' => null,
            'header_html' => null,
            'footer_html' => null,
            'show_page_numbers' => true,
            'table_header_bg' => '#f9fafb',
            'table_row_even_bg' => '#ffffff',
            'table_row_odd_bg' => '#f9fafb',
            'table_border_color' => '#e5e7eb',
        ];

        $configKey = $type === 'invoice' ? 'pdf.invoice' : 'pdf.receipt';
        $templateConfig = config($configKey, []);

        return array_merge($baseConfig, $templateConfig);
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
            'defaultFont' => 'YuMincho', // Windows標準フォントをデフォルトに
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
        $pdf = $this->generatePdfFromInvoice($invoice, 'invoice');
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
        $pdf = $this->generatePdfFromInvoice($invoice, 'invoice');

        return $pdf->stream();
    }

    /**
     * 領収書PDFを生成する
     */
    public function generateReceiptPdf(MonthlyInvoice $invoice): DomPdfInstance
    {
        return $this->generatePdfFromInvoice($invoice, 'receipt');
    }

    /**
     * 指定年月の全入居者分請求書PDFを一括ZIPファイルにアーカイブする
     *
     * @param  string  $yearMonth  'YYYY-MM'
     * @return string 作成されたZIPファイルの一時パス
     */
    public function generateMonthlyZip(string $yearMonth): string
    {
        $invoices = MonthlyInvoice::with('resident')
            ->forYearMonth($yearMonth)
            ->get();

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
                $pdf = $this->generateInvoicePdf($invoice);
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