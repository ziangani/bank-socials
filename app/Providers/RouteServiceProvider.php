<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Configure rate limiting
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });

        // Custom route patterns
        Route::pattern('id', '[0-9]+');
        Route::pattern('reference', '[A-Za-z0-9]+');
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Rate limit for API endpoints
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limit for USSD endpoints
        RateLimiter::for('ussd', function (Request $request) {
            return Limit::perMinute(30)->by($request->input('phoneNumber'));
        });

        // Rate limit for WhatsApp endpoints
        RateLimiter::for('whatsapp', function (Request $request) {
            return Limit::perMinute(20)->by($request->input('from'));
        });

        // Rate limit for transaction endpoints
        RateLimiter::for('transactions', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
