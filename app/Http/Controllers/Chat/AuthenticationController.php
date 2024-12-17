<?php

namespace App\Http\Controllers\Chat;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\ChatUser;
use App\Models\ChatUserLogin;
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
                'otp_generated_at' => now(),
                'is_authentication' => true // Flag to distinguish from registration OTP
            ]
        ]);

        // Return response only, let ChatController handle sending
        return [
            'message' => "Welcome back to Social Banking!\n\nPlease enter the 6-digit OTP sent to your number via SMS.\n\nTest OTP: $otp",
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
        $isAuthentication = $sessionData['data']['is_authentication'] ?? false;

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

        // Update session state
        $this->messageAdapter->updateSession($message['session_id'], [
            'state' => 'WELCOME',
            'data' => []
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
