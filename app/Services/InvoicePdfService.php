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
        $invoice->load([
            'resident.dailyCharges' => function ($query) use ($invoice) {
                $query->forYearMonth($invoice->billing_year_month)
                    ->with('chargeItem')
                    ->orderBy('date');
            },
        ]);

        $view = $type === 'invoice' ? 'invoices.pdf' : 'invoices.receipt';

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
        ]);

        $pdf->setPaper('a4', 'portrait');

        // 日本語フォント設定を適用
        $this->applyJapaneseFontSettings($pdf);

        return $pdf;
    }

    /**
     * 日本語フォント設定を適用
     *
     * Windows標準フォント（YuMincho, MS Gothic, Meiryo）を優先し、
     * プロジェクト同梱のNoto Sans JPをフォールバックとして使用
     */
    private function applyJapaneseFontSettings(DomPdfInstance $pdf): void
    {
        // Noto Sans JPフォントディレクトリを優先（存在する場合）
        $notoFontDir = resource_path('fonts/'.self::NOTO_SANS_JP_DIR);
        $fontDir = File::exists($notoFontDir) ? $notoFontDir : storage_path('fonts');

        $options = [
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'fontHeightRatio' => 1.25,
            'defaultFont' => 'YuMincho', // Windows標準フォントをデフォルトに
            'font_dir' => $fontDir,
            'font_cache' => storage_path('fonts'),
        ];

        $pdf->setOptions($options);
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

        $tempDir = storage_path('app/temp/invoices_'.$yearMonth.'_'.uniqid());
        $zipPath = storage_path("app/temp/請求書一括_{$yearMonth}.zip");

        try {
            File::ensureDirectoryExists($tempDir);

            if (File::exists($zipPath)) {
                File::delete($zipPath);
            }

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('ZIPファイルの作成に失敗しました。');
            }

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
        } finally {
            // 例外発生時も含めて一時ディレクトリをクリーンアップ
            if (File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }
}
