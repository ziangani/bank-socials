<?php

namespace App\Http\Controllers\Chat;

use Illuminate\Support\Facades\Log;

class BillPaymentController extends BaseMessageController
{
    // Bill payment flow states
    const STATES = [
        'BILL_TYPE_SELECTION' => 'BILL_TYPE_SELECTION',
        'ACCOUNT_INPUT' => 'ACCOUNT_INPUT',
        'AMOUNT_INPUT' => 'AMOUNT_INPUT',
        'CONFIRM_PAYMENT' => 'CONFIRM_PAYMENT',
        'PIN_VERIFICATION' => 'PIN_VERIFICATION',
    ];

    // Bill types and their validation patterns
    const BILL_TYPES = [
        '1' => [
            'name' => 'Electricity',
            'code' => 'KPLC',
            'pattern' => '/^\d{6}$/',
            'length' => '6',
            'fixed_amount' => false
        ],
        '2' => [
            'name' => 'Water',
            'code' => 'WATER',
            'pattern' => '/^\d{8}$/',
            'length' => '8',
            'fixed_amount' => false
        ],
        '3' => [
            'name' => 'TV Subscription',
            'code' => 'TV',
            'pattern' => '/^\d{10}$/',
            'length' => '10',
            'fixed_amount' => true
        ],
        '4' => [
            'name' => 'Internet',
            'code' => 'NET',
            'pattern' => '/^\d{8}$/',
            'length' => '8',
            'fixed_amount' => true
        ]
    ];

