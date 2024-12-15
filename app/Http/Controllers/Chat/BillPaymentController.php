<?php

namespace App\Http\Controllers\Chat;

use App\Common\GeneralStatus;
use App\Services\BillPaymentService;
use App\Services\SessionManager;
use App\Interfaces\MessageAdapterInterface;
use Illuminate\Support\Facades\Log;

class BillPaymentController extends BaseMessageController
{
    // Bill payment flow states
    const STATES = [
        'BILL_TYPE_SELECTION' => 'BILL_TYPE_SELECTION',
        'ACCOUNT_INPUT' => 'ACCOUNT_INPUT',
        'AMOUNT_INPUT' => 'AMOUNT_INPUT',
        'CONFIRM_PAYMENT' => 'CONFIRM_PAYMENT'
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

    protected BillPaymentService $billPaymentService;

    public function __construct(
        MessageAdapterInterface $messageAdapter, 
        SessionManager $sessionManager,
        BillPaymentService $billPaymentService
    ) {
        parent::__construct($messageAdapter, $sessionManager);
        $this->billPaymentService = $billPaymentService;
    }

    public function handleBillPayment(array $message, array $sessionData): array
    {
        try {
            Log::info('Initializing bill payment flow - Message ID: ' . ($message['id'] ?? 'null') . ', Session ID: ' . ($message['session_id'] ?? 'null'));

            // Initialize bill payment flow with bill type selection
            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'BILL_PAYMENT',
                'data' => [
                    'step' => self::STATES['BILL_TYPE_SELECTION']
                ]
            ]);

            // Build message showing all bill types
            $messageText = "Please select the type of bill to pay:\n\n";
            foreach (self::BILL_TYPES as $key => $type) {
                $messageText .= "{$key}. {$type['name']}\n";
            }
            $messageText .= "\nEnter the number of your selection:";

            // Only show first 3 as buttons for quick access
            $buttons = [];
            $count = 0;
            foreach (self::BILL_TYPES as $key => $type) {
                if ($count >= 3) break;
                $buttons[$key] = ['text' => $type['name']];
                $count++;
            }

            return $this->formatMenuResponse($messageText, $buttons);
        } catch (\Exception $e) {
            Log::error('Failed to initialize bill payment: ' . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return $this->formatTextResponse("Sorry, something went wrong. Please try again.\n\nReply with 00 to return to main menu.");
        }
    }

    public function processBillPayment(array $message, array $sessionData): array
    {
        try {
            Log::info('Processing bill payment step - Step: ' . ($sessionData['data']['step'] ?? 'null') . ', Content: ' . ($message['content'] ?? 'null'));

            $currentStep = $sessionData['data']['step'] ?? null;
            
            return match($currentStep) {
                self::STATES['BILL_TYPE_SELECTION'] => $this->processBillTypeSelection($message, $sessionData),
                self::STATES['ACCOUNT_INPUT'] => $this->processAccountInput($message, $sessionData),
                self::STATES['AMOUNT_INPUT'] => $this->processAmountInput($message, $sessionData),
                self::STATES['CONFIRM_PAYMENT'] => $this->processPaymentConfirmation($message, $sessionData),
                default => $this->handleBillPayment($message, $sessionData)
            };
        } catch (\Exception $e) {
            Log::error('Failed to process bill payment step: ' . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return $this->formatTextResponse("Sorry, something went wrong. Please try again.\n\nReply with 00 to return to main menu.");
        }
    }

    protected function processBillTypeSelection(array $message, array $sessionData): array
    {
        try {
            $selection = $message['content'];
            Log::info('Processing bill type selection: ' . $selection);
            
            if (!isset(self::BILL_TYPES[$selection])) {
                Log::warning('Invalid bill type selection: ' . $selection);

                // Build message showing all bill types again
                $messageText = "Invalid selection. Please select a valid bill type:\n\n";
                foreach (self::BILL_TYPES as $key => $type) {
                    $messageText .= "{$key}. {$type['name']}\n";
                }
                $messageText .= "\nEnter the number of your selection:";

                // Only show first 3 as buttons for quick access
                $buttons = [];
                $count = 0;
                foreach (self::BILL_TYPES as $key => $type) {
                    if ($count >= 3) break;
                    $buttons[$key] = ['text' => $type['name']];
                    $count++;
                }

                return $this->formatMenuResponse($messageText, $buttons);
            }

            $billType = self::BILL_TYPES[$selection];
            
            // Update session with bill type
            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'BILL_PAYMENT',
                'data' => [
                    'bill_type' => $billType,
                    'step' => self::STATES['ACCOUNT_INPUT']
                ]
            ]);

            return $this->formatTextResponse("Please enter your {$billType['name']} account number ({$billType['length']} digits):");
        } catch (\Exception $e) {
            Log::error('Failed to process bill type selection: ' . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return $this->formatTextResponse("Sorry, something went wrong. Please try again.\n\nReply with 00 to return to main menu.");
        }
    }

