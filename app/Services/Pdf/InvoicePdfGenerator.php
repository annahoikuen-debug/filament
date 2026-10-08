<?php

namespace App\Services\Pdf;

use App\DTOs\Pdf\InvoicePdfData;
use App\DTOs\Pdf\ReceiptPdfData;
use App\Models\MonthlyInvoice;
use App\Services\Pdf\Contracts\RendererInterface;
use App\Services\Pdf\DataProviders\InvoiceDataProvider;
use App\Services\Pdf\Renderers\DomPdfRenderer;
use App\Services\Pdf\Templates\InvoiceTemplate;
use App\Services\Pdf\Templates\ReceiptTemplate;
use App\Services\Pdf\TemplateSettingsService;

class InvoicePdfGenerator
{
    public function __construct(
        private InvoiceDataProvider $dataProvider,
        private InvoiceTemplate $invoiceTemplate,
        private ReceiptTemplate $receiptTemplate,
        private RendererInterface $renderer,
        private TemplateSettingsService $templateSettings,
    ) {}

    public function generateInvoice(MonthlyInvoice $invoice, bool $download = false, ?array $facility = null)
    {
        $data = $this->dataProvider->getInvoiceData($invoice, $facility);
        $templateConfig = $this->templateSettings->getSettings('invoice');
        $html = $this->invoiceTemplate->render(array_merge($data->toArray(), ['template' => $templateConfig]));

        if ($download) {
            return $this->renderer->download($html, $this->invoiceFilename($invoice));
        }

        return $this->renderer->render($html);
    }

    public function streamInvoice(MonthlyInvoice $invoice, ?array $facility = null)
    {
        $data = $this->dataProvider->getInvoiceData($invoice, $facility);
        $templateConfig = $this->templateSettings->getSettings('invoice');
        $html = $this->invoiceTemplate->render(array_merge($data->toArray(), ['template' => $templateConfig]));
        return $this->renderer->stream($html, $this->invoiceFilename($invoice));
    }

    public function generateReceipt(MonthlyInvoice $invoice, bool $download = false, ?array $facility = null)
    {
        $data = $this->dataProvider->getReceiptData($invoice, $facility);
        $templateConfig = $this->templateSettings->getSettings('receipt');
        $html = $this->receiptTemplate->render(array_merge($data->toArray(), ['template' => $templateConfig]));

        if ($download) {
            return $this->renderer->download($html, $this->receiptFilename($invoice));
        }

        return $this->renderer->render($html);
    }

    public function streamReceipt(MonthlyInvoice $invoice, ?array $facility = null)
    {
        $data = $this->dataProvider->getReceiptData($invoice, $facility);
        $templateConfig = $this->templateSettings->getSettings('receipt');
        $html = $this->receiptTemplate->render(array_merge($data->toArray(), ['template' => $templateConfig]));
        return $this->renderer->stream($html, $this->receiptFilename($invoice));
    }

    public function previewInvoice(MonthlyInvoice $invoice, ?array $facility = null): string
    {
        $data = $this->dataProvider->getInvoiceData($invoice, $facility);
        $templateConfig = $this->templateSettings->getSettings('invoice');
        return $this->invoiceTemplate->render(array_merge($data->toArray(), ['template' => $templateConfig]));
    }

    public function previewReceipt(MonthlyInvoice $invoice, ?array $facility = null): string
    {
        $data = $this->dataProvider->getReceiptData($invoice, $facility);
        $templateConfig = $this->templateSettings->getSettings('receipt');
        return $this->receiptTemplate->render(array_merge($data->toArray(), ['template' => $templateConfig]));
    }

    /**
     * DTOデータから直接請求書HTMLを生成（キャッシュ用など）
     */
    public function previewInvoiceFromData(array $data): string
    {
        $templateConfig = $this->templateSettings->getSettings('invoice');
        return $this->invoiceTemplate->render(array_merge($data, ['template' => $templateConfig]));
    }

    /**
     * 月次一括生成（従来版：配列で返す）
     *
     * @return array<int, array{filename: string, content: string}>
     */
    public function generateMonthlyBatch(string $yearMonth, ?int $facilityId = null): array
    {
        $invoicesData = $this->dataProvider->getMonthlyInvoicesData($yearMonth, $facilityId);
        $templateConfig = $this->templateSettings->getSettings('invoice');
        $results = [];

        foreach ($invoicesData as $data) {
            $html = $this->invoiceTemplate->render(array_merge($data->toArray(), ['template' => $templateConfig]));
            $results[] = [
                'filename' => $this->batchFilename($data),
                'content' => $this->renderer->render($html),
            ];
        }

        return $results;
    }

    /**
     * 月次一括生成（ストリーミング版：Generatorで返す、メモリ効率化）
     *
     * @return \Generator<int, array{html: string, filename: string}>
     */
    public function generateMonthlyBatchStream(string $yearMonth, ?int $facilityId = null): \Generator
    {
        $invoicesData = $this->dataProvider->getMonthlyInvoicesData($yearMonth, $facilityId);
        $templateConfig = $this->templateSettings->getSettings('invoice');

        foreach ($invoicesData as $data) {
            yield [
                'html' => $this->invoiceTemplate->render(array_merge($data->toArray(), ['template' => $templateConfig])),
                'filename' => $this->batchFilename($data),
            ];
        }
    }

    /**
     * 月次一括ZIP生成（ストリーミング版：直接ZIPに書き込み、中間ファイルなし）
     */
    public function generateMonthlyZipStream(string $yearMonth, \ZipArchive $zip, ?int $facilityId = null): int
    {
        if ($this->renderer instanceof DomPdfRenderer) {
            $items = [];
            foreach ($this->generateMonthlyBatchStream($yearMonth, $facilityId) as $item) {
                $items[] = $item;
            }
            return $this->renderer->renderBatchToZip($items, $zip);
        }

        // フォールバック: 従来方式
        $count = 0;
        foreach ($this->generateMonthlyBatchStream($yearMonth, $facilityId) as $item) {
            $zip->addFromString($item['filename'], $this->renderer->render($item['html']));
            $count++;
        }
        return $count;
    }

    private function invoiceFilename(MonthlyInvoice $invoice): string
    {
        return sprintf(
            '請求書_%s_%s様_%s.pdf',
            $invoice->billing_year_month,
            $invoice->resident->name,
            $invoice->resident->room_number
        );
    }

    private function receiptFilename(MonthlyInvoice $invoice): string
    {
        return sprintf(
            '領収証_%s_%s様_%s.pdf',
            $invoice->billing_year_month,
            $invoice->resident->name,
            $invoice->resident->room_number
        );
    }

    private function batchFilename(InvoicePdfData $data): string
    {
        return sprintf(
            '【%s号室】%s様_請求書_%s.pdf',
            $data->resident->roomNumber,
            $data->resident->name,
            $data->billingYearMonth
        );
    }
}