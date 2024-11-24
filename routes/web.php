<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\USSDController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Http\Request;
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
    Route::post('webhook', [ChatController::class, 'processMessage'])->name('whatsapp.webhook');
    Route::get('verify', function (Request $request) {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('whatsapp.verify_token')) {
            return response($challenge, 200);
        }

        return response()->json(['error' => 'Invalid verify token'], 403);
    })->name('whatsapp.verify');
});

// USSD endpoints
Route::prefix('ussd')->group(function () {
    Route::post('handle', [ChatController::class, 'processMessage'])->name('ussd.handle');
    Route::post('simulate', [USSDController::class, 'simulate'])->name('ussd.simulate');
    Route::post('end-session', [USSDController::class, 'endSession'])->name('ussd.end');
    Route::get('session-status', [USSDController::class, 'sessionStatus'])->name('ussd.status');
    
    // USSD Simulator Interface
    Route::get('simulator', function () {
        return view('ussd.simulator');
    })->name('ussd.simulator');
});

// Welcome/Registration endpoints
Route::prefix('welcome')->group(function () {
    Route::post('register/card', [WelcomeController::class, 'registerWithCard'])->name('welcome.register.card');
    Route::post('register/account', [WelcomeController::class, 'registerWithAccount'])->name('welcome.register.account');
    Route::post('verify-otp', [WelcomeController::class, 'verifyRegistrationOTP'])->name('welcome.verify-otp');
});

// API Documentation
Route::get('/docs', function () {
    return view('api-docs');
})->name('api.docs');

// Health Check
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String()
    ]);
})->name('health');
