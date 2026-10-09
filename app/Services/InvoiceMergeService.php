<?php

namespace App\Services;

use App\Models\MonthlyInvoice;
use App\Models\ServiceInvoice;
use App\Services\Pdf\Contracts\FontRegistryInterface;
use App\Services\Pdf\Contracts\RendererInterface;
use App\Services\Pdf\InvoicePdfGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceMergeService
{
    public function __construct(
        private InvoicePdfGenerator $generator,
        private RendererInterface $renderer,
        private FontRegistryInterface $fontRegistry,
    ) {}

    /**
     * 住居費請求書と介護サービス請求書PDFを結合する
     *
     * @param  MonthlyInvoice  $housingInvoice  住居費請求書
     * @param  array  $careServicePdfs  サービス種別ごとのPDFパス ['visiting_care' => '/path/to/pdf', ...]
     * @param  array|null  $facility  施設設定配列
     * @return string 結合されたPDFのバイナリデータ
     */
    public function mergeInvoices(
        MonthlyInvoice $housingInvoice,
        array $careServicePdfs,
        ?array $facility = null
    ): string {
        $resident = $housingInvoice->resident;
        $facilityConfig = $facility ?? $resident->facility?->toConfigArray();

        // 1. 住居費請求書HTMLを生成
        $housingHtml = $this->generator->previewInvoice($housingInvoice, $facilityConfig);

        // 2. 表紙HTMLを生成
        $coverHtml = $this->generateCoverHtml($housingInvoice, $careServicePdfs, $facilityConfig);

        // 3. 介護サービス種別見出しHTMLを生成
        $serviceHeadersHtml = $this->generateServiceHeadersHtml($careServicePdfs);

        // 4. 全HTMLを結合（ページ区切りで）
        $mergedHtml = $this->combineHtml($coverHtml, $housingHtml, $serviceHeadersHtml);

        // 5. PDFレンダリング
        return $this->renderer->render($mergedHtml);
    }

    /**
     * 指定入居者・月の介護サービスPDFパスを取得
     */
    public function getCareServicePdfs(MonthlyInvoice $housingInvoice): array
    {
        $serviceInvoices = ServiceInvoice::where('resident_id', $housingInvoice->resident_id)
            ->where('billing_year_month', $housingInvoice->billing_year_month)
            ->whereNotNull('pdf_path')
            ->where('status', '!=', 'draft')
            ->get();

        $pdfs = [];
        foreach ($serviceInvoices as $si) {
            if ($si->hasPdf()) {
                $pdfs[$si->service_type->value] = $si->pdf_full_path;
            }
        }

        return $pdfs;
    }

    /**
     * 統合請求書を生成して保存
     */
    public function generateAndSaveMergedInvoice(
        MonthlyInvoice $housingInvoice,
        ?string $outputPath = null
    ): string {
        $careServicePdfs = $this->getCareServicePdfs($housingInvoice);

        if (empty($careServicePdfs)) {
            // 介護サービスPDFがない場合は住居費請求書のみ返す
            $html = $this->generator->previewInvoice($housingInvoice, $housingInvoice->resident->facility?->toConfigArray());
            $content = $this->renderer->render($html);
        } else {
            $content = $this->mergeInvoices($housingInvoice, $careServicePdfs);
        }

        if ($outputPath) {
            File::ensureDirectoryExists(dirname($outputPath));
            File::put($outputPath, $content);
        }

        return $content;
    }

    /**
     * 表紙HTML生成（サマリー）
     */
    private function generateCoverHtml(
        MonthlyInvoice $housingInvoice,
        array $careServicePdfs,
        ?array $facility
    ): string {
        $resident = $housingInvoice->resident;
        $yearMonth = $housingInvoice->billing_year_month;

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

        $careServices = [];
        $careTotal = 0;
        foreach ($careServicePdfs as $type => $path) {
            $si = ServiceInvoice::where('resident_id', $resident->id)
                ->where('billing_year_month', $yearMonth)
                ->where('service_type', $type)
                ->first();
            if ($si) {
                $careServices[] = [
                    'label' => $serviceLabels[$type] ?? $type,
                    'amount' => $si->amount,
                    'tax' => $si->tax_amount,
                    'total' => $si->total_with_tax,
                ];
                $careTotal += $si->total_with_tax;
            }
        }

        $housingTotal = $housingInvoice->total_with_tax;
        $grandTotal = $housingTotal + $careTotal;

        return view('pdf.merged-cover', [
            'resident' => $resident,
            'facility' => $facility,
            'billing_year_month' => $yearMonth,
            'housing' => [
                'rent' => $housingInvoice->rent_subtotal,
                'management' => $housingInvoice->management_fee_subtotal,
                'service' => $housingInvoice->service_subtotal,
                'total' => $housingTotal,
            ],
            'care_services' => $careServices,
            'care_total' => $careTotal,
            'grand_total' => $grandTotal,
            'generated_at' => now()->format('Y年m月d日 H:i'),
        ])->render();
    }

    /**
     * サービス種別見出しHTML生成
     */
    private function generateServiceHeadersHtml(array $careServicePdfs): string
    {
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

        $order = array_keys($serviceLabels);
        $htmlParts = [];

        foreach ($order as $type) {
            if (isset($careServicePdfs[$type])) {
                $label = $serviceLabels[$type] ?? $type;
                $htmlParts[] = <<<HTML
<div class="service-header-page" style="page-break-before: always;">
    <div style="height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; background: #f8fafc; border: 2px solid #e2e8f0;">
        <div style="font-size: 28px; font-weight: bold; color: #1e293b;">{$label} 請求書</div>
        <div style="font-size: 16px; color: #64748b; margin-top: 8px;">（外部システム発行PDFは別添）</div>
    </div>
</div>
HTML;
            }
        }

        return implode("\n", $htmlParts);
    }

    /**
     * HTMLを結合
     */
    private function combineHtml(string $coverHtml, string $housingHtml, string $serviceHeadersHtml): string
    {
        // 各HTMLからbody内容のみ抽出して結合
        $coverBody = $this->extractBody($coverHtml);
        $housingBody = $this->extractBody($housingHtml);
        $serviceBody = $this->extractBody($serviceHeadersHtml);

        // ヘッダー/フッター/スタイルは表紙から取得
        $head = $this->extractHead($coverHtml);

        return <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
{$head}
<style>
    @page {
        margin: 20mm 15mm;
        @bottom-center {
            content: "第 " counter(page) " 頁 / 計 " counter(pages) " 頁";
            font-size: 9px;
            color: #64748b;
        }
    }
    .service-header-page { page-break-before: always; }
</style>
</head>
<body>
{$coverBody}
{$housingBody}
{$serviceBody}
</body>
</html>
HTML;
    }

    /**
     * HTMLからhead部分を抽出
     */
    private function extractHead(string $html): string
    {
        if (preg_match('/<head[^>]*>(.*?)<\/head>/is', $html, $matches)) {
            return $matches[1];
        }
        return '<meta charset="UTF-8"><title>統合請求書</title>';
    }

    /**
     * HTMLからbody内容を抽出
     */
    private function extractBody(string $html): string
    {
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            return $matches[1];
        }
        return $html;
    }
}