<?php

namespace App\Http\Controllers;

use App\Services\InvoicePdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class InvoiceZipDownloadController extends Controller
{
    public function __invoke(Request $request, string $jobId)
    {
        $pdfService = app(InvoicePdfService::class);
        $zipPath = $pdfService->getZipResult($jobId);

        if (! $zipPath || ! File::exists($zipPath)) {
            return redirect()
                ->route('filament.admin.resources.monthly-invoices.zip-progress', ['jobId' => $jobId])
                ->with('error', 'ZIPファイルが見つかりません。再度生成してください。');
        }

        $yearMonth = str_replace(['zip_', '_all_', '_'], '', $jobId);
        $yearMonth = substr($yearMonth, 0, 7);

        return response()->download($zipPath, "請求書一括_{$yearMonth}.zip")->deleteFileAfterSend();
    }
}