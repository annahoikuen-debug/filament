<?php

use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\Auth\TwoFactorVerifyController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\FormDownloadController;
use App\Http\Controllers\InvoiceZipDownloadController;
use App\Http\Controllers\PublicChatbotController;
use App\Services\InvoicePdfService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return redirect('/admin');
});

// 請求書PDFダウンロード / ストリームプレビュー用ルート（認証必須）
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/invoices/{invoice}/pdf', [InvoicePdfService::class, 'downloadPdf'])
        ->name('invoices.pdf.download')
        ->middleware('throttle:downloads');

    Route::get('/invoices/{invoice}/stream', [InvoicePdfService::class, 'streamPdf'])
        ->name('invoices.pdf.stream')
        ->middleware('throttle:downloads');

    // 非同期ZIP生成の進捗確認・ダウンロード用
    Route::get('/invoices/zip-progress/{jobId}', [InvoiceZipDownloadController::class, '__invoke'])
        ->name('invoices.zip-progress')
        ->middleware('throttle:downloads');
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
        ->withoutMiddleware([Authenticate::class]);
});

// チャットボットAPI（職員向け・認証必須）
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/api/chatbot/message', [ChatbotController::class, 'message'])
        ->middleware('throttle:30,1')
        ->name('chatbot.message');

    Route::post('/api/chatbot/feedback', [ChatbotController::class, 'feedback'])
        ->middleware('throttle:30,1')
        ->name('chatbot.feedback');
});

// 公開チャットボットAPI（認証不要・throttle + ハニーポット + 最短時間チェック）
Route::post('/api/public/chatbot/message', [PublicChatbotController::class, 'message'])
    ->name('public.chatbot.message')
    ->middleware('throttle:'.config('chatbot.public.rate_limit', '20,1'));

// 公開チャットボットウィジェットJS配信（静的サイトから1行scriptで埋め込み可能）
Route::get('/chatbot/widget.js', function () {
    $path = public_path('vendor/chatbot/widget.js');

    if (! file_exists($path)) {
        abort(404);
    }

    return response(file_get_contents($path), 200, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->name('public.chatbot.widget');

// フォーム確認メール内の署名付き資料ダウンロードルート（認証不要・署名必須）
Route::get('/forms/{submission}/download/{document}', [FormDownloadController::class, 'show'])
    ->name('forms.download')
    ->middleware('signed')
    ->where('document', 'catalog|diagnosis');

// 二要素認証 (2FA) ルート（認証必須）
Route::middleware(['web', 'auth'])->prefix('2fa')->name('2fa.')->group(function () {
    Route::get('/setup', [TwoFactorSetupController::class, 'show'])->name('setup');
    Route::post('/setup', [TwoFactorSetupController::class, 'confirm'])->name('setup.submit');
    Route::get('/verify', [TwoFactorVerifyController::class, 'show'])->name('verify');
    Route::post('/verify', [TwoFactorVerifyController::class, 'verify'])->name('verify.submit');
    Route::post('/disable', [TwoFactorVerifyController::class, 'disable'])->name('disable');
});

// 電子帳簿保存法：帳票ファイルダウンロード（認証必須・施設スコープ適用）
Route::middleware(['web', 'auth'])->get('/invoice-documents/{invoiceDocument}/download', function (\App\Models\InvoiceDocument $invoiceDocument) {
    $filePath = storage_path('app/'.$invoiceDocument->file_path);

    if (! file_exists($filePath)) {
        abort(404, '帳票ファイルが見つかりません。');
    }

    // 施設スコープチェック
    $user = auth()->user();
    if ($user && $user->requiresFacilityScope()) {
        $facilityId = $invoiceDocument->monthlyInvoice->resident->facility_id;
        if ((int) $user->facility_id !== (int) $facilityId) {
            abort(403, '他の施設の帳票にはアクセスできません。');
        }
    }

    return response()->download($filePath, basename($invoiceDocument->file_path), [
        'Content-Type' => 'application/pdf',
    ]);
})->name('invoice-documents.download');

// 運用ヘルスチェック（Railway / Caddy 用）
// DB 接続・ストレージ書き込み・schedule:work 生存(h heartbeat)を検証し、
// 異常時は 503 を返す。認証不要。
Route::get('/health', function () {
    $checks = [];

    // DB 接続確認
    try {
        DB::select('SELECT 1');
        $checks['database'] = 'ok';
    } catch (\Throwable $e) {
        $checks['database'] = 'error: '.$e->getMessage();
    }

    // ストレージ書き込み確認
    try {
        $testFile = storage_path('framework/health_check_'.uniqid());
        file_put_contents($testFile, 'ok');
        @unlink($testFile);
        $checks['storage'] = 'ok';
    } catch (\Throwable $e) {
        $checks['storage'] = 'error: '.$e->getMessage();
    }

    // スケジューラ生存確認（heartbeat キャッシュが 90秒以内に更新されているか）
    $heartbeat = Cache::get('ops:heartbeat:schedule_work');
    $checks['schedule'] = $heartbeat && $heartbeat->diffInSeconds(now()) <= 120 ? 'ok' : 'stale_or_missing';

    $healthy = ! in_array('error', $checks, true) && $checks['schedule'] === 'ok';

    return response()->json([
        'status' => $healthy ? 'ok' : 'error',
        'checks' => $checks,
    ], $healthy ? 200 : 503);
})->name('health');
