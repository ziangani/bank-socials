<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        // Social Banking Events
        'App\Events\TransactionCreated' => [
            'App\Listeners\SendTransactionNotification',
            'App\Listeners\LogTransaction',
        ],
        'App\Events\TransactionCompleted' => [
            'App\Listeners\SendTransactionConfirmation',
            'App\Listeners\UpdateTransactionLogs',
        ],
        'App\Events\TransactionFailed' => [
            'App\Listeners\NotifyTransactionFailure',
            'App\Listeners\LogFailedTransaction',
        ],
        'App\Events\SessionStarted' => [
            'App\Listeners\InitializeSessionData',
            'App\Listeners\LogSessionStart',
        ],
        'App\Events\SessionEnded' => [
            'App\Listeners\CleanupSessionData',
            'App\Listeners\LogSessionEnd',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Register model observers
        \App\Models\Transaction::observe(\App\Observers\TransactionObserver::class);
        \App\Models\TransactionLog::observe(\App\Observers\TransactionLogObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
