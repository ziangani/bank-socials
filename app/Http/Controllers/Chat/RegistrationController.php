<?php

namespace App\Http\Controllers\Chat;

use Illuminate\Support\Facades\Log;

class RegistrationController extends BaseMessageController
{
    // Registration flow states
    const STATES = [
        'REGISTRATION_TYPE_SELECTION' => 'REGISTRATION_TYPE_SELECTION',
        'CARD_NUMBER_INPUT' => 'CARD_NUMBER_INPUT',
        'ACCOUNT_NUMBER_INPUT' => 'ACCOUNT_NUMBER_INPUT',
        'PIN_SETUP' => 'PIN_SETUP',
        'CONFIRM_PIN' => 'CONFIRM_PIN',
        'PHONE_NUMBER_INPUT' => 'PHONE_NUMBER_INPUT',
        'OTP_VERIFICATION' => 'OTP_VERIFICATION'
    ];

    public function handleRegistration(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Initializing registration:', [
                'message' => $message,
                'session' => $sessionData
            ]);
        }

        // Get registration menu
        $registrationMenu = $this->getMenuConfig('registration');

        // Check if this is a menu selection
        if (isset($sessionData['data']['step']) && $sessionData['data']['step'] === self::STATES['REGISTRATION_TYPE_SELECTION']) {
            $selection = $message['content'];

            if (config('app.debug')) {
                Log::info('Processing registration type selection:', [
                    'selection' => $selection,
                    'menu' => $registrationMenu
                ]);
            }

            // Process menu selection
            foreach ($registrationMenu as $key => $option) {
                if ($selection == $key) {
                    // Update session with selected registration type
                    $this->messageAdapter->updateSession($message['session_id'], [
                        'state' => $option['state']
                    ]);

                    if (config('app.debug')) {
                        Log::info('Selected registration type:', [
                            'type' => $option['state']
                        ]);
                    }

                    // Route to appropriate registration flow
                    return match($option['state']) {
                        'CARD_REGISTRATION' => $this->initializeCardRegistration($message, $sessionData),
                        'ACCOUNT_REGISTRATION' => $this->initializeAccountRegistration($message, $sessionData),
                        default => $this->handleUnknownState($message, $sessionData)
                    };
                }
            }

            // Invalid selection
            if (config('app.debug')) {
                Log::warning('Invalid registration type selection:', [
                    'selection' => $selection
                ]);
            }

            return $this->formatMenuResponse(
                "Invalid selection. Please select registration type:\n\n",
                $registrationMenu
            );
        }

        // Initialize registration with type selection
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'REGISTRATION_INIT',
            'data' => [
                ...$sessionData['data'] ?? [],
                'step' => self::STATES['REGISTRATION_TYPE_SELECTION']
            ]
        ]);

        return $this->formatMenuResponse(
            "Please select registration type:\n\n",
            $registrationMenu
        );
    }

    protected function initializeCardRegistration(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Initializing card registration');
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'CARD_REGISTRATION',
            'data' => [
                ...$sessionData['data'] ?? [],
                'registration_type' => 'card',
                'step' => self::STATES['CARD_NUMBER_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter your 16-digit card number:");
    }

    protected function initializeAccountRegistration(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Initializing account registration');
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'ACCOUNT_REGISTRATION',
            'data' => [
                ...$sessionData['data'] ?? [],
                'registration_type' => 'account',
                'step' => self::STATES['ACCOUNT_NUMBER_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter your account number:");
    }

    public function processCardRegistration(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing card registration:', [
                'message' => $message,
                'session' => $sessionData
            ]);
        }

        $currentStep = $sessionData['data']['step'] ?? null;

        return match($currentStep) {
            self::STATES['CARD_NUMBER_INPUT'] => $this->processCardNumberInput($message, $sessionData),
            self::STATES['PIN_SETUP'] => $this->processPinSetup($message, $sessionData),
            self::STATES['CONFIRM_PIN'] => $this->processConfirmPin($message, $sessionData),
            self::STATES['PHONE_NUMBER_INPUT'] => $this->processPhoneNumberInput($message, $sessionData),
            self::STATES['OTP_VERIFICATION'] => $this->processOtpVerification($message, $sessionData),
            default => $this->initializeCardRegistration($message, $sessionData)
        };
    }

    public function processAccountRegistration(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing account registration:', [
                'message' => $message,
                'session' => $sessionData
            ]);
        }

        $currentStep = $sessionData['data']['step'] ?? null;

        return match($currentStep) {
            self::STATES['ACCOUNT_NUMBER_INPUT'] => $this->processAccountNumberInput($message, $sessionData),
            self::STATES['PIN_SETUP'] => $this->processPinSetup($message, $sessionData),
            self::STATES['CONFIRM_PIN'] => $this->processConfirmPin($message, $sessionData),
            self::STATES['PHONE_NUMBER_INPUT'] => $this->processPhoneNumberInput($message, $sessionData),
            self::STATES['OTP_VERIFICATION'] => $this->processOtpVerification($message, $sessionData),
            default => $this->initializeAccountRegistration($message, $sessionData)
        };
    }

    protected function processCardNumberInput(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing card number input:', [
                'card_number' => str_repeat('*', strlen($message['content'])),
                'session' => $sessionData
            ]);
        }

        $cardNumber = $message['content'];

        if (!$this->validateCardNumber($cardNumber)) {
            if (config('app.debug')) {
                Log::warning('Invalid card number format');
            }

            return $this->formatTextResponse("Invalid card number. Please enter a valid 16-digit card number:");
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'CARD_REGISTRATION',
            'data' => [
                ...$sessionData['data'],
                'card_number' => $cardNumber,
                'step' => self::STATES['PIN_SETUP']
            ]
        ]);

        return $this->formatTextResponse("Please set up your PIN (4 digits):");
    }

    protected function processAccountNumberInput(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing account number input:', [
                'account_number' => str_repeat('*', strlen($message['content'])),
                'session' => $sessionData
            ]);
        }

        $accountNumber = $message['content'];

        if (!$this->validateAccountNumber($accountNumber)) {
            if (config('app.debug')) {
                Log::warning('Invalid account number format');
            }

            return $this->formatTextResponse("Invalid account number. Please enter a valid account number:");
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'ACCOUNT_REGISTRATION',
            'data' => [
                ...$sessionData['data'],
                'account_number' => $accountNumber,
                'step' => self::STATES['PIN_SETUP']
            ]
        ]);

        return $this->formatTextResponse("Please set up your PIN (4 digits):");
    }

    protected function processPinSetup(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing PIN setup:', [
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
            'state' => $sessionData['state'],
            'data' => [
                ...$sessionData['data'],
                'pin' => $pin,
                'step' => self::STATES['CONFIRM_PIN']
            ]
        ]);

        return $this->formatTextResponse("Please confirm your PIN:");
    }

    protected function processConfirmPin(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing PIN confirmation:', [
                'session' => $sessionData
            ]);
        }

        $confirmPin = $message['content'];

        if ($confirmPin !== $sessionData['data']['pin']) {
            if (config('app.debug')) {
                Log::warning('PINs do not match');
            }

            return $this->formatTextResponse("PINs do not match. Please set up your PIN again (4 digits):");
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => $sessionData['state'],
            'data' => [
                ...$sessionData['data'],
                'step' => self::STATES['PHONE_NUMBER_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter your phone number (e.g., 07XXXXXXXX):");
    }

    protected function processPhoneNumberInput(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing phone number input:', [
                'phone' => $message['content'],
                'session' => $sessionData
            ]);
        }

        $phoneNumber = $message['content'];

        if (!$this->validatePhoneNumber($phoneNumber)) {
            if (config('app.debug')) {
                Log::warning('Invalid phone number format');
            }

            return $this->formatTextResponse("Invalid phone number. Please enter a valid phone number (e.g., 07XXXXXXXX):");
        }

        // Simulate OTP generation and sending (replace with actual implementation)
        $otp = $this->generateOtp();

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => $sessionData['state'],
            'data' => [
                ...$sessionData['data'],
                'phone_number' => $phoneNumber,
                'otp' => $otp,
                'step' => self::STATES['OTP_VERIFICATION']
            ]
        ]);

        return $this->formatTextResponse("A verification code has been sent to your phone. Please enter the code:");
    }

    protected function processOtpVerification(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing OTP verification:', [
                'session' => $sessionData
            ]);
        }

        $otp = $message['content'];

        if ($otp !== $sessionData['data']['otp']) {
            if (config('app.debug')) {
                Log::warning('Invalid OTP');
            }

            return $this->formatTextResponse("Invalid verification code. Please try again:");
        }

        // Simulate registration completion (replace with actual implementation)
        $registrationType = $sessionData['data']['registration_type'];
        $identifier = $registrationType === 'card' ? 
            $sessionData['data']['card_number'] : 
            $sessionData['data']['account_number'];

        // Reset session to welcome state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME'
        ]);

        if (config('app.debug')) {
            Log::info('Registration successful, returning to welcome state');
        }

        return $this->formatTextResponse(
            "Registration successful! ✅\n\n" .
            "Your {$registrationType} (*" . substr($identifier, -4) . ") has been registered.\n\n" .
            "Reply with 00 to return to main menu."
        );
    }

    protected function validateCardNumber(string $cardNumber): bool
    {
        return preg_match('/^\d{16}$/', $cardNumber);
    }

    protected function validateAccountNumber(string $accountNumber): bool
    {
        return preg_match('/^\d{10}$/', $accountNumber);
    }

    protected function validatePin(string $pin): bool
    {
        return preg_match('/^\d{4}$/', $pin);
    }

    protected function validatePhoneNumber(string $phoneNumber): bool
    {
        return preg_match('/^07\d{8}$/', $phoneNumber);
    }

    protected function generateOtp(): string
    {
        // Simulate OTP generation (replace with actual implementation)
        return (string) random_int(100000, 999999);
    }
}
