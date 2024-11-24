<?php

namespace App\Providers;

use App\Interfaces\ChannelInterface;
use App\Interfaces\SessionManagerInterface;
use App\Interfaces\TransactionInterface;
use App\Services\AccountService;
use App\Services\AuthenticationService;
use App\Services\BillPaymentService;
use App\Services\SessionManager;
use App\Services\TransactionService;
use App\Services\TransferService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
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

        // Register singleton instances
        $this->app->singleton(SessionManager::class);
        $this->app->singleton(TransactionService::class);
        $this->app->singleton(AuthenticationService::class);
        $this->app->singleton(TransferService::class);
        $this->app->singleton(BillPaymentService::class);
        $this->app->singleton(AccountService::class);

        // Register service aliases
        $this->app->alias(AuthenticationService::class, 'auth.service');
        $this->app->alias(TransferService::class, 'transfer.service');
        $this->app->alias(BillPaymentService::class, 'bill.service');
        $this->app->alias(AccountService::class, 'account.service');
        $this->app->alias(TransactionService::class, 'transaction.service');
        $this->app->alias(SessionManager::class, 'session.service');
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

        // Register any custom validation rules
        $this->registerValidationRules();
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

    /**
     * Register custom validation rules
     */
    protected function registerValidationRules(): void
    {
        // Phone number validation
        Validator::extend('phone_number', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^26[0-9]{10}$/', $value);
        }, 'The :attribute must be a valid Zambian phone number.');

        // Account number validation
        Validator::extend('account_number', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[0-9]{10,}$/', $value);
        }, 'The :attribute must be a valid account number.');

        // PIN validation
        Validator::extend('pin', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[0-9]{4}$/', $value);
        }, 'The :attribute must be a 4-digit number.');

        // Card number validation
        Validator::extend('card_number', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[0-9]{16}$/', $value);
        }, 'The :attribute must be a valid 16-digit card number.');

        // CVV validation
        Validator::extend('cvv', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[0-9]{3}$/', $value);
        }, 'The :attribute must be a valid 3-digit CVV number.');

        // Card expiry validation
        Validator::extend('card_expiry', function ($attribute, $value, $parameters, $validator) {
            if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $value, $matches)) {
                return false;
            }

            $month = $matches[1];
            $year = '20' . $matches[2];
            $expiry = Carbon::createFromDate($year, $month)->endOfMonth();

            return $expiry->isFuture();
        }, 'The :attribute must be a valid future date in MM/YY format.');

        // Amount validation
        Validator::extend('valid_amount', function ($attribute, $value, $parameters, $validator) {
            $minAmount = config('social-banking.transactions.min_amount', 10);
            $maxAmount = config('social-banking.transactions.max_amount', 150000);

            return is_numeric($value) && $value >= $minAmount && $value <= $maxAmount;
        }, 'The :attribute must be between :min and :max.');
    }
}
