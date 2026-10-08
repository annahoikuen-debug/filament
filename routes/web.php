<?php

use App\Http\Controllers\InvoiceZipDownloadController;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// 請求書PDFダウンロード / ストリームプレビュー用ルート（認証必須）
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/invoices/{invoice}/pdf', [InvoicePdfService::class, 'downloadPdf'])
        ->name('invoices.pdf.download');

    Route::get('/invoices/{invoice}/stream', [InvoicePdfService::class, 'streamPdf'])
        ->name('invoices.pdf.stream');

    // 非同期ZIP生成の進捗確認・ダウンロード用
    Route::get('/invoices/zip-progress/{jobId}', [InvoiceZipDownloadController::class, '__invoke'])
        ->name('invoices.zip-progress');
});

// HTMLプレビュー用ルート（Webアプリ表示用・認証不要）
Route::middleware(['web'])->group(function () {
    Route::get('/invoices/{invoice}/preview/{type}', [InvoicePdfService::class, 'previewHtml'])
        ->name('invoices.preview')
        ->where('type', 'invoice|receipt')
        ->withoutMiddleware([\Illuminate\Auth\Middleware\Authenticate::class]);
});
