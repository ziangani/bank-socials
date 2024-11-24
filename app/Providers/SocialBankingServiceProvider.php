<?php

namespace App\Providers;

use App\Interfaces\ChannelInterface;
use App\Interfaces\SessionManagerInterface;
use App\Interfaces\TransactionInterface;
use App\Channels\WhatsAppChannel;
use App\Channels\USSDChannel;
use App\Services\SessionManager;
use App\Services\TransactionService;
use Illuminate\Support\ServiceProvider;

class SocialBankingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind SessionManager
        $this->app->bind(SessionManagerInterface::class, SessionManager::class);

        // Bind TransactionService
        $this->app->bind(TransactionInterface::class, TransactionService::class);

        // Bind Channel implementations based on request context
        $this->app->bind(ChannelInterface::class, function ($app) {
            $channel = request()->header('X-Channel');
            
            return match($channel) {
                'whatsapp' => $app->make(WhatsAppChannel::class),
                'ussd' => $app->make(USSDChannel::class),
                default => $app->make(WhatsAppChannel::class) // Default to WhatsApp
            };
        });

        // Register singleton instances where needed
        $this->app->singleton(SessionManager::class);
        $this->app->singleton(TransactionService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register any observers
        $this->registerObservers();

        // Register any event listeners
        $this->registerEventListeners();
    }

    /**
     * Register model observers
     */
    protected function registerObservers(): void
    {
        // Example: Transaction::observe(TransactionObserver::class);
    }

    /**
     * Register event listeners
     */
    protected function registerEventListeners(): void
    {
        // Example: Event::listen(TransactionCreated::class, SendTransactionNotification::class);
    }
}
