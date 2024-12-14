<?php

namespace App\Http\Controllers\Chat;

use Illuminate\Support\Facades\Log;

class RegistrationController extends BaseMessageController
{
    // Registration flow states
    const STATES = [
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

        // Initialize account registration directly
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'ACCOUNT_REGISTRATION',
            'data' => [
                ...$sessionData['data'] ?? [],
                'step' => self::STATES['ACCOUNT_NUMBER_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter your account number:");
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
            default => $this->handleRegistration($message, $sessionData)
        };
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

//        if ($otp !== $sessionData['data']['otp']) {
//            if (config('app.debug')) {
//                Log::warning('Invalid OTP');
//            }
//
//            return $this->formatTextResponse("Invalid verification code. Please try again:");
//        }

        $accountNumber = $sessionData['data']['account_number'];

        // Reset session to welcome state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME'
        ]);

        if (config('app.debug')) {
            Log::info('Registration successful, returning to welcome state');
        }

        return $this->formatTextResponse(
            "Registration successful! ✅\n\n" .
            "Your account (*" . substr($accountNumber, -4) . ") has been registered.\n\n" .
            "Reply with 00 to return to main menu."
        );
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
