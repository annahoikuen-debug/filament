<?php

use App\Http\Controllers\InvoiceZipDownloadController;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// 請求書PDFダウンロード / ストリームプレビュー用ルート（サービス経由に統合）
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/invoices/{invoice}/pdf', [InvoicePdfService::class, 'downloadPdf'])
        ->name('invoices.pdf.download');

    Route::get('/invoices/{invoice}/stream', [InvoicePdfService::class, 'streamPdf'])
        ->name('invoices.pdf.stream');

    // 非同期ZIP生成の進捗確認・ダウンロード用
    Route::get('/invoices/zip-progress/{jobId}', [InvoiceZipDownloadController::class, '__invoke'])
        ->name('invoices.zip-progress');
});
