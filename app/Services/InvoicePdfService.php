<?php

namespace App\Services;

use App\Models\MonthlyInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
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
        // N+1防止: 必要なリレーションを事前にロード
        $invoice->loadMissing([
            'resident.dailyCharges' => function ($query) use ($invoice) {
                $query->forYearMonth($invoice->billing_year_month)
                    ->with('chargeItem')
                    ->orderBy('date');
            },
        ]);

        return $this->generatePdfFromInvoice($invoice, 'invoice', $facility);
    }

    /**
     * 共通PDF生成メソッド（請求書・領収書を統合）
     *
     * @param  string  $type  'invoice' または 'receipt'
     */
    /**
     * ビュー名とテンプレートデータを組み立てる
     * （テストからBladeビューを直接レンダリングしてレイアウト検証できるように公開）
     *
     * @return array{0: string, 1: array} [ビュー名, データ]
     */
    public function prepareViewData(MonthlyInvoice $invoice, string $type = 'invoice', ?array $facility = null): array
    {
        // N+1防止: 未ロードの場合のみロード（generateInvoicePdf/generateReceiptPdfで事前ロード済みの場合はスキップ）
        $invoice->loadMissing([
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
                $cacheKey = 'qr_invoice_' . md5($qrCodeData);
                $templateConfig['qr_code_data'] = $this->generatePaymentQrCode($qrCodeData, $cacheKey);
            } else {
                // 領収書の場合は検証用QRコード
                $qrCodeData = $this->getReceiptVerificationQrCodeData($invoice);
                $cacheKey = 'qr_receipt_' . md5($qrCodeData);
                $templateConfig['qr_code_data'] = $this->generatePaymentQrCode($qrCodeData, $cacheKey);
            }
        }

        $data = [
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
        ];

        return [$view, $data];
    }

    public function generatePdfFromInvoice(MonthlyInvoice $invoice, string $type = 'invoice', ?array $facility = null): DomPdfInstance
    {
        [$view, $data] = $this->prepareViewData($invoice, $type, $facility);

        $pdf = Pdf::loadView($view, $data);

        // 日本語フォント設定を適用
        $this->applyJapaneseFontSettings($pdf, $data['template']);

        // 余白・用紙設定を適用
        $this->applyMargins($pdf, $data['template']);

        return $pdf;
    }

    /**
     * テンプレート設定を取得（config からマージ）
     */
    /**
     * テンプレート設定を取得（config からマージ）
     */
    public function getTemplateConfig(string $type): array
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
     * QRコードを生成するヘルパーメソッド（キャッシュ対応）
     *
     * @param string $data エンコードするデータ
     * @param string|null $cacheKey キャッシュキー（指定時はキャッシュから取得・保存）
     * @return string base64エンコードされたSVGデータURI
     */
    private function generatePaymentQrCode(string $data, ?string $cacheKey = null): string
    {
        // キャッシュキーが指定されている場合はキャッシュから取得を試みる
        if ($cacheKey !== null) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            $rendererStyle = new \BaconQrCode\Renderer\RendererStyle\RendererStyle(70);
            $imageBackEnd = new \BaconQrCode\Renderer\Image\SvgImageBackEnd();
            $renderer = new \BaconQrCode\Renderer\ImageRenderer($rendererStyle, $imageBackEnd);
            $writer = new \BaconQrCode\Writer($renderer);
            // 日本語を含むデータを扱うため UTF-8 を指定（既定の ISO-8859-1 では多バイト文字のエンコードに失敗する）
            $svg = $writer->writeString($data, 'UTF-8');

            $result = 'data:image/svg+xml;base64,'.base64_encode($svg);

            // キャッシュに保存（24時間）
            if ($cacheKey !== null) {
                Cache::put($cacheKey, $result, now()->addHours(24));
            }

            return $result;
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
        // N+1防止: 必要なリレーションを事前にロード
        $invoice->loadMissing([
            'resident.dailyCharges' => function ($query) use ($invoice) {
                $query->forYearMonth($invoice->billing_year_month)
                    ->with('chargeItem')
                    ->orderBy('date');
            },
        ]);

        return $this->generatePdfFromInvoice($invoice, 'receipt', $facility);
    }

