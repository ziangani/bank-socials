<?php

namespace App\Http\Controllers\Chat;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Models\ChatUser;
use App\Models\ChatUserLogin;
use App\Adapters\WhatsAppMessageAdapter;
use App\Integrations\ESB;

class AuthenticationController extends BaseMessageController
{
    /**
     * Process PIN verification for USSD
     */
    public function processPINVerification(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing PIN verification:', [
                'sender' => $message['sender']
            ]);
        }

        // Get chat user
        $chatUser = ChatUser::where('phone_number', $message['sender'])->first();
        if (!$chatUser) {
            return [
                'message' => "User not found. Please register first.",
                'type' => 'text'
            ];
        }

        // Verify PIN
        if (!Hash::check($message['content'], $chatUser->pin)) {
            return [
                'message' => "Invalid PIN. Please try again or type 00 to return to main menu.",
                'type' => 'text'
            ];
        }

        // Create new login record
        ChatUserLogin::createLogin($chatUser, $message['session_id']);

        // Update session state with authenticated user
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME',
            'data' => [],
            'authenticated_user' => [
                'App\\Models\\ChatUser' => $chatUser->toArray()
            ]
        ]);

        return app(MenuController::class)->showMainMenu($message);
    }

    /**
     * Initiate OTP verification
     */
    public function initiateOTPVerification(array $message): array
    {
        try {
            // Generate OTP via ESB
            $esb = new ESB();
            $result = $esb->generateOTP($message['sender']);

            if (!$result['status']) {
                Log::error('Failed to generate OTP:', [
                    'phone' => $message['sender'],
                    'error' => $result['message']
                ]);
                return [
                    'message' => "Sorry, we couldn't generate an OTP at this time. Please try again later.",
                    'type' => 'text'
                ];
            }

            // Store OTP from ESB response in session
            $this->messageAdapter->updateSession($message['session_id'], [
                'state' => 'OTP_VERIFICATION',
                'data' => [
                    'otp' => $result['data']['otp'],
                    'otp_generated_at' => now(),
                    'expires_at' => $result['data']['expires_at'],
                    'is_authentication' => true // Flag to distinguish from registration OTP
                ]
            ]);

            // Return response only, let ChatController handle sending
            return [
                'message' => "Welcome back to Social Banking!\n\nPlease enter the 6-digit OTP sent to your number via SMS.",
                'type' => 'text'
            ];
        } catch (\Exception $e) {
            Log::error('OTP generation error: ' . $e->getMessage());
            return [
                'message' => "Sorry, we couldn't generate an OTP at this time. Please try again later.",
                'type' => 'text'
            ];
        }
    }

    /**
     * Process OTP verification
     */
    public function processOTPVerification(array $message, array $sessionData): array
    {
        $inputOtp = $message['content'];
        $storedOtp = $sessionData['data']['otp'] ?? null;
        $otpGeneratedAt = $sessionData['data']['otp_generated_at'] ?? null;
        $isAuthentication = $sessionData['data']['is_authentication'] ?? false;

        // Verify OTP
        if (!$storedOtp || !$otpGeneratedAt) {
            return $this->initiateOTPVerification($message);
        }

        // Check OTP expiry using ESB's expires_at
        if (Carbon::parse($sessionData['data']['expires_at'])->isPast()) {
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

        // Get chat user
        $chatUser = ChatUser::where('phone_number', $message['sender'])->first();
        if (!$chatUser) {
            return [
                'message' => "User not found. Please register first.",
                'type' => 'text'
            ];
        }

        // Create new login record
        ChatUserLogin::createLogin($chatUser, $message['session_id']);

        // Update session state with authenticated user
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME',
            'data' => [],
            'authenticated_user' => [
                'App\\Models\\ChatUser' => $chatUser->toArray()
            ]
        ]);

        return app(MenuController::class)->showMainMenu($message);
    }

    /**
     * Check if user is authenticated in current session
     */
    public function isUserAuthenticated(string $phoneNumber): bool
    {
        $activeLogin = ChatUserLogin::getActiveLogin($phoneNumber);
        return $activeLogin && $activeLogin->isValid();
    }

    /**
     * Handle user logout (000 command)
     */
    public function handleLogout(array $parsedMessage): \Illuminate\Http\JsonResponse
    {
        try {
            // Deactivate any active logins
            $activeLogin = ChatUserLogin::getActiveLogin($parsedMessage['sender']);
            if ($activeLogin) {
                $activeLogin->deactivate();
            }

            // End the current session
            if ($parsedMessage['session_id']) {
                $this->messageAdapter->endSession($parsedMessage['session_id']);
            }

            // Send goodbye message
            $response = [
                'message' => "Thank you for using Social Banking. Goodbye! 👋",
                'type' => 'text',
                'end_session' => true
            ];
            
            // Send response via message adapter
            $this->messageAdapter->sendMessage(
                $parsedMessage['sender'],
                $response['message'],
                ['message_id' => $parsedMessage['message_id']]
            );
            
            // Format and return response
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
