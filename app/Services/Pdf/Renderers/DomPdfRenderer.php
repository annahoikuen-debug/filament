<?php

namespace App\Services\Pdf\Renderers;

use App\Services\Pdf\Contracts\FontRegistryInterface;
use App\Services\Pdf\Contracts\RendererInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Response as ResponseFacade;

class DomPdfRenderer implements RendererInterface
{
    public function __construct(
        private FontRegistryInterface $fontRegistry,
        private array $defaultOptions = [],
    ) {}

    public function render(string $html, array $options = []): string
    {
        $pdf = $this->createPdfInstance();
        $pdf->loadHtml($html);
        return $pdf->output();
    }

    public function stream(string $html, string $filename): Response
    {
        $pdf = $this->createPdfInstance();
        $pdf->loadHtml($html);
        $content = $pdf->output();

        return ResponseFacade::make($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function download(string $html, string $filename): Response
    {
        $pdf = $this->createPdfInstance();
        $pdf->loadHtml($html);
        $content = $pdf->output();

        return ResponseFacade::make($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * バッチ生成用：HTML配列から直接ZIPに書き込み（中間ファイルなし）
     * 同一インスタンスを再利用してフォント登録のオーバーヘッドを削減
     *
     * @param  array<int, array{html: string, filename: string}>  $items
     * @param  \ZipArchive  $zip
     * @return int 追加したファイル数
     */
    public function renderBatchToZip(array $items, \ZipArchive $zip): int
    {
        $pdf = $this->createPdfInstance();
        $count = 0;

        foreach ($items as $item) {
            $pdf->loadHtml($item['html']);
            $zip->addFromString($item['filename'], $pdf->output());
            $count++;
        }

        return $count;
    }

    /**
     * 新しいDomPDFインスタンスを作成（フォント登録済み）
     */
    private function createPdfInstance(): Dompdf
    {
        $options = new Options(array_merge([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'fontHeightRatio' => $this->defaultOptions['fontHeightRatio'] ?? 1.6,
            'defaultFont' => $this->defaultOptions['defaultFont'] ?? 'ipaexg',
            'font_dir' => storage_path('fonts'),
            'font_cache' => storage_path('fonts'),
            'tempDir' => storage_path('app/temp/dompdf'),
            'enable_font_subsetting' => true,
            'chroot' => storage_path('app'),
        ], $this->defaultOptions));

        $pdf = new Dompdf($options);
        $this->fontRegistry->register($pdf);

        return $pdf;
    }
}