    protected function processAccountInput(array $message, array $sessionData): array
    {
        try {
            $accountNumber = $message['content'];
            $billType = $sessionData['data']['bill_type'];
            
            Log::info('Processing bill account input - Account: ' . $accountNumber . ', Type: ' . $billType['code']);
            
            // Validate account number format
            if (!preg_match($billType['pattern'], $accountNumber)) {
                Log::warning('Invalid bill account format - Account: ' . $accountNumber . ', Expected Pattern: ' . $billType['pattern']);
                return $this->formatTextResponse(
                    "Invalid account number format. Please enter a {$billType['length']}-digit account number for {$billType['name']}:"
                );
            }

            // Validate bill account with service
            $validation = $this->billPaymentService->validateBillAccount($accountNumber, $billType['code']);
            if ($validation['status'] !== GeneralStatus::SUCCESS) {
                Log::warning('Bill account validation failed - Account: ' . $accountNumber . ', Error: ' . $validation['message']);
                return $this->formatTextResponse(
                    "Invalid account number. Please check and try again.\n\nReply with 00 to return to main menu."
                );
            }

            // Extract account name from validation response
            $accountName = $validation['account_name'] ?? 'Unknown';

            if ($billType['fixed_amount']) {
                // For fixed amount bills, get amount from service
                $amount = $this->getFixedAmount($billType['code']);
                return $this->prepareConfirmation($message, [
                    ...$sessionData,
                    'data' => [
                        ...$sessionData['data'],
                        'account_number' => $accountNumber,
                        'account_name' => $accountName
                    ]
                ], $amount);
            }

            // For variable amount bills, proceed to amount input
            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'BILL_PAYMENT',
                'data' => [
                    ...$sessionData['data'],
                    'account_number' => $accountNumber,
                    'account_name' => $accountName,
                    'step' => self::STATES['AMOUNT_INPUT']
                ]
            ]);

