<?php

namespace App\Http\Controllers\Chat;

use App\Services\AuthenticationService;
use App\Models\ChatUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class RegistrationController extends BaseMessageController
{
    // Registration flow states
    const STATES = [
        'ACCOUNT_NUMBER_INPUT' => 'ACCOUNT_NUMBER_INPUT',
        'PIN_SETUP' => 'PIN_SETUP',
        'CONFIRM_PIN' => 'CONFIRM_PIN',
        'OTP_VERIFICATION' => 'OTP_VERIFICATION'
    ];

    protected AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    public function handleRegistration(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Initializing registration:', [
                'message' => $message,
                'session' => $sessionData
            ]);
        }

        // Check if user is already registered
        $existingUser = ChatUser::where('phone_number', $message['sender'])->first();
        if ($existingUser) {
            return $this->formatTextResponse(
                "This phone number is already registered.\n\n" .
                "Reply with 00 to return to main menu."
            );
        }

        // Initialize account registration
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'ACCOUNT_REGISTRATION',
            'data' => [
                ...$sessionData['data'] ?? [],
                'step' => self::STATES['ACCOUNT_NUMBER_INPUT']
            ]
        ]);

        return $this->formatTextResponse("Please enter your account number (10 digits):");
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

        // Validate account through AuthenticationService
        $validation = $this->authService->validateAccountDetails([
            'account_number' => $accountNumber,
            'phone_number' => $message['sender']
        ]);

        if ($validation['status'] !== 'success') {
            if (config('app.debug')) {
                Log::warning('Account validation failed:', $validation);
            }

            return $this->formatTextResponse(
                "Invalid account number. Please enter a valid 10-digit account number:"
            );
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'ACCOUNT_REGISTRATION',
            'data' => [
                ...$sessionData['data'],
                'account_number' => $accountNumber,
                'step' => self::STATES['PIN_SETUP']
            ]
        ]);

        return $this->formatTextResponse(
            "Please set up your PIN for USSD access.\n" .
            "Enter a 4-digit PIN:"
        );
    }

    protected function processPinSetup(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing PIN setup');
        }

        $pin = $message['content'];

        if (!$this->validatePin($pin)) {
            if (config('app.debug')) {
                Log::warning('Invalid PIN format');
            }

            return $this->formatTextResponse("Invalid PIN. Please enter exactly 4 digits for your PIN:");
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => $sessionData['state'],
            'data' => [
                ...$sessionData['data'],
                'pin' => $pin,
                'step' => self::STATES['CONFIRM_PIN']
            ]
        ]);

        return $this->formatTextResponse("Please confirm your PIN (enter the same 4 digits again):");
    }

    protected function processConfirmPin(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing PIN confirmation');
        }

        $confirmPin = $message['content'];

        if ($confirmPin !== $sessionData['data']['pin']) {
            if (config('app.debug')) {
                Log::warning('PINs do not match');
            }

            return $this->formatTextResponse("PINs do not match. Please set up your PIN again (must be 4 digits):");
        }

        // Generate OTP through AuthenticationService
        $otpResult = $this->authService->registerWithAccount([
            'account_number' => $sessionData['data']['account_number'],
            'phone_number' => $message['sender'],
            'pin' => $sessionData['data']['pin']
        ]);

        if ($otpResult['status'] !== 'success') {
            return $this->formatTextResponse(
                "Failed to send verification code. Please try again later or contact support."
            );
        }

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => $sessionData['state'],
            'data' => [
                ...$sessionData['data'],
                'registration_reference' => $otpResult['data']['reference'],
                'step' => self::STATES['OTP_VERIFICATION']
            ]
        ]);

        return $this->formatTextResponse(
            "A verification code has been sent to your WhatsApp number.\n" .
            "Please enter the code to complete registration:"
        );
    }

    protected function processOtpVerification(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing OTP verification');
        }

        $otp = $message['content'];
        $reference = $sessionData['data']['registration_reference'];

        // Verify OTP through AuthenticationService
        $verificationResult = $this->authService->verifyRegistrationOTP(
            $reference,
            $otp,
            [
                'account_number' => $sessionData['data']['account_number'],
                'phone_number' => $message['sender']
            ]
        );

        if ($verificationResult['status'] !== 'success') {
            return $this->formatTextResponse(
                "Invalid verification code. Please try again:"
            );
        }

        // Create ChatUser record
        ChatUser::create([
            'phone_number' => $message['sender'],
            'account_number' => $sessionData['data']['account_number'],
            'pin' => Hash::make($sessionData['data']['pin']),
            'is_verified' => true,
            'last_otp_sent_at' => now()
        ]);

        // Reset session to welcome state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME'
        ]);

        if (config('app.debug')) {
            Log::info('Registration successful, returning to welcome state');
        }

        return $this->formatTextResponse(
            "Registration successful! ✅\n\n" .
            "Your account (*" . substr($sessionData['data']['account_number'], -4) . ") has been registered.\n\n" .
            "Reply with 00 to return to main menu."
        );
    }

    protected function validatePin(string $pin): bool
    {
        return preg_match('/^\d{4}$/', $pin);
    }
}
