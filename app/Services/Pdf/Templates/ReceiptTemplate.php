<?php

namespace App\Services\Pdf\Templates;

use App\Services\Pdf\Contracts\TemplateInterface;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\File;

class ReceiptTemplate implements TemplateInterface
{
    public function render(array $data): string
    {
        $css = $this->getCss();
        
        $templateConfig = array_merge(
            config('pdf.default', []),
            config('pdf.receipt', []),
            $data['template'] ?? []
        );

        return View::make('pdf.receipt', [
            'data' => $data,
            'css' => $css,
            'template' => $templateConfig,
        ])->render();
    }

    public function getCss(): string
    {
        $cssPath = resource_path('css/pdf-receipt.css');
        
        if (File::exists($cssPath)) {
            return File::get($cssPath);
        }

        return $this->getDefaultCss();
    }

    public function getRequiredFonts(): array
    {
        return ['YuMincho', 'YuGothic', 'Meiryo', 'msgothic'];
    }

    private function getDefaultCss(): string
    {
        return <<<'CSS'
@page { margin: 15mm; }
body { font-family: 'YuMincho', 'YuGothic', 'Meiryo', 'msgothic', 'Noto Sans JP', sans-serif; font-size: 10.5pt; line-height: 1.6; color: #111827; }
.tabular-nums { font-family: 'YuGothic', 'Meiryo', 'msgothic', sans-serif; }
.two-col { overflow: hidden; }
.col-left { float: left; width: 47%; }
.col-right { float: right; width: 47%; }
.clear { clear: both; }
.card { border: 1px solid #e5e7eb; padding: 4mm; background: #fafafa; }
.section-title { font-weight: 600; color: #1e3a8a; border-bottom: 2px solid #1e3a8a; padding-bottom: 1mm; margin-bottom: 3mm; }
.amount-box { background: #059669; color: white; padding: 6mm; text-align: center; }
.note-box { background: #fafafa; border-left: 4px solid #1e3a8a; padding: 4mm; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 4mm; border: 1px solid #e5e7eb; vertical-align: middle; }
th { background: #3b82f6; color: white; font-weight: 600; }
tr:nth-child(even) td { background: #fafafa; }
tr.total-row td { background: #3b82f6; color: white; font-weight: 700; }
tr.total-tax-row td { background: #059669; color: white; font-weight: 700; }
CSS;
    }
}