            return $this->formatTextResponse("Please enter the amount to pay:");
        } catch (\Exception $e) {
            Log::error('Failed to process bill account input: ' . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return $this->formatTextResponse("Sorry, something went wrong. Please try again.\n\nReply with 00 to return to main menu.");
        }
    }

    protected function processAmountInput(array $message, array $sessionData): array
    {
        try {
            $amount = $message['content'];
            
            Log::info('Processing bill amount input: ' . $amount);
            
            // Validate amount
            if (!is_numeric($amount) || $amount <= 0) {
                Log::warning('Invalid bill amount: ' . $amount);
                return $this->formatTextResponse("Invalid amount. Please enter a valid number:");
            }

            return $this->prepareConfirmation($message, $sessionData, $amount);
        } catch (\Exception $e) {
            Log::error('Failed to process bill amount input: ' . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return $this->formatTextResponse("Sorry, something went wrong. Please try again.\n\nReply with 00 to return to main menu.");
        }
    }

    protected function prepareConfirmation(array $message, array $sessionData, string $amount): array
    {
        try {
            Log::info('Preparing bill payment confirmation - Type: ' . $sessionData['data']['bill_type']['code'] . ', Account: ' . $sessionData['data']['account_number'] . ', Amount: ' . $amount);

            $billType = $sessionData['data']['bill_type'];
            $accountNumber = $sessionData['data']['account_number'];
            $accountName = $sessionData['data']['account_name'];
            $currency = config('social-banking.currency', 'MWK');

            // Update session with amount and move to confirmation
            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'BILL_PAYMENT',
                'data' => [
                    ...$sessionData['data'],
                    'amount' => $amount,
                    'step' => self::STATES['CONFIRM_PAYMENT']
                ]
            ]);

            $confirmationMsg = "Please confirm bill payment:\n\n" .
                            "Type: {$billType['name']}\n" .
                            "Account: {$accountNumber}\n" .
                            "Name: {$accountName}\n" .
                            "Amount: {$currency} {$amount}\n\n" .
                            "Select an option:";

            return $this->formatMenuResponse(
                $confirmationMsg,
                [
                    '1' => ['text' => 'Confirm'],
                    '2' => ['text' => 'Cancel']
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to prepare bill payment confirmation: ' . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return $this->formatTextResponse("Sorry, something went wrong. Please try again.\n\nReply with 00 to return to main menu.");
        }
    }

    protected function processPaymentConfirmation(array $message, array $sessionData): array
    {
        try {
            $response = $message['content'];
            
            Log::info('Processing bill payment confirmation: ' . $response);

            if ($response === '2' || strtolower($response) === 'cancel') {
                // Reset to welcome state
                $this->messageAdapter->updateSession($message['session_id'], [
                    'state' => 'WELCOME'
                ]);

                Log::info('Bill payment cancelled by user');
                return $this->formatTextResponse("Payment cancelled. Reply with 00 to return to main menu.");
            }

            if ($response !== '1' && strtolower($response) !== 'confirm') {
                Log::warning('Invalid payment confirmation response: ' . $response);
                return $this->formatMenuResponse(
                    "Invalid response. Please confirm or cancel the payment:",
                    [
                        '1' => ['text' => 'Confirm'],
                        '2' => ['text' => 'Cancel']
                    ]
                );
            }

            // Get authenticated user from session data
            $user = $sessionData['authenticated_user'];

            // Process payment using service
            $paymentResult = $this->billPaymentService->processBillPayment([
                'bill_type' => $sessionData['data']['bill_type']['code'],
                'bill_account' => $sessionData['data']['account_number'],
                'amount' => $sessionData['data']['amount'],
                'payer' => $user['account_number']
            ]);

            if ($paymentResult['status'] !== GeneralStatus::SUCCESS) {
                Log::error('Bill payment processing failed: ' . $paymentResult['message']);
                return $this->formatTextResponse(
                    "Payment failed: {$paymentResult['message']}\n\nReply with 00 to return to main menu."
                );
            }

            // Reset to welcome state
            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'WELCOME'
            ]);

            Log::info('Bill payment completed successfully - Reference: ' . $paymentResult['reference']);

            $successMsg = $this->formatSuccessMessage(
                $sessionData['data']['bill_type']['name'],
                $sessionData['data']['account_number'],
                $paymentResult['amount'],
                $paymentResult['reference']
            );

            return $this->formatTextResponse($successMsg . "\n\nReply with 00 to return to main menu.");
        } catch (\Exception $e) {
            Log::error('Failed to process bill payment confirmation: ' . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return $this->formatTextResponse("Sorry, something went wrong. Please try again.\n\nReply with 00 to return to main menu.");
        }
    }

    protected function getFixedAmount(string $billType): string
    {
        // Get fixed amounts from service instead of hardcoding
        return match($billType) {
            'TV' => '1500.00',
            'NET' => '2999.00',
            default => '0.00'
        };
    }

    protected function formatSuccessMessage(
        string $billType,
        string $accountNumber,
        string $amount,
        string $reference
    ): string {
        $currency = config('social-banking.currency', 'MWK');

        return "Payment successful! ✅\n\n" .
               "Bill Type: {$billType}\n" .
               "Account: {$accountNumber}\n" .
               "Amount: {$currency} {$amount}\n" .
               "Reference: {$reference}";
    }
}
