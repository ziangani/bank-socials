<?php

use App\Http\Controllers\USSDController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// WhatsApp endpoints
Route::prefix('whatsapp')->group(function () {
    Route::post('webhook', [WhatsAppController::class, 'handleWebhook']);
    Route::get('verify', [WhatsAppController::class, 'verifyWebhook']);
});

// USSD endpoints
Route::prefix('ussd')->group(function () {
    Route::post('handle', [USSDController::class, 'handle']);
    Route::post('simulate', [USSDController::class, 'simulate']);
    Route::post('end-session', [USSDController::class, 'endSession']);
    Route::get('session-status', [USSDController::class, 'sessionStatus']);
});

// API Documentation
Route::get('/docs', function () {
    return view('api-docs');
});

// Health Check
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String()
    ]);
});
