<?php

namespace App\Http\Controllers\Chat;

use App\Services\AuthenticationService;
use App\Models\ChatUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;
use App\Adapters\WhatsAppMessageAdapter;
use App\Common\GeneralStatus;
use Carbon\Carbon;

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

    public function __construct(
        MessageAdapterInterface $messageAdapter,
        SessionManager $sessionManager,
        AuthenticationService $authService
    ) {
        parent::__construct($messageAdapter, $sessionManager);
        $this->authService = $authService;
    }

    protected function isWhatsAppChannel(): bool
    {
        return $this->messageAdapter instanceof WhatsAppMessageAdapter;
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
        $sessionDataMerged = array_merge($sessionData['data'] ?? [], [
            'step' => self::STATES['ACCOUNT_NUMBER_INPUT']
        ]);

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'ACCOUNT_REGISTRATION',
            'data' => $sessionDataMerged
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

        if ($validation['status'] !== GeneralStatus::SUCCESS) {
            if (config('app.debug')) {
                Log::warning('Account validation failed:', $validation);
            }

            return $this->formatTextResponse(
                "Invalid account number. Please enter a valid 10-digit account number:"
            );
        }

        // For WhatsApp registrations, skip PIN setup and go straight to OTP verification
        if ($this->isWhatsAppChannel()) {
            // Generate OTP through AuthenticationService
            $otpResult = $this->authService->registerWithAccount([
                'account_number' => $accountNumber,
                'phone_number' => $message['sender']
            ]);

            if ($otpResult['status'] !== GeneralStatus::SUCCESS) {
                return $this->formatTextResponse(
                    "Failed to send verification code. Please try again later or contact support."
                );
            }

            $sessionDataMerged = array_merge($sessionData['data'] ?? [], [
                'account_number' => $accountNumber,
                'registration_reference' => $otpResult['data']['reference'],
                'step' => self::STATES['OTP_VERIFICATION']
            ]);

            // Set both state and step to OTP_VERIFICATION for consistency
            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'OTP_VERIFICATION',
                'data' => $sessionDataMerged
            ]);

            return $this->formatTextResponse(
                "Welcome back to Social Banking!\n\n" .
                "Please enter the 6-digit OTP sent to your number via SMS.\n\n" .
                "Test OTP: " . $otpResult['data']['otp']
            );
        }

        // For USSD registrations, continue with PIN setup
        $sessionDataMerged = array_merge($sessionData['data'] ?? [], [
            'account_number' => $accountNumber,
            'step' => self::STATES['PIN_SETUP']
        ]);

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'ACCOUNT_REGISTRATION',
            'data' => $sessionDataMerged
        ]);

        return $this->formatTextResponse(
            "Please set up your PIN for USSD access.\n" .
            "Enter a 4-digit PIN:"
        );
    }

    protected function processPinSetup(array $message, array $sessionData): array
    {
        // Skip PIN setup for WhatsApp registrations
        if ($this->isWhatsAppChannel()) {
            return $this->processOtpVerification($message, $sessionData);
        }

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

        $sessionDataMerged = array_merge($sessionData['data'] ?? [], [
            'pin' => $pin,
            'step' => self::STATES['CONFIRM_PIN']
        ]);

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => $sessionData['state'],
            'data' => $sessionDataMerged
        ]);

        return $this->formatTextResponse("Please confirm your PIN (enter the same 4 digits again):");
    }

    protected function processConfirmPin(array $message, array $sessionData): array
    {
        // Skip PIN confirmation for WhatsApp registrations
        if ($this->isWhatsAppChannel()) {
            return $this->processOtpVerification($message, $sessionData);
        }

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

        if ($otpResult['status'] !== GeneralStatus::SUCCESS) {
            return $this->formatTextResponse(
                "Failed to send verification code. Please try again later or contact support."
            );
        }

        $sessionDataMerged = array_merge($sessionData['data'] ?? [], [
            'registration_reference' => $otpResult['data']['reference'],
            'step' => self::STATES['OTP_VERIFICATION']
        ]);

        // Set both state and step to OTP_VERIFICATION for consistency
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'OTP_VERIFICATION',
            'data' => $sessionDataMerged
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

        if ($verificationResult['status'] !== GeneralStatus::SUCCESS) {
            return $this->formatTextResponse(
                "Invalid verification code. Please try again:"
            );
        }

        // Prepare user data
        $userData = [
            'phone_number' => $message['sender'],
            'account_number' => $sessionData['data']['account_number'],
            'is_verified' => true,
            'last_otp_sent_at' => Carbon::now()
        ];

        // Only include PIN for USSD registrations
        if (!$this->isWhatsAppChannel() && isset($sessionData['data']['pin'])) {
            $userData['pin'] = Hash::make($sessionData['data']['pin']);
        }

        try {
            // Use updateOrCreate to handle both new and existing users
            ChatUser::updateOrCreate(
                ['phone_number' => $message['sender']], // The unique identifier
                $userData // The values to update or create with
            );

            // Set session state to REGISTRATION_SUCCESS
            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'REGISTRATION_SUCCESS',
                'data' => [
                    'account_number' => $sessionData['data']['account_number']
                ]
            ]);

            if (config('app.debug')) {
                Log::info('Registration successful, state set to REGISTRATION_SUCCESS');
            }

            return $this->formatTextResponse(
                "Registration successful! ✅\n\n" .
                "Your account (*" . substr($sessionData['data']['account_number'], -4) . ") has been registered.\n\n" .
                "Reply with 00 to return to main menu."
            );
        } catch (\Exception $e) {
            Log::error('Failed to save chat user:', ['error' => $e->getMessage()]);
            return $this->formatTextResponse(
                "Registration failed. Please try again later or contact support."
            );
        }
    }

    protected function validatePin(string $pin): bool
    {
        return preg_match('/^\d{4}$/', $pin);
    }
}