    public function handleBillPayment(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Initializing bill payment:', [
                'message' => $message,
                'session' => $sessionData
            ]);
        }

        // Initialize bill payment flow with bill type selection while preserving session data
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'BILL_PAYMENT_INIT', // Ensure state is set
            'data' => [
                ...$sessionData['data'] ?? [], // Preserve existing session data
                'step' => self::STATES['BILL_TYPE_SELECTION']
            ]
        ]);

        if (config('app.debug')) {
            Log::info('Updated session for bill type selection');
        }

        return $this->formatMenuResponse(
            "Please select the type of bill to pay:\n\n",
            [
                '1' => ['text' => 'Electricity'],
                '2' => ['text' => 'Water'],
                '3' => ['text' => 'TV Subscription'],
                '4' => ['text' => 'Internet']
            ]
        );
    }

    public function processBillPayment(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing bill payment:', [
                'message' => $message,
                'session' => $sessionData,
                'current_step' => $sessionData['data']['step'] ?? null
            ]);
        }

        $currentStep = $sessionData['data']['step'] ?? null;
        
        return match($currentStep) {
            self::STATES['BILL_TYPE_SELECTION'] => $this->processBillTypeSelection($message, $sessionData),
            self::STATES['ACCOUNT_INPUT'] => $this->processAccountInput($message, $sessionData),
            self::STATES['AMOUNT_INPUT'] => $this->processAmountInput($message, $sessionData),
            self::STATES['CONFIRM_PAYMENT'] => $this->processPaymentConfirmation($message, $sessionData),
            self::STATES['PIN_VERIFICATION'] => $this->processPinVerification($message, $sessionData),
            default => $this->handleBillPayment($message, $sessionData)
        };
    }

    protected function processBillTypeSelection(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing bill type selection:', [
                'selection' => $message['content'],
                'session' => $sessionData
            ]);
        }

        $selection = $message['content'];
        
        if (!isset(self::BILL_TYPES[$selection])) {
            if (config('app.debug')) {
                Log::warning('Invalid bill type selection:', ['selection' => $selection]);
            }

            return $this->formatMenuResponse(
                "Invalid selection. Please select a valid bill type:\n\n",
                [
                    '1' => ['text' => 'Electricity'],
                    '2' => ['text' => 'Water'],
                    '3' => ['text' => 'TV Subscription'],
                    '4' => ['text' => 'Internet']
                ]
            );
        }

        $billType = self::BILL_TYPES[$selection];
        
        // Update session with bill type while preserving session data
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'BILL_PAYMENT_INIT', // Keep the state consistent
            'data' => [
                ...$sessionData['data'],
                'bill_type' => $billType,
                'step' => self::STATES['ACCOUNT_INPUT']
            ]
        ]);

        if (config('app.debug')) {
            Log::info('Updated session after bill type selection:', [
                'bill_type' => $billType,
                'new_step' => self::STATES['ACCOUNT_INPUT']
            ]);
        }

        return $this->formatTextResponse("Please enter your {$billType['name']} account number ({$billType['length']} digits):");
    }

    protected function processAccountInput(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing account input:', [
                'account_number' => $message['content'],
                'session' => $sessionData
            ]);
        }

        $accountNumber = $message['content'];
        $billType = $sessionData['data']['bill_type'];
        
        // Validate account number format
        if (!preg_match($billType['pattern'], $accountNumber)) {
            if (config('app.debug')) {
                Log::warning('Invalid account number format:', [
                    'account_number' => $accountNumber,
                    'expected_pattern' => $billType['pattern']
                ]);
            }

            return $this->formatTextResponse(
                "Invalid account number format. Please enter a {$billType['length']}-digit account number for {$billType['name']}:"
            );
        }

        // Update session with account number
        $sessionData['data']['account_number'] = $accountNumber;
        
        if ($billType['fixed_amount']) {
            // Simulate fetching fixed amount (replace with actual API call)
            $amount = $this->getFixedAmount($billType['code']);
            return $this->prepareConfirmation($message, $sessionData, $amount);
        }

        // For variable amount bills, proceed to amount input
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'BILL_PAYMENT_INIT', // Keep the state consistent
            'data' => [
                ...$sessionData['data'],
                'account_number' => $accountNumber,
                'step' => self::STATES['AMOUNT_INPUT']
            ]
        ]);

        if (config('app.debug')) {
            Log::info('Updated session after account input:', [
                'account_number' => $accountNumber,
                'new_step' => self::STATES['AMOUNT_INPUT']
            ]);
        }

        return $this->formatTextResponse("Please enter the amount to pay:");
    }

    protected function processAmountInput(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing amount input:', [
                'amount' => $message['content'],
                'session' => $sessionData
            ]);
        }

        $amount = $message['content'];
        
        // Validate amount
        if (!is_numeric($amount) || $amount <= 0) {
            if (config('app.debug')) {
                Log::warning('Invalid amount:', ['amount' => $amount]);
            }

            return $this->formatTextResponse("Invalid amount. Please enter a valid number:");
        }

        return $this->prepareConfirmation($message, $sessionData, $amount);
    }

    protected function prepareConfirmation(array $message, array $sessionData, string $amount): array
    {
        if (config('app.debug')) {
            Log::info('Preparing payment confirmation:', [
                'bill_type' => $sessionData['data']['bill_type'],
                'account_number' => $sessionData['data']['account_number'],
                'amount' => $amount
            ]);
        }

        $billType = $sessionData['data']['bill_type'];
        $accountNumber = $sessionData['data']['account_number'];
        $currency = config('social-banking.currency', 'KES');

        // Update session with amount and move to confirmation
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'BILL_PAYMENT_INIT', // Keep the state consistent
            'data' => [
                ...$sessionData['data'],
                'amount' => $amount,
                'step' => self::STATES['CONFIRM_PAYMENT']
            ]
        ]);

        if (config('app.debug')) {
            Log::info('Updated session for payment confirmation');
        }

        $confirmationMsg = "Please confirm bill payment:\n\n" .
                          "Type: {$billType['name']}\n" .
                          "Account: {$accountNumber}\n" .
                          "Amount: {$currency} {$amount}\n\n" .
                          "Select an option:";

        return $this->formatMenuResponse(
            $confirmationMsg,
            [
                '1' => ['text' => 'Confirm'],
                '2' => ['text' => 'Cancel']
            ]
        );
    }

    protected function processPaymentConfirmation(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing payment confirmation:', [
                'response' => $message['content'],
                'session' => $sessionData
            ]);
        }

        $response = $message['content'];

        if ($response === '2' || strtolower($response) === 'cancel') {
            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'WELCOME'
            ]);

            if (config('app.debug')) {
                Log::info('Payment cancelled, returning to welcome state');
            }

            return $this->formatTextResponse("Payment cancelled. Reply with 00 to return to main menu.");
        }

        if ($response !== '1' && strtolower($response) !== 'confirm') {
            if (config('app.debug')) {
                Log::warning('Invalid confirmation response:', ['response' => $response]);
            }

            return $this->formatMenuResponse(
                "Invalid response. Please confirm or cancel the payment:",
                [
                    '1' => ['text' => 'Confirm'],
                    '2' => ['text' => 'Cancel']
                ]
            );
        }

        // Update session for PIN verification
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'BILL_PAYMENT_INIT', // Keep the state consistent
            'data' => [
                ...$sessionData['data'],
                'step' => self::STATES['PIN_VERIFICATION']
            ]
        ]);

        if (config('app.debug')) {
            Log::info('Updated session for PIN verification');
        }

        return $this->formatTextResponse("Please enter your PIN (4 digits) to complete the payment:");
    }

    protected function processPinVerification(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing PIN verification:', [
                'session' => $sessionData
            ]);
        }

        $pin = $message['content'];

        // Validate PIN
        if (strlen($pin) !== 4 || !is_numeric($pin)) {
            if (config('app.debug')) {
                Log::warning('Invalid PIN format');
            }

            return $this->formatTextResponse("Invalid PIN. Please enter a 4-digit PIN:");
        }

        // Simulate successful payment (replace with actual payment processing)
        $paymentData = $sessionData['data'];
        $successMsg = $this->formatSuccessMessage(
            $paymentData['bill_type']['name'],
            $paymentData['account_number'],
            $paymentData['amount']
        );

        // Reset session to welcome state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME'
        ]);

        if (config('app.debug')) {
            Log::info('Payment successful, session reset to welcome state');
        }

        return $this->formatTextResponse($successMsg . "\n\nReply with 00 to return to main menu.");
    }

    protected function getFixedAmount(string $billType): string
    {
        // Simulate fetching fixed amounts (replace with actual implementation)
        return match($billType) {
            'TV' => '1500.00',
            'NET' => '2999.00',
            default => '0.00'
        };
    }

    protected function formatSuccessMessage(string $billType, string $accountNumber, string $amount): string
    {
        $currency = config('social-banking.currency', 'KES');

        return "Payment successful! ✅\n\n" .
               "Bill Type: {$billType}\n" .
               "Account: {$accountNumber}\n" .
               "Amount: {$currency} {$amount}\n" .
               "Reference: " . $this->generateReference();
    }

    protected function generateReference(): string
    {
        return 'BILL' . strtoupper(uniqid());
    }
}
