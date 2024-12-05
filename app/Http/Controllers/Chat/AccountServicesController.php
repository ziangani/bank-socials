<?php

namespace App\Http\Controllers\Chat;

class AccountServicesController extends BaseMessageController
{
    // Account services flow states
    const STATES = [
        'PIN_VERIFICATION' => 'PIN_VERIFICATION',
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
        return $this->formatMenuResponse(
            "Please select a service:\n\n",
            $this->getMenuConfig('account_services')
        );
    }

    public function processBalanceInquiry(array $message, array $sessionData): array
    {
        $currentStep = $sessionData['data']['step'] ?? null;

        if ($currentStep === self::STATES['PIN_VERIFICATION']) {
            return $this->processBalancePinVerification($message, $sessionData);
        }

        // Initialize PIN verification
        $this->messageAdapter->updateSession($message['session_id'], [
            'data' => [
                'step' => self::STATES['PIN_VERIFICATION']
            ]
        ]);

        return $this->formatTextResponse("Please enter your PIN to view balance:");
    }

    protected function processBalancePinVerification(array $message, array $sessionData): array
    {
        $pin = $message['content'];

        if (!$this->validatePin($pin)) {
            return $this->formatTextResponse("Invalid PIN. Please enter a 4-digit PIN:");
        }

        // Simulate balance fetch (replace with actual implementation)
        $balance = $this->getAccountBalance();

        // Reset session to welcome state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME'
        ]);

        return $this->formatTextResponse(
            "Your current balance is:\n\n" .
            "Available Balance: KES {$balance['available']}\n" .
            "Actual Balance: KES {$balance['actual']}\n\n" .
            "Reply with 00 to return to main menu."
        );
    }

    public function processMiniStatement(array $message, array $sessionData): array
    {
        $currentStep = $sessionData['data']['step'] ?? null;

        if ($currentStep === self::STATES['PIN_VERIFICATION']) {
            return $this->processMiniStatementPinVerification($message, $sessionData);
        }

        // Initialize PIN verification
        $this->messageAdapter->updateSession($message['session_id'], [
            'data' => [
                'step' => self::STATES['PIN_VERIFICATION']
            ]
        ]);

        return $this->formatTextResponse("Please enter your PIN to view mini statement:");
    }

    protected function processMiniStatementPinVerification(array $message, array $sessionData): array
    {
        $pin = $message['content'];

        if (!$this->validatePin($pin)) {
            return $this->formatTextResponse("Invalid PIN. Please enter a 4-digit PIN:");
        }

        // Simulate fetching mini statement (replace with actual implementation)
        $transactions = $this->getMiniStatement();
        
        // Format transactions into readable text
        $statementText = "Last 5 Transactions:\n\n";
        foreach ($transactions as $tx) {
            $statementText .= "{$tx['date']} | {$tx['description']} | KES {$tx['amount']}\n";
        }

        // Reset session to welcome state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME'
        ]);

        return $this->formatTextResponse($statementText . "\n\nReply with 00 to return to main menu.");
    }

    public function processFullStatement(array $message, array $sessionData): array
    {
        $currentStep = $sessionData['data']['step'] ?? null;

        return match($currentStep) {
            self::STATES['PIN_VERIFICATION'] => $this->processFullStatementPinVerification($message, $sessionData),
            self::STATES['START_DATE_INPUT'] => $this->processStartDateInput($message, $sessionData),
            self::STATES['END_DATE_INPUT'] => $this->processEndDateInput($message, $sessionData),
            default => $this->initializeFullStatement($message)
        };
    }

    protected function initializeFullStatement(array $message): array
    {
        $this->messageAdapter->updateSession($message['session_id'], [
            'data' => [
                'step' => self::STATES['PIN_VERIFICATION']
            ]
        ]);

        return $this->formatTextResponse("Please enter your PIN to proceed:");
    }

    protected function processFullStatementPinVerification(array $message, array $sessionData): array
    {
        $pin = $message['content'];

        if (!$this->validatePin($pin)) {
            return $this->formatTextResponse("Invalid PIN. Please enter a 4-digit PIN:");
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'data' => [
                ...$sessionData['data'],
                'step' => self::STATES['START_DATE_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter start date (DD/MM/YYYY):");
    }

    protected function processStartDateInput(array $message, array $sessionData): array
    {
        $startDate = $message['content'];

        if (!$this->validateDate($startDate)) {
            return $this->formatTextResponse("Invalid date format. Please enter date as DD/MM/YYYY:");
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'data' => [
                ...$sessionData['data'],
                'start_date' => $startDate,
                'step' => self::STATES['END_DATE_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter end date (DD/MM/YYYY):");
    }

    protected function processEndDateInput(array $message, array $sessionData): array
    {
        $endDate = $message['content'];

        if (!$this->validateDate($endDate)) {
            return $this->formatTextResponse("Invalid date format. Please enter date as DD/MM/YYYY:");
        }

        // Simulate fetching full statement (replace with actual implementation)
        $transactions = $this->getFullStatement($sessionData['data']['start_date'], $endDate);
        
        // Format transactions into readable text
        $statementText = "Statement for {$sessionData['data']['start_date']} to {$endDate}:\n\n";
        foreach ($transactions as $tx) {
            $statementText .= "{$tx['date']} | {$tx['description']} | KES {$tx['amount']}\n";
        }

        // Reset session to welcome state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME'
        ]);

        return $this->formatTextResponse($statementText . "\n\nReply with 00 to return to main menu.");
    }

    public function processPINManagement(array $message, array $sessionData): array
    {
        $currentStep = $sessionData['data']['step'] ?? null;

        return match($currentStep) {
            self::STATES['PIN_MANAGEMENT_SELECTION'] => $this->processPinManagementSelection($message, $sessionData),
            self::STATES['CURRENT_PIN_INPUT'] => $this->processCurrentPinInput($message, $sessionData),
            self::STATES['NEW_PIN_INPUT'] => $this->processNewPinInput($message, $sessionData),
            self::STATES['CONFIRM_NEW_PIN'] => $this->processConfirmNewPin($message, $sessionData),
            default => $this->initializePinManagement($message)
        };
    }

    protected function initializePinManagement(array $message): array
    {
        $this->messageAdapter->updateSession($message['session_id'], [
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
        $selection = $message['content'];

        if (!in_array($selection, ['1', '2', '3'])) {
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
            'data' => [
                ...$sessionData['data'],
                'pin_action' => $selection,
                'step' => self::STATES['CURRENT_PIN_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter your current PIN:");
    }

    protected function processCurrentPinInput(array $message, array $sessionData): array
    {
        $pin = $message['content'];

        if (!$this->validatePin($pin)) {
            return $this->formatTextResponse("Invalid PIN. Please enter a 4-digit PIN:");
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'data' => [
                ...$sessionData['data'],
                'step' => self::STATES['NEW_PIN_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter your new PIN:");
    }

    protected function processNewPinInput(array $message, array $sessionData): array
    {
        $newPin = $message['content'];

        if (!$this->validatePin($newPin)) {
            return $this->formatTextResponse("Invalid PIN. Please enter a 4-digit PIN:");
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'data' => [
                ...$sessionData['data'],
                'new_pin' => $newPin,
                'step' => self::STATES['CONFIRM_NEW_PIN']
            ]
        ]);

        return $this->formatTextResponse("Please confirm your new PIN:");
    }

    protected function processConfirmNewPin(array $message, array $sessionData): array
    {
        $confirmPin = $message['content'];

        if ($confirmPin !== $sessionData['data']['new_pin']) {
            return $this->formatTextResponse("PINs do not match. Please enter your new PIN again:");
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
