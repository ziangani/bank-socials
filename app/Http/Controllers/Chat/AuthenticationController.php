<?php

namespace App\Http\Controllers\Chat;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\ChatUser;
use App\Adapters\WhatsAppMessageAdapter;

class AuthenticationController extends BaseMessageController
{
    /**
     * Initiate OTP verification
     */
    public function initiateOTPVerification(array $message): array
    {
        // Generate and send OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in session
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'OTP_VERIFICATION',
            'data' => [
                'otp' => $otp,
                'otp_generated_at' => now()
            ]
        ]);

        // Send OTP via WhatsApp
        $otpMessage = "Welcome back to Social Banking!\n\nPlease enter the 6-digit OTP sent to your number via SMS.\n\nTest OTP: $otp";
//        $this->messageAdapter->sendMessage($message['sender'], $otpMessage);

        return [
            'message' => $otpMessage,
            'type' => 'text'
        ];
    }

    /**
     * Process OTP verification
     */
    public function processOTPVerification(array $message, array $sessionData): array
    {
        $inputOtp = $message['content'];
        $storedOtp = $sessionData['data']['otp'] ?? null;
        $otpGeneratedAt = $sessionData['data']['otp_generated_at'] ?? null;

        // Verify OTP
        if (!$storedOtp || !$otpGeneratedAt) {
            return $this->initiateOTPVerification($message);
        }

        // Check OTP expiry (5 minutes)
        if (Carbon::parse($otpGeneratedAt)->addMinutes(5)->isPast()) {
            return [
                'message' => "OTP has expired. Please request a new one.",
                'type' => 'text'
            ];
        }

        if ($inputOtp !== $storedOtp) {
            return [
                'message' => "Invalid OTP. Please try again or type 00 to return to main menu.",
                'type' => 'text'
            ];
        }

        // Mark session as authenticated
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME',
            'data' => [
                ...$sessionData['data'],
                'otp_verified' => true,
                'authenticated_at' => now()
            ]
        ]);

        return app(MenuController::class)->showMainMenu($message);
    }

    /**
     * Check if user is authenticated in current session
     */
    public function isUserAuthenticated(string $sessionId): bool
    {
        $sessionData = $this->messageAdapter->getSessionData($sessionId);
        if (!$sessionData) {
            return false;
        }

        $authenticatedAt = $sessionData['data']['authenticated_at'] ?? null;
        if (!$authenticatedAt) {
            return false;
        }

        // Authentication valid for 30 minutes
        return !Carbon::parse($authenticatedAt)->addMinutes(30)->isPast();
    }

    /**
     * Handle user logout (000 command)
     */
    public function handleLogout(array $parsedMessage): \Illuminate\Http\JsonResponse
    {
        try {
            // End the current session
            if ($parsedMessage['session_id']) {
                $this->messageAdapter->endSession($parsedMessage['session_id']);
            }

            // Create new session in WELCOME state
            $this->messageAdapter->createSession([
                'session_id' => $parsedMessage['session_id'],
                'sender' => $parsedMessage['sender'],
                'state' => 'WELCOME',
                'data' => [
                    'authenticated_at' => null,
                    'otp_verified' => false,
                    'last_message' => $parsedMessage['content']
                ]
            ]);

            // Check if user is registered
            $chatUser = ChatUser::where('phone_number', $parsedMessage['sender'])->first();

            // Prepare response based on channel and registration status
            if ($this->messageAdapter instanceof WhatsAppMessageAdapter) {
                if (!$chatUser) {
                    $response = [
                        'message' => "Welcome to Social Banking!\n\nYou are not registered. Please register to continue.",
                        'type' => 'text'
                    ];
                } else {
                    // For WhatsApp, initiate OTP verification and wrap the response in JsonResponse
                    $otpResponse = $this->initiateOTPVerification($parsedMessage);
                    return response()->json($this->messageAdapter->formatOutgoingMessage($otpResponse));
                }
            } else {
                // For USSD
                if (!$chatUser) {
                    $response = [
                        'message' => "Welcome to Social Banking\n1. Register\n2. Help",
                        'type' => 'text'
                    ];
                } else {
                    $response = [
                        'message' => "Welcome to Social Banking\nPlease enter your PIN to continue:",
                        'type' => 'text'
                    ];
                }
            }

            // Send response via message adapter
            $options = ['message_id' => $parsedMessage['message_id']];
            $this->messageAdapter->sendMessage(
                $parsedMessage['sender'],
                $response['message'],
                $options
            );

            // Format response for channel
            $formattedResponse = $this->messageAdapter->formatOutgoingMessage($response);
            return response()->json($formattedResponse);

        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process logout'
            ], 500);
        }
    }
}
