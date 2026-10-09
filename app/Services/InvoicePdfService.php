<?php

namespace App\Services;

use App\Models\MonthlyInvoice;
use App\Models\ServiceInvoice;
use App\Services\Pdf\Contracts\FontRegistryInterface;
use App\Services\Pdf\Contracts\RendererInterface;
use App\Services\Pdf\InvoicePdfGenerator;
use App\Services\Pdf\TemplateSettingsService;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Http\Response;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\View\Factory as ViewFactory;

class InvoicePdfService
{
    public function __construct(
        private InvoicePdfGenerator $generator,
        private TemplateSettingsService $templateSettings,
        private FontRegistryInterface $fontRegistry,
        private RendererInterface $renderer,
    ) {}

    /**
     * 請求書PDFを生成する
     */
    public function generateInvoicePdf(MonthlyInvoice $invoice, ?array $facility = null): DomPdfInstance
    {
        $html = $this->generator->previewInvoice($invoice, $facility);
        return $this->createDomPdfInstance($html);
    }

    /**
     * 領収書PDFを生成する
     */
    public function generateReceiptPdf(MonthlyInvoice $invoice, ?array $facility = null): DomPdfInstance
    {
        $html = $this->generator->previewReceipt($invoice, $facility);
        return $this->createDomPdfInstance($html);
    }

    private function createDomPdfInstance(string $html): DomPdfInstance
    {
        $options = new \Dompdf\Options([
            'font_dir' => storage_path('fonts'),
            'font_cache' => storage_path('fonts'),
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'ipaexg',
        ]);
        $dompdf = new \Dompdf\Dompdf($options);
        $this->fontRegistry->register($dompdf);
        $dompdf->loadHtml($html);
        $dompdf->render();

        $pdf = new \Barryvdh\DomPDF\PDF(
            $dompdf,
            app(ConfigRepository::class),
            app(Filesystem::class),
            app(ViewFactory::class)
        );

        return $pdf;
    }

    /**
     * 請求書PDFをHTTPレスポンスとしてダウンロードする
     */
    public function downloadPdf(MonthlyInvoice $invoice): Response
    {
        return $this->generator->generateInvoice($invoice, true, null);
    }

    /**
     * 請求書PDFをブラウザ内でプレビュー表示する
     */
    public function streamPdf(MonthlyInvoice $invoice): Response
    {
        return $this->generator->streamInvoice($invoice, null);
    }

    /**
     * HTMLプレビュー表示（Webアプリ用・同一テンプレートをHTMLで返却）
     *
     * @param  \App\Models\MonthlyInvoice  $invoice
     * @param  string  $type  'invoice' または 'receipt'
     * @return \Illuminate\Http\Response
     */
    public function previewHtml(MonthlyInvoice $invoice, string $type = 'invoice'): Response
    {
        if (! in_array($type, ['invoice', 'receipt'], true)) {
            abort(404);
        }

        // 領収書プレビューは入金済みのみ許可
        if ($type === 'receipt' && $invoice->status !== \App\Enums\InvoiceStatus::Paid) {
            abort(404);
        }

        try {
            $html = $type === 'invoice'
                ? $this->generator->previewInvoice($invoice, $invoice->resident->facility?->toConfigArray())
                : $this->generator->previewReceipt($invoice, $invoice->resident->facility?->toConfigArray());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Preview HTML generation failed', [
                'invoice_id' => $invoice->id,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8');
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
        $results = $this->generator->generateMonthlyBatch($yearMonth, $facilityId);

        $zipPath = storage_path("app/temp/請求書一括_{$yearMonth}.zip");

        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($zipPath));

        if (\Illuminate\Support\Facades\File::exists($zipPath)) {
            \Illuminate\Support\Facades\File::delete($zipPath);
        }

        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIPファイルの作成に失敗しました。');
        }

