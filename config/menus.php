<?php

return [
    'main' => [
        '1' => [
            'text' => 'Register',
            'function' => 'handleRegistration',
            'state' => 'REGISTRATION_INIT'
        ],
        '2' => [
            'text' => 'Money Transfer',
            'function' => 'handleTransfer',
            'state' => 'TRANSFER_INIT'
        ],
        '3' => [
            'text' => 'Bill Payments',
            'function' => 'handleBillPayment',
            'state' => 'BILL_PAYMENT_INIT'
        ],
        '4' => [
            'text' => 'Account Services',
            'function' => 'handleAccountServices',
            'state' => 'SERVICES_INIT'
        ]
    ],

    'registration' => [
        '1' => [
            'text' => 'Account Registration',
            'function' => 'handleAccountRegistration',
            'state' => 'ACCOUNT_REGISTRATION'
        ]
    ],

    'transfer' => [
        '1' => [
            'text' => 'Internal Transfer',
            'function' => 'handleInternalTransfer',
            'state' => 'INTERNAL_TRANSFER'
        ],
        '2' => [
            'text' => 'Bank Transfer',
            'function' => 'handleBankTransfer',
            'state' => 'BANK_TRANSFER'
        ],
        '3' => [
            'text' => 'Mobile Money',
            'function' => 'handleMobileMoneyTransfer',
            'state' => 'MOBILE_MONEY_TRANSFER'
        ]
    ],

    'account_services' => [
        '1' => [
            'text' => 'Balance Inquiry',
            'function' => 'handleBalanceInquiry',
            'state' => 'BALANCE_INQUIRY'
        ],
        '2' => [
            'text' => 'Mini Statement',
            'function' => 'handleMiniStatement',
            'state' => 'MINI_STATEMENT'
        ],
        '3' => [
            'text' => 'Full Statement',
            'function' => 'handleFullStatement',
            'state' => 'FULL_STATEMENT'
        ],
        '4' => [
            'text' => 'PIN Management',
            'function' => 'handlePINManagement',
            'state' => 'PIN_MANAGEMENT'
        ]
    ]
];
