<?php

namespace App\Http\Controllers\Chat;

use Illuminate\Support\Facades\Log;

class TransferController extends BaseMessageController
{
    // Transfer flow states
    const STATES = [
        'RECIPIENT_INPUT' => 'RECIPIENT_INPUT',
        'AMOUNT_INPUT' => 'AMOUNT_INPUT',
        'CONFIRM_TRANSFER' => 'CONFIRM_TRANSFER',
    ];

    public function handleTransfer(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Initializing transfer menu:', [
                'message' => $message,
                'session' => $sessionData
            ]);
        }

        return $this->formatMenuResponse(
            "Please select transfer type:\n\n",
            $this->getMenuConfig('transfer')
        );
    }

    public function processInternalTransfer(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing internal transfer:', [
                'message' => $message,
                'session' => $sessionData
            ]);
        }

        $currentStep = $sessionData['data']['step'] ?? null;
        
        return match($currentStep) {
            self::STATES['RECIPIENT_INPUT'] => $this->processRecipientInput($message, $sessionData, 'internal'),
            self::STATES['AMOUNT_INPUT'] => $this->processAmountInput($message, $sessionData),
            self::STATES['CONFIRM_TRANSFER'] => $this->processTransferConfirmation($message, $sessionData),
            default => $this->initializeTransfer($message, $sessionData, 'internal')
        };
    }

    public function processBankTransfer(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing bank transfer:', [
                'message' => $message,
                'session' => $sessionData
            ]);
        }

        $currentStep = $sessionData['data']['step'] ?? null;
        
        return match($currentStep) {
            self::STATES['RECIPIENT_INPUT'] => $this->processRecipientInput($message, $sessionData, 'bank'),
            self::STATES['AMOUNT_INPUT'] => $this->processAmountInput($message, $sessionData),
            self::STATES['CONFIRM_TRANSFER'] => $this->processTransferConfirmation($message, $sessionData),
            default => $this->initializeTransfer($message, $sessionData, 'bank')
        };
    }

    public function processMobileMoneyTransfer(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing mobile money transfer:', [
                'message' => $message,
                'session' => $sessionData
            ]);
        }

        $currentStep = $sessionData['data']['step'] ?? null;
        
        return match($currentStep) {
            self::STATES['RECIPIENT_INPUT'] => $this->processRecipientInput($message, $sessionData, 'mobile'),
            self::STATES['AMOUNT_INPUT'] => $this->processAmountInput($message, $sessionData),
            self::STATES['CONFIRM_TRANSFER'] => $this->processTransferConfirmation($message, $sessionData),
            default => $this->initializeTransfer($message, $sessionData, 'mobile')
        };
    }

    protected function initializeTransfer(array $message, array $sessionData, string $type): array
    {
        if (config('app.debug')) {
            Log::info('Initializing transfer:', [
                'type' => $type,
                'session' => $sessionData
            ]);
        }

        // Update session with initial transfer data while preserving session data
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => match($type) {
                'internal' => 'INTERNAL_TRANSFER',
                'bank' => 'BANK_TRANSFER',
                'mobile' => 'MOBILE_MONEY_TRANSFER'
            },
            'data' => [
                ...$sessionData['data'] ?? [], // Preserve existing session data
                'transfer_type' => $type,
                'step' => self::STATES['RECIPIENT_INPUT']
            ]
        ]);

        $prompts = [
            'internal' => "Please enter recipient's account number (10 digits):",
            'bank' => "Please enter recipient's bank account number (10 digits):",
            'mobile' => "Please enter recipient's mobile number (format: 07XXXXXXXX):"
        ];

        return $this->formatTextResponse($prompts[$type]);
    }

    protected function processRecipientInput(array $message, array $sessionData, string $type): array
    {
        if (config('app.debug')) {
            Log::info('Processing recipient input:', [
                'recipient' => $message['content'],
                'type' => $type,
                'session' => $sessionData
            ]);
        }

        $recipient = $message['content'];
        
        // Validate recipient based on type
        if (!$this->validateRecipient($recipient, $type)) {
            if (config('app.debug')) {
                Log::warning('Invalid recipient format:', [
                    'recipient' => $recipient,
                    'type' => $type
                ]);
            }

            $errorMessages = [
                'internal' => "Invalid format. Please enter a 10-digit account number:",
                'bank' => "Invalid format. Please enter a 10-digit bank account number:",
                'mobile' => "Invalid format. Please enter a valid mobile number (07XXXXXXXX):"
            ];

            return $this->formatTextResponse($errorMessages[$type]);
        }

        // Update session with recipient while preserving state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => $sessionData['state'], // Maintain current state
            'data' => [
                ...$sessionData['data'],
                'recipient' => $recipient,
                'step' => self::STATES['AMOUNT_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter the amount to transfer:");
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
        if (!$this->validateAmount($amount)) {
            if (config('app.debug')) {
                Log::warning('Invalid amount:', ['amount' => $amount]);
            }

            return $this->formatTextResponse("Invalid amount. Please enter a valid number:");
        }

        // Update session with amount while preserving state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => $sessionData['state'], // Maintain current state
            'data' => [
                ...$sessionData['data'],
                'amount' => $amount,
                'step' => self::STATES['CONFIRM_TRANSFER']
            ]
        ]);

        // Format confirmation message based on transfer type
        $confirmationMsg = $this->formatConfirmationMessage($sessionData['data']['transfer_type'], $sessionData['data']['recipient'], $amount);
        
        return $this->formatMenuResponse(
            $confirmationMsg,
            [
                '1' => ['text' => 'Confirm'],
                '2' => ['text' => 'Cancel']
            ]
        );
    }

    protected function processTransferConfirmation(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing transfer confirmation:', [
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
                Log::info('Transfer cancelled, returning to welcome state');
            }

            return $this->formatTextResponse("Transfer cancelled. Reply with 00 to return to main menu.");
        }

        if ($response !== '1' && strtolower($response) !== 'confirm') {
            if (config('app.debug')) {
                Log::warning('Invalid confirmation response:', ['response' => $response]);
            }

            return $this->formatMenuResponse(
                "Invalid response. Please confirm or cancel the transfer:",
                [
                    '1' => ['text' => 'Confirm'],
                    '2' => ['text' => 'Cancel']
                ]
            );
        }

        // Process the transfer directly since PIN was already verified at login
        $transferData = $sessionData['data'];
        $successMsg = $this->formatSuccessMessage(
            $transferData['transfer_type'],
            $transferData['recipient'],
            $transferData['amount']
        );

        // Reset session to welcome state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME'
        ]);

        if (config('app.debug')) {
            Log::info('Transfer successful, session reset to welcome state');
        }

        return $this->formatTextResponse($successMsg . "\n\nReply with 00 to return to main menu.");
    }

    protected function validateRecipient(string $recipient, string $type): bool
    {
        return match($type) {
            'internal' => preg_match('/^\d{10}$/', $recipient), // 10-digit account number
            'bank' => preg_match('/^\d{10}$/', $recipient), // 10-digit bank account
            'mobile' => preg_match('/^07\d{8}$/', $recipient), // Valid mobile number format
            default => false
        };
    }

    protected function validateAmount(string $amount): bool
    {
        return is_numeric($amount) && $amount > 0;
    }

    protected function formatConfirmationMessage(string $type, string $recipient, string $amount): string
    {
        $typeLabels = [
            'internal' => 'internal transfer',
            'bank' => 'bank transfer',
            'mobile' => 'mobile money transfer'
        ];

        $currency = config('social-banking.currency', 'MWK');

        return "Please confirm {$typeLabels[$type]}:\n\n" .
               "Recipient: {$recipient}\n" .
               "Amount: {$currency} {$amount}\n\n" .
               "Select an option:";
    }

    protected function formatSuccessMessage(string $type, string $recipient, string $amount): string
    {
        $currency = config('social-banking.currency', 'MWK');

        return "Transfer successful! ✅\n\n" .
               "Amount: {$currency} {$amount}\n" .
               "Recipient: {$recipient}\n" .
               "Reference: " . $this->generateReference();
    }

    protected function generateReference(): string
    {
        return 'TRX' . strtoupper(uniqid());
    }
}
