<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TrialController;
use App\Http\Controllers\TrialConversionController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\Api\ExternalInvoiceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are stateless, which means they are not bound by session state
| and do not require authentication for basic endpoints.
|
*/

Route::post('/trials', [TrialController::class, 'store'])
    ->middleware('throttle:trial-create');

Route::post('/trials/{trial}/convert', [TrialConversionController::class, 'convert'])
    ->middleware('throttle:trial-convert');

Route::get('/trials/{trial}/quote', [QuoteController::class, 'show']);

Route::post('/trials/{trial}/quote/send', [QuoteController::class, 'send'])
    ->middleware('throttle:quote-send');

Route::post('/bookings', [BookingController::class, 'store'])
    ->middleware('throttle:booking-create');

Route::post('/site-forms/{type}', [FormController::class, 'store'])
    ->where('type', 'catalog|demo|diagnosis|prospect|inquiry')
    ->middleware('throttle:10,1');

// 外部介護サービス請求データ受信
Route::prefix('external-invoices')->group(function () {
    Route::post('/', [ExternalInvoiceController::class, 'store']);
    Route::post('/single', [ExternalInvoiceController::class, 'storeSingle']);
    Route::get('/', [ExternalInvoiceController::class, 'index']);
});