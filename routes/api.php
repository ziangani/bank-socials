<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Transaction routes
Route::middleware(['auth:sanctum', 'throttle:transactions'])->group(function () {
    Route::prefix('transactions')->group(function () {
        Route::get('/', 'TransactionController@index');
        Route::post('/', 'TransactionController@store');
        Route::get('/{reference}', 'TransactionController@show');
        Route::get('/{reference}/status', 'TransactionController@status');
        Route::post('/{reference}/reverse', 'TransactionController@reverse');
    });
});

// USSD API routes
Route::middleware('throttle:ussd')->prefix('ussd')->group(function () {
    Route::post('/session', 'USSDController@handle');
    Route::post('/session/end', 'USSDController@endSession');
    Route::get('/session/{sessionId}', 'USSDController@sessionStatus');
});

// WhatsApp API routes
Route::middleware('throttle:whatsapp')->prefix('whatsapp')->group(function () {
    Route::post('/webhook', 'WhatsAppController@handleWebhook');
    Route::get('/webhook', 'WhatsAppController@verifyWebhook');
});

// System health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'version' => config('app.version', '1.0.0')
    ]);
});

// Documentation
Route::get('/docs', function () {
    return response()->json([
        'message' => 'API documentation available at /docs/index.html',
        'version' => '1.0.0'
    ]);
});
