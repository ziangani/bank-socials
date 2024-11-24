<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Social Banking Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration settings for the social banking system.
    |
    */

    'channels' => [
        'whatsapp' => [
            'enabled' => env('WHATSAPP_ENABLED', true),
            'timeout' => env('WHATSAPP_TIMEOUT', 300), // seconds
            'retry_attempts' => env('WHATSAPP_RETRY_ATTEMPTS', 3),
            'webhook_url' => env('WHATSAPP_WEBHOOK_URL'),
            'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        ],

        'ussd' => [
            'enabled' => env('USSD_ENABLED', true),
            'timeout' => env('USSD_TIMEOUT', 120), // seconds
            'service_code' => env('USSD_SERVICE_CODE', '*123#'),
            'provider' => env('USSD_PROVIDER', 'default'),
        ],
    ],

    'transactions' => [
        'default_currency' => env('DEFAULT_CURRENCY', 'KES'),
        'min_amount' => env('MIN_TRANSACTION_AMOUNT', 10),
        'max_amount' => env('MAX_TRANSACTION_AMOUNT', 150000),
        
        'limits' => [
            'standard' => [
                'daily' => env('STANDARD_DAILY_LIMIT', 300000),
                'single' => env('STANDARD_SINGLE_LIMIT', 100000),
                'monthly' => env('STANDARD_MONTHLY_LIMIT', 1000000),
            ],
            'premium' => [
                'daily' => env('PREMIUM_DAILY_LIMIT', 1000000),
                'single' => env('PREMIUM_SINGLE_LIMIT', 500000),
                'monthly' => env('PREMIUM_MONTHLY_LIMIT', 5000000),
            ],
            'business' => [
                'daily' => env('BUSINESS_DAILY_LIMIT', 5000000),
                'single' => env('BUSINESS_SINGLE_LIMIT', 1000000),
                'monthly' => env('BUSINESS_MONTHLY_LIMIT', 20000000),
            ],
        ],

        'fees' => [
            'internal' => [
                'fixed' => env('INTERNAL_FIXED_FEE', 0),
                'percentage' => env('INTERNAL_PERCENTAGE_FEE', 0),
            ],
            'external' => [
                'fixed' => env('EXTERNAL_FIXED_FEE', 50),
                'percentage' => env('EXTERNAL_PERCENTAGE_FEE', 1),
            ],
            'bill_payment' => [
                'fixed' => env('BILL_FIXED_FEE', 30),
                'percentage' => env('BILL_PERCENTAGE_FEE', 0),
            ],
        ],
    ],

    'security' => [
        'pin_attempts' => env('MAX_PIN_ATTEMPTS', 3),
        'pin_timeout' => env('PIN_TIMEOUT', 30), // minutes
        'session_timeout' => env('SESSION_TIMEOUT', 30), // minutes
        'otp_expiry' => env('OTP_EXPIRY', 10), // minutes
        'otp_length' => env('OTP_LENGTH', 6),
    ],

    'notifications' => [
        'sms' => [
            'enabled' => env('SMS_NOTIFICATIONS_ENABLED', true),
            'provider' => env('SMS_PROVIDER', 'default'),
        ],
        'email' => [
            'enabled' => env('EMAIL_NOTIFICATIONS_ENABLED', true),
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
        ],
    ],

    'integrations' => [
        'core_banking' => [
            'url' => env('CORE_BANKING_URL'),
            'timeout' => env('CORE_BANKING_TIMEOUT', 30),
            'retry_attempts' => env('CORE_BANKING_RETRY_ATTEMPTS', 3),
        ],
        'mobile_money' => [
            'enabled' => env('MOBILE_MONEY_ENABLED', true),
            'provider' => env('MOBILE_MONEY_PROVIDER', 'default'),
            'url' => env('MOBILE_MONEY_URL'),
        ],
        'bill_providers' => [
            'enabled' => env('BILL_PAYMENTS_ENABLED', true),
            'providers' => [
                'electricity' => [
                    'enabled' => true,
                    'code' => 'ELEC',
                    'name' => 'Electricity Provider',
                ],
                'water' => [
                    'enabled' => true,
                    'code' => 'WATER',
                    'name' => 'Water Provider',
                ],
                'tv' => [
                    'enabled' => true,
                    'code' => 'TV',
                    'name' => 'TV Provider',
                ],
            ],
        ],
    ],

    'logging' => [
        'enabled' => env('TRANSACTION_LOGGING_ENABLED', true),
        'channel' => env('TRANSACTION_LOG_CHANNEL', 'daily'),
        'retention' => env('LOG_RETENTION_DAYS', 90),
    ],

    'simulator' => [
        'enabled' => env('SIMULATOR_ENABLED', true),
        'delay' => env('SIMULATOR_DELAY', 2), // seconds
        'error_rate' => env('SIMULATOR_ERROR_RATE', 0.1), // 10% error rate
    ],
];
