<?php

namespace App\Http\Controllers\Chat;

use App\Services\AuthenticationService;
use App\Models\ChatUser;
use App\Models\ChatUserLogin;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;
use App\Adapters\WhatsAppMessageAdapter;
use App\Common\GeneralStatus;
use App\Integrations\ESB;
use Carbon\Carbon;
use Illuminate\Support\Str;

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
    protected ESB $esb;

    public function __construct(
        MessageAdapterInterface $messageAdapter,
        SessionManager $sessionManager,
        AuthenticationService $authService,
        ESB $esb
    ) {
        parent::__construct($messageAdapter, $sessionManager);
        $this->authService = $authService;
        $this->esb = $esb;
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

        // 1. Validate account through ESB
        $validation = $this->esb->getAccountDetailsAndBalance($accountNumber);

        if (!$validation['status']) {
            if (config('app.debug')) {
                Log::warning('Account validation failed:', $validation);
            }

            $errorMessage = $validation['message'] ?? 'Invalid account number';
            return $this->formatTextResponse(
                "{$errorMessage}\n\nPlease enter a valid 10-digit account number:"
            );
        }

        // 2. Generate OTP through ESB
        $otpResult = $this->esb->generateOTP($message['sender']);
        if (!$otpResult['status']) {
            return $this->formatTextResponse(
                "Failed to send verification code. Please try again later or contact support."
            );
        }

        // Store account and OTP details
        $sessionDataMerged = array_merge($sessionData['data'] ?? [], [
            'account_number' => $accountNumber,
            'account_details' => $validation['data'],
            'otp' => $otpResult['data']['otp'],
            'otp_generated_at' => now(),
            'expires_at' => $otpResult['data']['expires_at'],
            'registration_reference' => Str::random(16)
        ]);

        // For WhatsApp, go straight to OTP verification
        if ($this->isWhatsAppChannel()) {
            $sessionDataMerged['step'] = self::STATES['OTP_VERIFICATION'];
            
            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'OTP_VERIFICATION',
                'data' => $sessionDataMerged
            ]);

            return $this->formatTextResponse(
                "Let's help you register for Social Banking!\n\n" .
                "Please enter the 6-digit OTP sent to your number via SMS.\n\n" 
            );
        }

        // For USSD, continue with PIN setup
        $sessionDataMerged['step'] = self::STATES['PIN_SETUP'];

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

            // Update session to ensure state consistency
            $sessionDataMerged = array_merge($sessionData['data'] ?? [], [
                'step' => self::STATES['PIN_SETUP']
            ]);

            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'ACCOUNT_REGISTRATION',
                'data' => $sessionDataMerged
            ]);

            return $this->formatTextResponse("Invalid PIN. Please enter exactly 4 digits for your PIN:");
        }

        $sessionDataMerged = array_merge($sessionData['data'] ?? [], [
            'pin' => $pin,
            'step' => self::STATES['CONFIRM_PIN']
        ]);

        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'ACCOUNT_REGISTRATION',
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

            // Update session to reset PIN setup with state consistency
            $sessionDataMerged = array_merge($sessionData['data'] ?? [], [
                'step' => self::STATES['PIN_SETUP'],
                'pin' => null // Clear the invalid PIN
            ]);

            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'ACCOUNT_REGISTRATION',
                'data' => $sessionDataMerged
            ]);

            return $this->formatTextResponse("PINs do not match. Please set up your PIN again (must be 4 digits):");
        }

        // Generate OTP through ESB
        $otpResult = $this->esb->generateOTP($message['sender']);
        if (!$otpResult['status']) {
            return $this->formatTextResponse(
                "Failed to send verification code. Please try again later or contact support."
            );
        }

        $sessionDataMerged = array_merge($sessionData['data'] ?? [], [
            'otp' => $otpResult['data']['otp'],
            'otp_generated_at' => now(),
            'expires_at' => $otpResult['data']['expires_at'],
            'step' => self::STATES['OTP_VERIFICATION']
        ]);

        // Set both state and step to OTP_VERIFICATION for consistency
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'OTP_VERIFICATION',
            'data' => $sessionDataMerged
        ]);

        // Use appropriate message based on channel
        $message = $this->isWhatsAppChannel() 
            ? "A verification code has been sent to your WhatsApp number.\n"
            : "A verification code has been sent to your phone via SMS.\n";

        return $this->formatTextResponse(
            $message .
            "Please enter the code to complete registration:"
        );
    }

    protected function processOtpVerification(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing OTP verification');
        }

        $inputOtp = $message['content'];
        $storedOtp = $sessionData['data']['otp'] ?? null;
        $otpGeneratedAt = $sessionData['data']['otp_generated_at'] ?? null;

        // Check if OTP exists and hasn't expired
        if (!$storedOtp || !$otpGeneratedAt || Carbon::parse($sessionData['data']['expires_at'])->isPast()) {
            // Generate new OTP through ESB
            $otpResult = $this->esb->generateOTP($message['sender']);
            if (!$otpResult['status']) {
                return $this->formatTextResponse(
                    "Failed to send verification code. Please try again later or contact support."
                );
            }

            // Update session with new OTP
            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'OTP_VERIFICATION',
                'data' => array_merge($sessionData['data'], [
                    'otp' => $otpResult['data']['otp'],
                    'otp_generated_at' => now(),
                    'expires_at' => $otpResult['data']['expires_at']
                ])
            ]);

            return $this->formatTextResponse(
                "A new verification code has been sent to your number via SMS.\n\n" .
                "Please enter the code to complete registration:"
            );
        }

        // Verify OTP and create user through AuthenticationService
        $result = $this->authService->verifyRegistrationOTP(
            $sessionData['data']['registration_reference'] ?? '',
            $inputOtp,
            [
                'otp' => $storedOtp,
                'expires_at' => $sessionData['data']['expires_at'],
                'phone_number' => $message['sender'],
                'account_number' => $sessionData['data']['account_number'],
                'account_details' => $sessionData['data']['account_details'],
                'pin' => !$this->isWhatsAppChannel() ? $sessionData['data']['pin'] : null
            ]
        );

        if ($result['status'] !== GeneralStatus::SUCCESS) {
            return $this->formatTextResponse(
                $result['message'] ?? "Invalid verification code. Please try again:"
            );
        }

        // Set session state to WELCOME and clear registration data
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME',
            'data' => []
        ]);

        if (config('app.debug')) {
            Log::info('Registration successful, showing main menu');
        }

        // Return success message and show main menu options
        return app(MenuController::class)->showMainMenu($message);
    }

    protected function validatePin(string $pin): bool
    {
        return preg_match('/^\d{4}$/', $pin);
    }
}
