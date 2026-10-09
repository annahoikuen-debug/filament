<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\FormDownloadController;
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

// デモ面談予約一覧（営業担当用・管理者のみ。個人情報保護のため認証必須）
// JSON 応答のため 'auth' リダイレクトではなくコントローラー側で認可判定
Route::middleware(['web'])->get('/bookings', [BookingController::class, 'index'])
    ->name('bookings.index');

// HTMLプレビュー用ルート（メール送信用・署名付きURLのみ許可）
// 個人情報（請求書データ）が含まれるため、ID推測による不正アクセスを防止するため署名検証を必須化
Route::middleware(['web', 'signed'])->group(function () {
    Route::get('/invoices/{invoice}/preview/{type}', [InvoicePdfService::class, 'previewHtml'])
        ->name('invoices.preview')
        ->where('type', 'invoice|receipt')
        ->withoutMiddleware([\Illuminate\Auth\Middleware\Authenticate::class]);
});

// チャットボットAPI（職員向け・認証必須）
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/api/chatbot/message', [ChatbotController::class, 'message'])
        ->middleware('throttle:30,1')
        ->name('chatbot.message');
});

// フォーム確認メール内の署名付き資料ダウンロードルート（認証不要・署名必須）
Route::get('/forms/{submission}/download/{document}', [FormDownloadController::class, 'show'])
    ->name('forms.download')
    ->middleware('signed')
    ->where('document', 'catalog|diagnosis');
