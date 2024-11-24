<?php

namespace App\Providers;

use App\Adapters\USSDMessageAdapter;
use App\Adapters\WhatsAppMessageAdapter;
use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;
use Illuminate\Support\ServiceProvider;

class MessageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind MessageAdapterInterface based on request path
        $this->app->bind(MessageAdapterInterface::class, function ($app) {
            $path = request()->path();
            
            // Determine channel from request path
            if (str_starts_with($path, 'whatsapp/')) {
                return $app->make(WhatsAppMessageAdapter::class);
            }
            
            if (str_starts_with($path, 'ussd/')) {
                return $app->make(USSDMessageAdapter::class);
            }

            // For chat endpoints, determine from request payload
            if (str_starts_with($path, 'chat/')) {
                $channel = request()->input('channel');
                return match($channel) {
                    'whatsapp' => $app->make(WhatsAppMessageAdapter::class),
                    'ussd' => $app->make(USSDMessageAdapter::class),
                    default => $app->make(WhatsAppMessageAdapter::class)
                };
            }

            // Default to WhatsApp
            return $app->make(WhatsAppMessageAdapter::class);
        });

        // Register singleton instances
        $this->app->singleton(SessionManager::class);

        // Bind adapters as singletons
        $this->app->singleton(WhatsAppMessageAdapter::class);
        $this->app->singleton(USSDMessageAdapter::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register any observers or event listeners
        $this->registerEventListeners();
    }

    /**
     * Register event listeners
     */
    protected function registerEventListeners(): void
    {
        // Register message processing events
        // Example: Event::listen(MessageReceived::class, ProcessMessage::class);
    }
}
