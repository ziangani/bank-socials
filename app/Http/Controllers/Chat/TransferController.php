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
        'PIN_VERIFICATION' => 'PIN_VERIFICATION',
    ];

    public function handleTransfer(array $message, array $sessionData): array
    {
        return $this->formatMenuResponse(
            "Please select transfer type:\n\n",
            $this->getMenuConfig('transfer')
        );
    }

    public function processInternalTransfer(array $message, array $sessionData): array
    {
        $currentStep = $sessionData['data']['step'] ?? null;
        
        return match($currentStep) {
            self::STATES['RECIPIENT_INPUT'] => $this->processRecipientInput($message, $sessionData, 'internal'),
            self::STATES['AMOUNT_INPUT'] => $this->processAmountInput($message, $sessionData),
            self::STATES['CONFIRM_TRANSFER'] => $this->processTransferConfirmation($message, $sessionData),
            self::STATES['PIN_VERIFICATION'] => $this->processPinVerification($message, $sessionData),
            default => $this->initializeTransfer($message, 'internal')
        };
    }

    public function processBankTransfer(array $message, array $sessionData): array
    {
        $currentStep = $sessionData['data']['step'] ?? null;
        
        return match($currentStep) {
            self::STATES['RECIPIENT_INPUT'] => $this->processRecipientInput($message, $sessionData, 'bank'),
            self::STATES['AMOUNT_INPUT'] => $this->processAmountInput($message, $sessionData),
            self::STATES['CONFIRM_TRANSFER'] => $this->processTransferConfirmation($message, $sessionData),
            self::STATES['PIN_VERIFICATION'] => $this->processPinVerification($message, $sessionData),
            default => $this->initializeTransfer($message, 'bank')
        };
    }

    public function processMobileMoneyTransfer(array $message, array $sessionData): array
    {
        $currentStep = $sessionData['data']['step'] ?? null;
        
        return match($currentStep) {
            self::STATES['RECIPIENT_INPUT'] => $this->processRecipientInput($message, $sessionData, 'mobile'),
            self::STATES['AMOUNT_INPUT'] => $this->processAmountInput($message, $sessionData),
            self::STATES['CONFIRM_TRANSFER'] => $this->processTransferConfirmation($message, $sessionData),
            self::STATES['PIN_VERIFICATION'] => $this->processPinVerification($message, $sessionData),
            default => $this->initializeTransfer($message, 'mobile')
        };
    }

    protected function initializeTransfer(array $message, string $type): array
    {
        // Update session with initial transfer data
        $this->messageAdapter->updateSession($message['session_id'], [
            'data' => [
                'transfer_type' => $type,
                'step' => self::STATES['RECIPIENT_INPUT']
            ]
        ]);

        $prompts = [
            'internal' => "Please enter recipient's account number:",
            'bank' => "Please enter recipient's bank account number:",
            'mobile' => "Please enter recipient's mobile number:"
        ];

        return $this->formatTextResponse($prompts[$type]);
    }

    protected function processRecipientInput(array $message, array $sessionData, string $type): array
    {
        $recipient = $message['content'];
        
        // Validate recipient based on type
        if (!$this->validateRecipient($recipient, $type)) {
            return $this->formatTextResponse("Invalid recipient format. Please try again.");
        }

        // Update session with recipient
        $this->messageAdapter->updateSession($message['session_id'], [
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
        $amount = $message['content'];
        
        // Validate amount
        if (!$this->validateAmount($amount)) {
            return $this->formatTextResponse("Invalid amount. Please enter a valid number:");
        }

        // Update session with amount
        $this->messageAdapter->updateSession($message['session_id'], [
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
        $response = $message['content'];

        if ($response === '2' || strtolower($response) === 'cancel') {
            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'WELCOME'
            ]);
            return $this->formatTextResponse("Transfer cancelled. Reply with 00 to return to main menu.");
        }

        if ($response !== '1' && strtolower($response) !== 'confirm') {
            return $this->formatMenuResponse(
                "Invalid response. Please confirm or cancel the transfer:",
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

        return $this->formatTextResponse("Please enter your PIN to complete the transfer:");
    }

    protected function processPinVerification(array $message, array $sessionData): array
    {
        $pin = $message['content'];

        // Simulate PIN verification (replace with actual verification logic)
        if (strlen($pin) !== 4 || !is_numeric($pin)) {
            return $this->formatTextResponse("Invalid PIN. Please enter a 4-digit PIN:");
        }

        // Simulate successful transfer (replace with actual transfer logic)
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

        return "Please confirm {$typeLabels[$type]}:\n\n" .
               "Recipient: {$recipient}\n" .
               "Amount: KES {$amount}\n\n" .
               "Select an option:";
    }

    protected function formatSuccessMessage(string $type, string $recipient, string $amount): string
    {
        return "Transfer successful! ✅\n\n" .
               "Amount: KES {$amount}\n" .
               "Recipient: {$recipient}\n" .
               "Reference: " . $this->generateReference();
    }

    protected function generateReference(): string
    {
        return 'TRX' . strtoupper(uniqid());
    }
}
