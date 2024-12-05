<?php

use App\Http\Controllers\Chat\ChatController;
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

Route::prefix('whatsapp')->group(function () {
    Route::post('webhook', [ChatController::class, 'processMessage'])->name('whatsapp.webhook');
    Route::get('webhook', function (Request $request) {
        $challenge = $request->query('hub_challenge');
        return response($challenge, 200);
    })->name('whatsapp.verify');
});

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
