<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Register policies
        $this->registerPolicies();

        // Define gates
        Gate::define('manage-transactions', function ($user) {
            return $user->hasRole('admin');
        });

        Gate::define('view-transactions', function ($user) {
            return $user->hasRole(['admin', 'support']);
        });
    }
}
