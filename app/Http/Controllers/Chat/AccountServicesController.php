<?php

namespace App\Http\Controllers\Chat;

use Illuminate\Support\Facades\Log;

class AccountServicesController extends BaseMessageController
{
    // Account services flow states
    const STATES = [
        'DATE_RANGE_SELECTION' => 'DATE_RANGE_SELECTION',
        'START_DATE_INPUT' => 'START_DATE_INPUT',
        'END_DATE_INPUT' => 'END_DATE_INPUT',
        'PIN_MANAGEMENT_SELECTION' => 'PIN_MANAGEMENT_SELECTION',
        'CURRENT_PIN_INPUT' => 'CURRENT_PIN_INPUT',
        'NEW_PIN_INPUT' => 'NEW_PIN_INPUT',
        'CONFIRM_NEW_PIN' => 'CONFIRM_NEW_PIN'
    ];

    public function handleAccountServices(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Initializing account services menu:', [
                'message' => $message,
                'session' => $sessionData
            ]);
        }

        // If we have input, process the menu selection
        if (isset($message['content'])) {
            $input = $message['content'];
            $servicesMenu = $this->getMenuConfig('account_services');

            // Check if input matches a menu option
            if (isset($servicesMenu[$input])) {
                $selectedOption = $servicesMenu[$input];

                // Update session state to the selected service
                $this->messageAdapter->updateSession($message['session_id'], [
                    'state' => $selectedOption['state'],
                    'data' => [
                        'last_message' => $input,
                        'selected_option' => $input
                    ]
                ]);

                // Route to appropriate handler based on selection
                return match($selectedOption['state']) {
                    'BALANCE_INQUIRY' => $this->processBalanceInquiry($message, $sessionData),
                    'MINI_STATEMENT' => $this->processMiniStatement($message, $sessionData),
                    'FULL_STATEMENT' => $this->processFullStatement($message, $sessionData),
                    'PIN_MANAGEMENT' => $this->processPINManagement($message, $sessionData),
                    default => $this->formatMenuResponse(
                        "Invalid selection. Please select a service:\n\n",
                        $servicesMenu
                    )
                };
            }

            // Invalid selection, show menu again
            return $this->formatMenuResponse(
                "Invalid selection. Please select a service:\n\n",
                $servicesMenu
            );
        }

        // No input, show initial menu
        return $this->formatMenuResponse(
            "Please select a service:\n\n",
            $this->getMenuConfig('account_services')
        );
    }

    public function processBalanceInquiry(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing balance inquiry:', [
                'message' => $message,
                'session' => $sessionData
            ]);
        }

        // Simulate balance fetch (replace with actual implementation)
        $balance = $this->getAccountBalance();
        $currency = config('social-banking.currency', 'MWK');

        // Reset session to welcome state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME'
        ]);

        if (config('app.debug')) {
            Log::info('Balance inquiry successful, returning to welcome state');
        }

        return $this->formatTextResponse(
            "Your current balance is:\n\n" .
            "Available Balance: {$currency} {$balance['available']}\n" .
            "Actual Balance: {$currency} {$balance['actual']}\n\n" .
            "Reply with 00 to return to main menu."
        );
    }

    public function processMiniStatement(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing mini statement:', [
                'message' => $message,
                'session' => $sessionData
            ]);
        }

        // Simulate fetching mini statement (replace with actual implementation)
        $transactions = $this->getMiniStatement();
        $currency = config('social-banking.currency', 'MWK');
        
        // Format transactions into readable text
        $statementText = "Last 5 Transactions:\n\n";
        foreach ($transactions as $tx) {
            $statementText .= "{$tx['date']} | {$tx['description']} | {$currency} {$tx['amount']}\n";
        }

        // Reset session to welcome state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME'
        ]);

        if (config('app.debug')) {
            Log::info('Mini statement retrieved successfully, returning to welcome state');
        }

        return $this->formatTextResponse($statementText . "\n\nReply with 00 to return to main menu.");
    }

    public function processFullStatement(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing full statement:', [
                'message' => $message,
                'session' => $sessionData
            ]);
        }

        $currentStep = $sessionData['data']['step'] ?? null;

        return match($currentStep) {
            self::STATES['START_DATE_INPUT'] => $this->processStartDateInput($message, $sessionData),
            self::STATES['END_DATE_INPUT'] => $this->processEndDateInput($message, $sessionData),
            default => $this->initializeFullStatement($message, $sessionData)
        };
    }

    protected function initializeFullStatement(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Initializing full statement request');
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'FULL_STATEMENT',
            'data' => [
                'step' => self::STATES['START_DATE_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter start date (DD/MM/YYYY):");
    }

    protected function processStartDateInput(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing start date input:', [
                'date' => $message['content'],
                'session' => $sessionData
            ]);
        }

        $startDate = $message['content'];

        if (!$this->validateDate($startDate)) {
            if (config('app.debug')) {
                Log::warning('Invalid date format:', ['date' => $startDate]);
            }

            return $this->formatTextResponse("Invalid date format. Please enter date as DD/MM/YYYY:");
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'FULL_STATEMENT',
            'data' => [
                'start_date' => $startDate,
                'step' => self::STATES['END_DATE_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter end date (DD/MM/YYYY):");
    }

    protected function processEndDateInput(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing end date input:', [
                'date' => $message['content'],
                'session' => $sessionData
            ]);
        }

        $endDate = $message['content'];

        if (!$this->validateDate($endDate)) {
            if (config('app.debug')) {
                Log::warning('Invalid date format:', ['date' => $endDate]);
            }

            return $this->formatTextResponse("Invalid date format. Please enter date as DD/MM/YYYY:");
        }

        // Simulate fetching full statement (replace with actual implementation)
        $transactions = $this->getFullStatement($sessionData['data']['start_date'], $endDate);
        $currency = config('social-banking.currency', 'MWK');
        
        // Format transactions into readable text
        $statementText = "Statement for {$sessionData['data']['start_date']} to {$endDate}:\n\n";
        foreach ($transactions as $tx) {
            $statementText .= "{$tx['date']} | {$tx['description']} | {$currency} {$tx['amount']}\n";
        }

        // Reset session to welcome state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME'
        ]);

        if (config('app.debug')) {
            Log::info('Full statement retrieved successfully, returning to welcome state');
        }

        return $this->formatTextResponse($statementText . "\n\nReply with 00 to return to main menu.");
    }

    public function processPINManagement(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing PIN management:', [
                'message' => $message,
                'session' => $sessionData
            ]);
        }

        $currentStep = $sessionData['data']['step'] ?? null;

        return match($currentStep) {
            self::STATES['PIN_MANAGEMENT_SELECTION'] => $this->processPinManagementSelection($message, $sessionData),
            self::STATES['CURRENT_PIN_INPUT'] => $this->processCurrentPinInput($message, $sessionData),
            self::STATES['NEW_PIN_INPUT'] => $this->processNewPinInput($message, $sessionData),
            self::STATES['CONFIRM_NEW_PIN'] => $this->processConfirmNewPin($message, $sessionData),
            default => $this->initializePinManagement($message, $sessionData)
        };
    }

    protected function initializePinManagement(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Initializing PIN management');
        }

        // Only keep essential session data, removing any previous step information
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'PIN_MANAGEMENT',
            'data' => [
                'step' => self::STATES['PIN_MANAGEMENT_SELECTION']
            ]
        ]);

        return $this->formatMenuResponse(
            "PIN Management:\n\nSelect an option:",
            [
                '1' => ['text' => 'Change PIN'],
                '2' => ['text' => 'Reset PIN'],
                '3' => ['text' => 'Set Transaction PIN']
            ]
        );
    }

    protected function processPinManagementSelection(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing PIN management selection:', [
                'selection' => $message['content'],
                'session' => $sessionData
            ]);
        }

        $selection = $message['content'];

        if (!in_array($selection, ['1', '2', '3'])) {
            if (config('app.debug')) {
                Log::warning('Invalid PIN management selection:', ['selection' => $selection]);
            }

            return $this->formatMenuResponse(
                "Invalid selection. Please select an option:",
                [
                    '1' => ['text' => 'Change PIN'],
                    '2' => ['text' => 'Reset PIN'],
                    '3' => ['text' => 'Set Transaction PIN']
                ]
            );
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'PIN_MANAGEMENT',
            'data' => [
                'pin_action' => $selection,
                'step' => self::STATES['CURRENT_PIN_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter your current PIN (4 digits):");
    }

    protected function processCurrentPinInput(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing current PIN input:', [
                'session' => $sessionData
            ]);
        }

        $pin = $message['content'];

        if (!$this->validatePin($pin)) {
            if (config('app.debug')) {
                Log::warning('Invalid PIN format');
            }

            return $this->formatTextResponse("Invalid PIN. Please enter a 4-digit PIN:");
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'PIN_MANAGEMENT',
            'data' => [
                'pin_action' => $sessionData['data']['pin_action'],
                'step' => self::STATES['NEW_PIN_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter your new PIN (4 digits):");
    }

    protected function processNewPinInput(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing new PIN input:', [
                'session' => $sessionData
            ]);
        }

        $newPin = $message['content'];

        if (!$this->validatePin($newPin)) {
            if (config('app.debug')) {
                Log::warning('Invalid PIN format');
            }

            return $this->formatTextResponse("Invalid PIN. Please enter a 4-digit PIN:");
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'PIN_MANAGEMENT',
            'data' => [
                'pin_action' => $sessionData['data']['pin_action'],
                'new_pin' => $newPin,
                'step' => self::STATES['CONFIRM_NEW_PIN']
            ]
        ]);

        return $this->formatTextResponse("Please confirm your new PIN (enter the same 4 digits again):");
    }

    protected function processConfirmNewPin(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing confirm new PIN:', [
                'session' => $sessionData
            ]);
        }

        $confirmPin = $message['content'];

        if ($confirmPin !== $sessionData['data']['new_pin']) {
            if (config('app.debug')) {
                Log::warning('PINs do not match');
            }

            return $this->formatTextResponse("PINs do not match. Please enter your new PIN (4 digits) again:");
        }

        // Simulate PIN update (replace with actual implementation)
        $action = match($sessionData['data']['pin_action']) {
            '1' => 'changed',
            '2' => 'reset',
            '3' => 'transaction PIN set'
        };

        // Reset session to welcome state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME'
        ]);

        if (config('app.debug')) {
            Log::info('PIN management successful, returning to welcome state');
        }

        return $this->formatTextResponse(
            "Your PIN has been successfully {$action}. ✅\n\n" .
            "Reply with 00 to return to main menu."
        );
    }

    protected function validatePin(string $pin): bool
    {
        return strlen($pin) === 4 && is_numeric($pin);
    }

    protected function validateDate(string $date): bool
    {
        if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return false;
        }

        $parts = explode('/', $date);
        return checkdate($parts[1], $parts[0], $parts[2]);
    }

    protected function getAccountBalance(): array
    {
        // Simulate balance fetch (replace with actual implementation)
        return [
            'available' => number_format(25000.00, 2),
            'actual' => number_format(27500.00, 2)
        ];
    }

    protected function getMiniStatement(): array
    {
        // Simulate mini statement (replace with actual implementation)
        return [
            [
                'date' => '2024-01-15',
                'description' => 'ATM WITHDRAWAL',
                'amount' => '-2,000.00'
            ],
            [
                'date' => '2024-01-14',
                'description' => 'MPESA TRANSFER',
                'amount' => '-1,500.00'
            ],
            [
                'date' => '2024-01-13',
                'description' => 'SALARY CREDIT',
                'amount' => '+45,000.00'
            ],
            [
                'date' => '2024-01-12',
                'description' => 'UTILITY BILL',
                'amount' => '-3,500.00'
            ],
            [
                'date' => '2024-01-11',
                'description' => 'BANK TRANSFER',
                'amount' => '-5,000.00'
            ]
        ];
    }

    protected function getFullStatement(string $startDate, string $endDate): array
    {
        // Simulate full statement (replace with actual implementation)
        return [
            [
                'date' => '2024-01-15',
                'description' => 'ATM WITHDRAWAL',
                'amount' => '-2,000.00'
            ],
            [
                'date' => '2024-01-14',
                'description' => 'MPESA TRANSFER',
                'amount' => '-1,500.00'
            ],
            [
                'date' => '2024-01-13',
                'description' => 'SALARY CREDIT',
                'amount' => '+45,000.00'
            ],
            // Add more transactions as needed
        ];
    }
}
