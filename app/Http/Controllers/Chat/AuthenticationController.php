<?php

namespace App\Http\Controllers\Chat;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
        $otpMessage = "Your OTP for WhatsApp Banking is: {$otp}\n\nThis code will expire in 5 minutes.";
        $this->messageAdapter->sendMessage($message['sender'], $otpMessage);

        return [
            'message' => "Please enter the 6-digit OTP sent to your WhatsApp number to continue.",
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
     * Handle user logout
     */
    public function handleLogout(array $parsedMessage): \Illuminate\Http\JsonResponse
    {
        try {
            // End the current session
            if ($parsedMessage['session_id']) {
                $this->messageAdapter->endSession($parsedMessage['session_id']);
            }

            // Clear any stored authentication state
            $this->messageAdapter->createSession([
                'session_id' => $parsedMessage['session_id'],
                'sender' => $parsedMessage['sender'],
                'state' => 'WELCOME',
                'data' => [
                    'authenticated_at' => null,
                    'otp_verified' => false
                ]
            ]);

            $response = [
                'message' => "You have been logged out successfully.\n\nThank you for using our service. Reply with 'Hi' to start a new session.",
                'type' => 'text',
                'end_session' => true
            ];

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
