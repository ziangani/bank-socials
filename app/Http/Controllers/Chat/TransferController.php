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
            'internal' => "Please enter recipient's account number:",
            'bank' => "Please enter recipient's bank account number:",
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

        // Validate recipient format based on type
        if (!$this->validateRecipient($recipient, $type)) {
            if (config('app.debug')) {
                Log::warning('Invalid recipient format:', [
                    'recipient' => $recipient,
                    'type' => $type
                ]);
            }

            $errorMessages = [
                'internal' => "Invalid format. Please enter a valid account number:",
                'bank' => "Invalid format. Please enter a valid bank account number:",
                'mobile' => "Invalid format. Please enter a valid mobile number (07XXXXXXXX):"
            ];

            return $this->formatTextResponse($errorMessages[$type]);
        }

        // For bank transfers, verify account exists
        if (in_array($type, ['internal', 'bank'])) {
            $esb = new \App\Integrations\ESB();
            $result = $esb->getAccountDetailsAndBalance($recipient);

            if (!$result['status']) {
                if (config('app.debug')) {
                    Log::warning('Account verification failed:', [
                        'recipient' => $recipient,
                        'error' => $result['message']
                    ]);
                }
                return $this->formatTextResponse(
                    "Account not found or invalid. Please check the account number and try again:"
                );
            }

            // Account exists, update session with recipient details
            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => $sessionData['state'],
                'data' => [
                    ...$sessionData['data'],
                    'recipient' => $recipient,
                    'recipient_name' => $result['data']['name'] ?? 'Unknown',
                    'step' => self::STATES['AMOUNT_INPUT']
                ]
            ]);

            return $this->formatTextResponse(
                "Account verified ✅\n" .
                "Account holder: {$result['data']['name']}\n\n" .
                "Please enter the amount to transfer:"
            );
        }

        // For mobile money, just store the number
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
        $confirmationMsg = $this->formatConfirmationMessage(
            $sessionData['data']['transfer_type'],
            $sessionData['data']['recipient'],
            $amount,
            $sessionData
        );

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

        // Get authenticated user
        $user = $sessionData['authenticated_user'] ?? null;
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        // Process the transfer through ESB
        $transferData = $sessionData['data'];
        $esb = new \App\Integrations\ESB();

        $result = $esb->transferToBankAccount(
            $user->account_number,
            $transferData['recipient'],
            $transferData['amount'],
            'Transfer via Social Banking'
        );

        if (!$result['status']) {
            // Transfer failed
            if (config('app.debug')) {
                Log::error('Transfer failed:', ['error' => $result['message']]);
            }
            return $this->formatTextResponse(
                "Transfer failed: {$result['message']}\n\n" .
                "Reply with 00 to return to main menu."
            );
        }

        // Reset session to welcome state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME'
        ]);

        if (config('app.debug')) {
            Log::info('Transfer submitted:', ['result' => $result]);
        }

        $successMsg = $this->formatSuccessMessage(
            $transferData['transfer_type'],
            $transferData['recipient'],
            $transferData['amount'],
            $result['data']
        );

        return $this->formatTextResponse($successMsg . "\n\nReply with 00 to return to main menu.");
    }

    protected function validateRecipient(string $recipient, string $type): bool
    {
        return match($type) {
            'internal' => preg_match('/^\d+$/', $recipient), // Any number of digits
            'bank' => preg_match('/^\d+$/', $recipient), // Any number of digits
            'mobile' => preg_match('/^07\d{8}$/', $recipient), // Valid mobile number format
            default => false
        };
    }

    protected function validateAmount(string $amount): bool
    {
        return is_numeric($amount) && $amount > 0;
    }

    protected function formatConfirmationMessage(string $type, string $recipient, string $amount, array $sessionData): string
    {
        $typeLabels = [
            'internal' => 'internal transfer',
            'bank' => 'bank transfer',
            'mobile' => 'mobile money transfer'
        ];

        $currency = config('social-banking.currency', 'MWK');
        $recipientName = $sessionData['data']['recipient_name'] ?? null;

        $message = "Please confirm {$typeLabels[$type]}:\n\n";
        if ($recipientName) {
            $message .= "To: {$recipientName}\n";
        }
        $message .= "Account: {$recipient}\n" .
                   "Amount: {$currency} {$amount}\n\n" .
                   "Select an option:";

        return $message;
    }

    protected function formatSuccessMessage(string $type, string $recipient, string $amount, array $data): string
    {
        $currency = config('social-banking.currency', 'MWK');
        $status = ucfirst($data['status']); // Convert 'pending' to 'Pending'

        return "Transfer {$status}! ✅\n\n" .
               "Amount: {$currency} {$amount}\n" .
               "Recipient: {$recipient}\n" .
               "Reference: {$data['reference']}\n" .
               "Date: {$data['value_date']}";
    }
}
