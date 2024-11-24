<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\USSDController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
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
    
    // USSD Simulator Interface
    Route::get('simulator', function () {
        return view('ussd.simulator');
    });
});

// Chat processing endpoints
Route::prefix('chat')->group(function () {
    Route::post('process', [ChatController::class, 'processMessage']);
    Route::post('welcome', [WelcomeController::class, 'welcome']);
});

// Health Check
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String()
    ]);
});
