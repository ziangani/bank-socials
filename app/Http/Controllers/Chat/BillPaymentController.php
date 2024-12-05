<?php

namespace App\Http\Controllers\Chat;

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
            'fixed_amount' => false
        ],
        '2' => [
            'name' => 'Water',
            'code' => 'WATER',
            'pattern' => '/^\d{8}$/',
            'fixed_amount' => false
        ],
        '3' => [
            'name' => 'TV Subscription',
            'code' => 'TV',
            'pattern' => '/^\d{10}$/',
            'fixed_amount' => true
        ],
        '4' => [
            'name' => 'Internet',
            'code' => 'NET',
            'pattern' => '/^\d{8}$/',
            'fixed_amount' => true
        ]
    ];

    public function handleBillPayment(array $message, array $sessionData): array
    {
        // Initialize bill payment flow with bill type selection
        $this->messageAdapter->updateSession($message['session_id'], [
            'data' => [
                'step' => self::STATES['BILL_TYPE_SELECTION']
            ]
        ]);

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
        $selection = $message['content'];
        
        if (!isset(self::BILL_TYPES[$selection])) {
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
        
        // Update session with bill type
        $this->messageAdapter->updateSession($message['session_id'], [
            'data' => [
                ...$sessionData['data'],
                'bill_type' => $billType,
                'step' => self::STATES['ACCOUNT_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter your {$billType['name']} account number:");
    }

    protected function processAccountInput(array $message, array $sessionData): array
    {
        $accountNumber = $message['content'];
        $billType = $sessionData['data']['bill_type'];
        
        // Validate account number format
        if (!preg_match($billType['pattern'], $accountNumber)) {
            return $this->formatTextResponse(
                "Invalid account number format for {$billType['name']}. Please try again:"
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
            'data' => [
                ...$sessionData['data'],
                'account_number' => $accountNumber,
                'step' => self::STATES['AMOUNT_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter the amount to pay:");
    }

    protected function processAmountInput(array $message, array $sessionData): array
    {
        $amount = $message['content'];
        
        // Validate amount
        if (!is_numeric($amount) || $amount <= 0) {
            return $this->formatTextResponse("Invalid amount. Please enter a valid number:");
        }

        return $this->prepareConfirmation($message, $sessionData, $amount);
    }

    protected function prepareConfirmation(array $message, array $sessionData, string $amount): array
    {
        $billType = $sessionData['data']['bill_type'];
        $accountNumber = $sessionData['data']['account_number'];

        // Update session with amount and move to confirmation
        $this->messageAdapter->updateSession($message['session_id'], [
            'data' => [
                ...$sessionData['data'],
                'amount' => $amount,
                'step' => self::STATES['CONFIRM_PAYMENT']
            ]
        ]);

        $confirmationMsg = "Please confirm bill payment:\n\n" .
                          "Type: {$billType['name']}\n" .
                          "Account: {$accountNumber}\n" .
                          "Amount: KES {$amount}\n\n" .
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
        $response = $message['content'];

        if ($response === '2' || strtolower($response) === 'cancel') {
            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'WELCOME'
            ]);
            return $this->formatTextResponse("Payment cancelled. Reply with 00 to return to main menu.");
        }

        if ($response !== '1' && strtolower($response) !== 'confirm') {
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
            'data' => [
                ...$sessionData['data'],
                'step' => self::STATES['PIN_VERIFICATION']
            ]
        ]);

        return $this->formatTextResponse("Please enter your PIN to complete the payment:");
    }

    protected function processPinVerification(array $message, array $sessionData): array
    {
        $pin = $message['content'];

        // Validate PIN
        if (strlen($pin) !== 4 || !is_numeric($pin)) {
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
        return "Payment successful! ✅\n\n" .
               "Bill Type: {$billType}\n" .
               "Account: {$accountNumber}\n" .
               "Amount: KES {$amount}\n" .
               "Reference: " . $this->generateReference();
    }

    protected function generateReference(): string
    {
        return 'BILL' . strtoupper(uniqid());
    }
}