/**
     * 指定年月の全入居者分請求書PDFを一括ZIPファイルにアーカイブする（同期版・互換性維持）
     *
     * @param  string  $yearMonth  'YYYY-MM'
     * @return string 作成されたZIPファイルの一時パス
     */
    public function generateMonthlyZip(string $yearMonth, ?int $facilityId = null): string
    {
        return $this->generateMonthlyZipSync($yearMonth, $facilityId);
    }

    /**
     * 指定年月の全入居者分請求書PDFを一括ZIPファイルにアーカイブする（同期版・内部実装）
     *
     * @param  string  $yearMonth  'YYYY-MM'
     * @return string 作成されたZIPファイルの一時パス
     */
    public function generateMonthlyZipSync(string $yearMonth, ?int $facilityId = null): string
    {
        $query = MonthlyInvoice::with([
            'resident.dailyCharges' => function ($q) use ($yearMonth) {
                $q->forYearMonth($yearMonth)
                  ->with('chargeItem')
                  ->orderBy('date');
            },
        ])->forYearMonth($yearMonth);

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

    /**
     * 非同期で月次ZIP生成ジョブをディスパッチする
     *
     * @param  string  $yearMonth  'YYYY-MM'
     * @return string ジョブID
     */
    public function generateMonthlyZipAsync(string $yearMonth, ?int $facilityId = null): string
    {
        $jobId = 'zip_' . $yearMonth . '_' . ($facilityId ?? 'all') . '_' . uniqid();
        \App\Jobs\GenerateMonthlyZipJob::dispatch($yearMonth, $facilityId, $jobId);

        return $jobId;
    }

    /**
     * 非同期ジョブの進捗を取得する
     *
     * @return array{percent: int, status: string, message: string, updated_at: string}|null
     */
    public function getZipProgress(string $jobId): ?array
    {
        return \App\Jobs\GenerateMonthlyZipJob::getProgress($jobId);
    }

    /**
     * 非同期ジョブの結果（ZIPファイルパス）を取得する
     */
    public function getZipResult(string $jobId): ?string
    {
        return \App\Jobs\GenerateMonthlyZipJob::getResult($jobId);
    }

    /**
     * 進捗キャッシュをクリアする
     */
    public function clearZipProgress(string $jobId): void
    {
        \App\Jobs\GenerateMonthlyZipJob::clearProgress($jobId);
    }

    /**
     * キャッシュされたPDFパスを取得する
     */
    public function getCachedPdfPath(string $yearMonth, int $invoiceId): string
    {
        return storage_path("app/invoices/{$yearMonth}/invoice_{$invoiceId}.pdf");
    }

    /**
     * PDFがキャッシュされているか確認する
     */
    public function hasCachedPdf(string $yearMonth, int $invoiceId): bool
    {
        return File::exists($this->getCachedPdfPath($yearMonth, $invoiceId));
    }

    /**
     * キャッシュされたPDFを取得する（存在しない場合は生成してキャッシュ）
     * アトミック書き込みで競合状態を防止
     */
    public function getOrGenerateCachedPdf(MonthlyInvoice $invoice, ?array $facility = null): string
    {
        $yearMonth = $invoice->billing_year_month;
        $cachePath = $this->getCachedPdfPath($yearMonth, $invoice->id);

        // 既に存在する場合は即座に返す（読み取りは競合しない）
        if (File::exists($cachePath)) {
            return File::get($cachePath);
        }

        $pdf = $this->generateInvoicePdf($invoice, $facility);
        $pdfContent = $pdf->output();

        // アトミック書き込み: 一時ファイルに書いてから rename（POSIXでアトミック）
        $tempPath = $cachePath . '.tmp.' . uniqid('', true);
        File::ensureDirectoryExists(dirname($cachePath));
        File::put($tempPath, $pdfContent);

        // rename でアトミックに移動（既存ファイルがあれば上書き）
        @rename($tempPath, $cachePath);

        // 稀に rename 後にファイルが消えている場合のフォールバック
        if (! File::exists($cachePath)) {
            // 別プロセスが書き込んだ可能性 → 再読み取り
            if (File::exists($cachePath)) {
                return File::get($cachePath);
            }
            // それでもなければ自分で書き込み直し（最後の手段）
            File::put($cachePath, $pdfContent);
        }

        return $pdfContent;
    }

    /**
     * 指定年月のキャッシュを全削除する
     */
    public function clearInvoiceCache(string $yearMonth): void
    {
        $cacheDir = storage_path("app/invoices/{$yearMonth}");
        if (File::exists($cacheDir)) {
            File::deleteDirectory($cacheDir);
        }
    }

    /**
     * チャンク処理でPDFを生成し、コールバックで処理する（メモリ効率化版）
     *
     * @param  string  $yearMonth  'YYYY-MM'
     * @param  callable  $callback  function(MonthlyInvoice $invoice, string $pdfContent): void
     * @param  int  $chunkSize  チャンクサイズ
     */
    public function chunkGeneratePdfs(
        string $yearMonth,
        callable $callback,
        ?int $facilityId = null,
        int $chunkSize = 10
    ): void {
        $query = MonthlyInvoice::with('resident')
            ->forYearMonth($yearMonth);

        if ($facilityId) {
            $query->whereHas('resident', function ($q) use ($facilityId) {
                $q->where('facility_id', $facilityId);
            });
        }

        $query->orderBy('id')
            ->chunkById($chunkSize, function ($invoices) use ($callback, $facility) {
                foreach ($invoices as $invoice) {
                    $pdf = $this->generateInvoicePdf($invoice, $facility);
                    $callback($invoice, $pdf->output());
                }

                return true;
            });
    }
}