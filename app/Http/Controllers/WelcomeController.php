<?php

namespace App\Http\Controllers;

use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WelcomeController extends Controller
{
    protected MessageAdapterInterface $messageAdapter;
    protected SessionManager $sessionManager;

    public function __construct(MessageAdapterInterface $messageAdapter, SessionManager $sessionManager)
    {
        $this->messageAdapter = $messageAdapter;
        $this->sessionManager = $sessionManager;
    }

    /**
     * Handle initial welcome message
     */
    public function welcome(array $message, array $sessionData = []): array
    {
        $contactName = $message['contact_name'] ?? 'there';
        
        // Check if user is registered
        if ($this->isUserRegistered($message['sender'])) {
            return $this->welcomeRegisteredUser($contactName);
        }

        return $this->welcomeNewUser($contactName);
    }

    /**
     * Welcome message for registered users
     */
    protected function welcomeRegisteredUser(string $contactName): array
    {
        $menuText = "Welcome back {$contactName}! 👋\n\n";
        $menuText .= "Please select from the following options:\n\n";
        $menuText .= "1. Money Transfer\n";
        $menuText .= "2. Bill Payments\n";
        $menuText .= "3. Account Services\n";
        $menuText .= "4. Help";

        return [
            'message' => $menuText,
            'type' => 'interactive',
            'buttons' => [
                '1' => 'Money Transfer',
                '2' => 'Bill Payments',
                '3' => 'Account Services',
                '4' => 'Help'
            ],
            'end_session' => false
        ];
    }

    /**
     * Welcome message for new users
     */
    protected function welcomeNewUser(string $contactName): array
    {
        $menuText = "Hello {$contactName}! 👋\n\n";
        $menuText .= "Welcome to our Social Banking Service. To get started, you'll need to register first.\n\n";
        $menuText .= "Please select an option:\n\n";
        $menuText .= "1. Register with Account\n";
        $menuText .= "2. Learn More\n";
        $menuText .= "3. Help";

        return [
            'message' => $menuText,
            'type' => 'interactive',
            'buttons' => [
                '1' => 'Register with Account',
                '2' => 'Learn More',
                '3' => 'Help'
            ],
            'end_session' => false
        ];
    }

    /**
     * Handle account registration
     */
    public function handleAccountRegistration(array $message, array $sessionData): array
    {
        $state = $sessionData['state'] ?? 'INIT';
        $data = $sessionData['data'] ?? [];

        return match($state) {
            'INIT' => [
                'message' => "Please enter your account number:",
                'type' => 'text',
                'end_session' => false
            ],
            'ACCOUNT_ENTERED' => $this->validateAccount($message['content'], $data),
            'ACCOUNT_VALIDATED' => $this->requestIDNumber($message['content'], $data),
            'ID_ENTERED' => $this->validateIDNumber($message['content'], $data),
            'ID_VALIDATED' => $this->requestOTP($message['content'], $data),
            'OTP_ENTERED' => $this->validateOTP($message['content'], $data),
            default => $this->handleUnknownState()
        };
    }

    /**
     * Check if user is registered
     */
    protected function isUserRegistered(string $identifier): bool
    {
        // TODO: Implement actual user registration check
        return false;
    }

    /**
     * Validate account number
     */
    protected function validateAccount(string $accountNumber, array $data): array
    {
        // TODO: Implement actual account validation logic

        return [
            'message' => "Please enter your National ID number:",
            'type' => 'text',
            'end_session' => false
        ];
    }

    /**
     * Request ID number
     */
    protected function requestIDNumber(string $input, array $data): array
    {
        return [
            'message' => "Please enter your National ID number:",
            'type' => 'text',
            'end_session' => false
        ];
    }

    /**
     * Validate ID number
     */
    protected function validateIDNumber(string $idNumber, array $data): array
    {
        // TODO: Implement actual ID validation logic

        // Generate and send OTP
        $otp = $this->generateAndSendOTP($data);

        return [
            'message' => "A one-time PIN has been sent to your registered mobile number. Please enter it:",
            'type' => 'text',
            'end_session' => false
        ];
    }

    /**
     * Request OTP
     */
    protected function requestOTP(string $input, array $data): array
    {
        return [
            'message' => "A one-time PIN has been sent to your registered mobile number. Please enter it:",
            'type' => 'text',
            'end_session' => false
        ];
    }

    /**
     * Validate OTP
     */
    protected function validateOTP(string $otp, array $data): array
    {
        // TODO: Implement actual OTP validation logic

        return [
            'message' => "Registration successful! 🎉\n\nYou can now access all our banking services.\n\nPlease set up your transaction PIN for future use:",
            'type' => 'text',
            'end_session' => false
        ];
    }

    /**
     * Generate and send OTP
     */
    protected function generateAndSendOTP(array $data): string
    {
        // TODO: Implement actual OTP generation and sending logic
        return '123456';
    }

    /**
     * Handle unknown state
     */
    protected function handleUnknownState(): array
    {
        return [
            'message' => "Sorry, we encountered an error. Please try again.",
            'type' => 'text',
            'end_session' => true
        ];
    }
}
