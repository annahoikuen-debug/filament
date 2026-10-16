<?php

namespace App\Services\Pdf;

use App\Models\MonthlyInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class OverdueNoticePdfService
{
    /**
     * 滞納督促状 PDF を生成
     */
    public function generateNoticePdf(MonthlyInvoice $invoice, string $stage = 'reminder'): string
    {
        $data = [
            'invoice' => $invoice,
            'resident' => $invoice->resident,
            'facility' => $invoice->facility,
            'stage' => $stage,
            'issueDate' => now()->format('Y年m月d日'),
            'totalAmount' => number_format((int) $invoice->total_with_tax),
            'yearMonth' => substr($invoice->billing_year_month, 0, 4) . '年' . (int) substr($invoice->billing_year_month, 5, 2) . '月',
        ];

        $html = view('pdf.overdue_notice', $data)->render();
        $pdf = Pdf::loadHTML($html);

        $dir = storage_path('app/overdue_notices');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $filePath = "{$dir}/notice_invoice_{$invoice->id}.pdf";
        $pdf->save($filePath);

        return $filePath;
    }
}
