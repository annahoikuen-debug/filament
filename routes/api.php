<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TrialController;
use App\Http\Controllers\TrialConversionController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\QuoteController;

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

Route::post('/trials', [TrialController::class, 'store']);
Route::post('/trials/{trial}/convert', [TrialConversionController::class, 'convert']);
Route::get('/trials/{trial}/quote', [QuoteController::class, 'show']);
Route::post('/trials/{trial}/quote/send', [QuoteController::class, 'send']);

Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/bookings', [BookingController::class, 'index']);