        try {
            foreach ($results as $result) {
                $zip->addFromString($result['filename'], $result['content']);
            }

            $zip->close();

            return $zipPath;
        } catch (\Throwable $e) {
            $zip->close();
            if (\Illuminate\Support\Facades\File::exists($zipPath)) {
                \Illuminate\Support\Facades\File::delete($zipPath);
            }
            throw $e;
        }
    }

    /**
     * 指定年月の請求書PDFをサービス種別フォルダ分けでZIPアーカイブする
     *
     * @param  string  $yearMonth  'YYYY-MM'
     * @param  int|null  $facilityId  施設ID
     * @param  bool  $includeMerged  統合請求書（住居費＋介護サービス）も含めるか
     * @return string 作成されたZIPファイルの一時パス
     */
    public function generateMonthlyZipByCategory(
        string $yearMonth,
        ?int $facilityId = null,
        bool $includeMerged = true
    ): string {
        $mergeService = app(\App\Services\InvoiceMergeService::class);

        // 対象月の請求データを取得
        $query = MonthlyInvoice::where('billing_year_month', $yearMonth)
            ->where('status', '!=', \App\Enums\InvoiceStatus::Unbilled);
        if ($facilityId) {
            $query->where('facility_id', $facilityId);
        }
        $invoices = $query->with('resident.facility')->get();

        $zipPath = storage_path("app/temp/請求書一括_カテゴリ別_{$yearMonth}.zip");

        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($zipPath));

        if (\Illuminate\Support\Facades\File::exists($zipPath)) {
            \Illuminate\Support\Facades\File::delete($zipPath);
        }

        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIPファイルの作成に失敗しました。');
        }

        try {
            // サービス種別ラベル
            $serviceLabels = [
                'visiting_care' => '訪問介護',
                'day_care' => '通所介護',
                'care_planning' => '居宅介護支援',
                'home_nursing' => '訪問看護',
                'short_stay' => '短期入所生活介護',
                'welfare_equipment' => '福祉用具貸与',
                'home_modification' => '居宅介護住宅改修',
                'other' => 'その他',
            ];

            // 1. 住居費請求書フォルダ
            foreach ($invoices as $invoice) {
                $html = $this->generator->previewInvoice($invoice, $invoice->resident->facility?->toConfigArray());
                $pdfContent = $this->renderer->render($html);

                $fileName = sprintf(
                    '住居費/請求書_%s_%s号室_%s様.pdf',
                    $invoice->billing_year_month,
                    $invoice->resident->room_number,
                    $invoice->resident->name
                );
                $zip->addFromString($fileName, $pdfContent);
            }

            // 2. 介護サービス種別フォルダ
            $serviceInvoices = ServiceInvoice::where('billing_year_month', $yearMonth)
                ->whereNotNull('pdf_path')
                ->where('status', '!=', 'draft');
            if ($facilityId) {
                $serviceInvoices->where('facility_id', $facilityId);
            }
            $serviceInvoices = $serviceInvoices->with('resident')->get();

            foreach ($serviceInvoices as $si) {
                if ($si->hasPdf()) {
                    $pdfContent = \Illuminate\Support\Facades\File::get($si->pdf_full_path);
                    $label = $serviceLabels[$si->service_type->value] ?? $si->service_type->value;

                    $fileName = sprintf(
                        '%s/請求書_%s_%s号室_%s様_%s.pdf',
                        $label,
                        $si->billing_year_month,
                        $si->resident->room_number,
                        $si->resident->name,
                        $label
                    );
                    $zip->addFromString($fileName, $pdfContent);
                }
            }

            // 3. 統合請求書フォルダ（オプション）
            if ($includeMerged) {
                foreach ($invoices as $invoice) {
                    $carePdfs = $mergeService->getCareServicePdfs($invoice);
                    if (!empty($carePdfs)) {
                        $content = $mergeService->mergeInvoices($invoice, $carePdfs);

                        $fileName = sprintf(
                            '統合請求書/統合請求書_%s_%s号室_%s様.pdf',
                            $invoice->billing_year_month,
                            $invoice->resident->room_number,
                            $invoice->resident->name
                        );
                        $zip->addFromString($fileName, $content);
                    }
                }
            }

            // 4. サマリーCSV
            $csvContent = $this->generateCategorySummaryCsv($yearMonth, $facilityId);
            $zip->addFromString('サマリー/請求内訳サマリー_' . $yearMonth . '.csv', $csvContent);

            $zip->close();

            return $zipPath;
        } catch (\Throwable $e) {
            $zip->close();
            if (\Illuminate\Support\Facades\File::exists($zipPath)) {
                \Illuminate\Support\Facades\File::delete($zipPath);
            }
            throw $e;
        }
    }

    /**
     * カテゴリ別サマリーCSV生成
     */
    private function generateCategorySummaryCsv(string $yearMonth, ?int $facilityId): string
    {
        $query = MonthlyInvoice::where('billing_year_month', $yearMonth)
            ->where('status', '!=', \App\Enums\InvoiceStatus::Unbilled);
        if ($facilityId) {
            $query->where('facility_id', $facilityId);
        }
        $invoices = $query->with('resident')->get();

        $serviceLabels = [
            'visiting_care' => '訪問介護',
            'day_care' => '通所介護',
            'care_planning' => '居宅介護支援',
            'home_nursing' => '訪問看護',
            'short_stay' => '短期入所生活介護',
            'welfare_equipment' => '福祉用具貸与',
            'home_modification' => '居宅介護住宅改修',
            'other' => 'その他',
        ];

        // 介護サービス請求を取得
        $serviceQuery = ServiceInvoice::where('billing_year_month', $yearMonth)
            ->where('status', '!=', 'draft');
        if ($facilityId) {
            $serviceQuery->where('facility_id', $facilityId);
        }
        $serviceInvoices = $serviceQuery->with('resident')->get()->groupBy('resident_id');

        $rows = [];
        $rows[] = ['請求年月', '部屋番号', '入居者名', '区分', '項目', '金額(税抜)', '消費税', '税込合計'];

        foreach ($invoices as $invoice) {
            $resident = $invoice->resident;

            // 住居費
            $rows[] = [
                $yearMonth,
                $resident->room_number,
                $resident->name,
                '住居費',
                '家賃',
                $invoice->rent_subtotal,
                0,
                $invoice->rent_subtotal,
            ];
            $rows[] = [
                $yearMonth,
                $resident->room_number,
                $resident->name,
                '住居費',
                '管理費',
                $invoice->management_fee_subtotal,
                round($invoice->management_fee_subtotal * 0.1),
                $invoice->management_fee_subtotal + round($invoice->management_fee_subtotal * 0.1),
            ];
            if ($invoice->service_subtotal > 0) {
                $rows[] = [
                    $yearMonth,
                    $resident->room_number,
                    $resident->name,
                    '住居費',
                    '自費サービス',
                    $invoice->service_subtotal,
                    round($invoice->service_subtotal * 0.1),
                    $invoice->service_subtotal + round($invoice->service_subtotal * 0.1),
                ];
            }

            // 介護サービス
            $residentServices = $serviceInvoices[$resident->id] ?? collect();
            foreach ($residentServices as $si) {
                $label = $serviceLabels[$si->service_type->value] ?? $si->service_type->value;
                $rows[] = [
                    $yearMonth,
                    $resident->room_number,
                    $resident->name,
                    '介護保険',
                    $label,
                    $si->amount,
                    $si->tax_amount,
                    $si->total_with_tax,
                ];
            }

            // 合計行
            $housingTotal = $invoice->total_with_tax;
            $careTotal = $residentServices->sum('total_with_tax');
            if ($careTotal > 0) {
                $rows[] = [
                    $yearMonth,
                    $resident->room_number,
                    $resident->name,
                    '合計',
                    '総合計',
                    '',
                    '',
                    $housingTotal + $careTotal,
                ];
            }
        }

        // UTF-8 BOM付きCSV
        $bom = "\xEF\xBB\xBF";
        $csv = $bom;
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string)$v) . '"', $row)) . "\n";
        }

        return $csv;
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
        return \Illuminate\Support\Facades\File::exists($this->getCachedPdfPath($yearMonth, $invoiceId));
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
        if (\Illuminate\Support\Facades\File::exists($cachePath)) {
            return \Illuminate\Support\Facades\File::get($cachePath);
        }

        $html = $this->generator->previewInvoice($invoice, $facility);
        $pdf = $this->createDomPdfInstance($html);
        $pdfContent = $pdf->output();

        // アトミック書き込み: 一時ファイルに書いてから rename（POSIXでアトミック）
        $tempPath = $cachePath . '.tmp.' . uniqid('', true);
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($cachePath));
        \Illuminate\Support\Facades\File::put($tempPath, $pdfContent);

        // rename でアトミックに移動（既存ファイルがあれば上書き）
        @rename($tempPath, $cachePath);

        // 稀に rename 後にファイルが消えている場合のフォールバック
        if (! \Illuminate\Support\Facades\File::exists($cachePath)) {
            // 別プロセスが書き込んだ可能性 → 再読み取り
            if (\Illuminate\Support\Facades\File::exists($cachePath)) {
                return \Illuminate\Support\Facades\File::get($cachePath);
            }
            // それでもなければ自分で書き込み直し（最後の手段）
            \Illuminate\Support\Facades\File::put($cachePath, $pdfContent);
        }

        return $pdfContent;
    }

    /**
     * 指定年月のキャッシュを全削除する
     */
    public function clearInvoiceCache(string $yearMonth): void
    {
        $cacheDir = storage_path("app/invoices/{$yearMonth}");
        if (\Illuminate\Support\Facades\File::exists($cacheDir)) {
            \Illuminate\Support\Facades\File::deleteDirectory($cacheDir);
        }
    }

    /**
     * チャンク処理でPDFを生成し、コールバックで処理する（メモリ効率化版・非推奨）
     *
     * @deprecated Use InvoicePdfGenerator::generateMonthlyBatchStream() instead for memory-efficient streaming.
     * @param  string  $yearMonth  'YYYY-MM'
     * @param  callable  $callback  function(MonthlyInvoice $invoice, string $pdfContent): void
     * @param  int  $facilityId 施設ID
     * @param  int  $chunkSize  チャンクサイズ
     */
    public function chunkGeneratePdfs(
        string $yearMonth,
        callable $callback,
        ?int $facilityId = null,
        int $chunkSize = 10
    ): void {
        $dataProvider = app(\App\Services\Pdf\DataProviders\InvoiceDataProvider::class);
        $invoicesData = $dataProvider->getMonthlyInvoicesData($yearMonth, $facilityId);

        foreach (array_chunk($invoicesData, $chunkSize) as $chunk) {
            foreach ($chunk as $data) {
                $html = $this->generator->previewInvoiceFromData($data);
                $callback(null, $html); // 互換性のため第1引数はnull
            }
        }
    }

    /**
     * テンプレート設定を取得（DB優先、configフォールバック）
     *
     * @param  string  $type  'invoice' または 'receipt'
     */
    public function getTemplateConfig(string $type): array
    {
        return $this->templateSettings->getSettings($type);
    }

    /**
     * ビュー名とテンプレートデータを組み立てる（後方互換性用・非推奨）
     *
     * @deprecated Use InvoicePdfGenerator::previewInvoice() or previewReceipt() instead.
     * @return array{0: string, 1: array} [ビュー名, データ]
     */
    public function prepareViewData(MonthlyInvoice $invoice, string $type = 'invoice', ?array $facility = null): array
    {
        $dataProvider = app(\App\Services\Pdf\DataProviders\InvoiceDataProvider::class);

        if ($type === 'invoice') {
            $data = $dataProvider->getInvoiceData($invoice, $facility);
        } else {
            $data = $dataProvider->getReceiptData($invoice, $facility);
        }

        $templateConfig = $this->getTemplateConfig($type);

        // QRコードデータを設定（設定で有効になっている場合）
        if ($templateConfig['show_qr_code'] ?? false) {
            if ($type === 'invoice') {
                $qrCodeData = $this->getBankTransferQrCodeData($invoice, $facility ?? config('facility'));
                $cacheKey = 'qr_invoice_' . md5($qrCodeData);
                $templateConfig['qr_code_data'] = $this->generatePaymentQrCode($qrCodeData, $cacheKey);
            } else {
                $qrCodeData = $this->getReceiptVerificationQrCodeData($invoice);
                $cacheKey = 'qr_receipt_' . md5($qrCodeData);
                $templateConfig['qr_code_data'] = $this->generatePaymentQrCode($qrCodeData, $cacheKey);
            }
        }

        $view = $type === 'invoice' ? 'pdf.invoice' : 'pdf.receipt';

        // CSSを取得（テンプレートクラスから）
        $templateClass = $type === 'invoice' 
            ? app(\App\Services\Pdf\Templates\InvoiceTemplate::class)
            : app(\App\Services\Pdf\Templates\ReceiptTemplate::class);
        $css = $templateClass->getCss();

        return [$view, [
            'data' => $data->toArray(),
            'template' => $templateConfig,
            'css' => $css,
        ]];
    }

    /**
     * 共通PDF生成メソッド（請求書・領収書を統合・後方互換性用・非推奨）
     *
     * @deprecated Use InvoicePdfGenerator::generateInvoice() or generateReceipt() instead.
     * @param  string  $type  'invoice' または 'receipt'
     */
    public function generatePdfFromInvoice(MonthlyInvoice $invoice, string $type = 'invoice', ?array $facility = null): DomPdfInstance
    {
        $html = $type === 'invoice' 
            ? $this->generator->previewInvoice($invoice, $facility)
            : $this->generator->previewReceipt($invoice, $facility);

        return $this->createDomPdfInstance($html);
    }

    /**
     * 振込用QRコードデータを生成（後方互換性用・非推奨）
     *
     * @deprecated Use InvoicePdfGenerator or a dedicated QR code service instead.
     * @param MonthlyInvoice $invoice 請求書データ
     * @param array $facility 施設情報
     * @return string QRコード用データ文字列
     */
    public function getBankTransferQrCodeData(MonthlyInvoice $invoice, array $facility): string
    {
        // 日本のQRコード規格（振込用）に準拠したデータを生成
        $data = sprintf(
            "STU{\n振:%s\n種:振込\n金:%d\n名:%s\n  共同:%s %s\n 口:%s %s\n 住:%s\nREF:%s-%s}",
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
     * 領収書用検証QRコードデータを生成（後方互換性用・非推奨）
     *
     * @deprecated Use InvoicePdfGenerator or a dedicated QR code service instead.
     * @param MonthlyInvoice $invoice 領収書データ
     * @return string QRコード用データ文字列
     */
    public function getReceiptVerificationQrCodeData(MonthlyInvoice $invoice): string
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
     * QRコードを生成するヘルパーメソッド（キャッシュ対応・後方互換性用・非推奨）
     *
     * @deprecated Use a dedicated QR code service (e.g., BaconQrCode directly) instead.
     * @param string $data エンコードするデータ
     * @param string|null $cacheKey キャッシュキー（指定時はキャッシュから取得・保存）
     * @return string base64エンコードされたSVGデータURI
     * @throws \RuntimeException QRコード生成に失敗した場合
     */
    public function generatePaymentQrCode(string $data, ?string $cacheKey = null): string
    {
        // キャッシュキーが指定されている場合はキャッシュから取得を試みる
        if ($cacheKey !== null) {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
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
                \Illuminate\Support\Facades\Cache::put($cacheKey, $result, now()->addHours(24));
            }

            return $result;
        } catch (\Throwable $e) {
            // QRコード生成失敗をログ出力（デバッグ用）
            \Illuminate\Support\Facades\Log::error('QRコード生成に失敗しました', [
                'data_length' => strlen($data),
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 例外を再スローして呼び出し元でハンドリングできるようにする
            throw new \RuntimeException('QRコードの生成に失敗しました: ' . $e->getMessage(), 0, $e);
        }
    }
}
