<?php

namespace App\Http\Controllers\Chat;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;
use App\Models\ChatUserLogin;
use App\Models\ChatUser;

class SessionController extends BaseMessageController
{
    protected MenuController $menuController;
    protected AuthenticationController $authenticationController;

    public function __construct(
        MessageAdapterInterface $messageAdapter,
        SessionManager $sessionManager,
        MenuController $menuController,
        AuthenticationController $authenticationController
    ) {
        parent::__construct($messageAdapter, $sessionManager);
        $this->menuController = $menuController;
        $this->authenticationController = $authenticationController;
    }

    /**
     * Check if session is expired
     */
    public function isSessionExpired(array $sessionData): bool
    {
        $timeout = config('whatsapp.session_timeout', 600);
        $lastActivity = Carbon::parse($sessionData['updated_at']);
        
        return $lastActivity->addSeconds($timeout)->isPast();
    }

    /**
     * Handle session expiry
     */
    public function handleSessionExpiry(array $message): array
    {
        // End the session
        if ($message['session_id']) {
            // Deactivate any active logins for this session
            ChatUserLogin::where('session_id', $message['session_id'])
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $this->messageAdapter->endSession($message['session_id']);
        }

        return [
            'message' => config('whatsapp.session_expiry_message'),
            'type' => 'text',
            'end_session' => true
        ];
    }

    /**
     * Handle return to main menu
     */
    public function handleReturnToMainMenu(array $parsedMessage): \Illuminate\Http\JsonResponse
    {
        try {
            // Check user registration and authentication status
            $chatUser = ChatUser::where('phone_number', $parsedMessage['sender'])->first();
            $activeLogin = ChatUserLogin::getActiveLogin($parsedMessage['sender']);
            $isAuthenticated = $activeLogin && $activeLogin->isValid();

            // End current session
            if ($parsedMessage['session_id']) {
                // If not authenticated, deactivate any existing logins
                if (!$isAuthenticated) {
                    ChatUserLogin::where('session_id', $parsedMessage['session_id'])
                        ->where('is_active', true)
                        ->update(['is_active' => false]);
                }

                $this->messageAdapter->endSession($parsedMessage['session_id']);
                
                // Create a new clean session
                $this->messageAdapter->createSession([
                    'session_id' => $parsedMessage['session_id'],
                    'sender' => $parsedMessage['sender'],
                    'state' => 'WELCOME',
                    'data' => [
                        'session_id' => $parsedMessage['session_id'],
                        'message_id' => $parsedMessage['message_id'],
                        'sender' => $parsedMessage['sender']
                    ]
                ]);
            }

            // Determine appropriate response based on user status
            if (!$chatUser) {
                // Unregistered user
                $response = $this->menuController->showUnregisteredMenu($parsedMessage);
            } else if (!$isAuthenticated) {
                // Registered but not authenticated
                $response = $this->authenticationController->initiateOTPVerification($parsedMessage);
            } else {
                // Registered and authenticated
                $response = $this->menuController->showMainMenu($parsedMessage);
            }

            // Send response via message adapter
            $options = [];
            if ($response['type'] === 'interactive') {
                $options['buttons'] = $this->messageAdapter->formatButtons($response['buttons']);
            }
            $options['message_id'] = $parsedMessage['message_id'];
            
            $this->messageAdapter->sendMessage(
                $parsedMessage['sender'],
                $response['message'],
                $options
            );

            // Format response for channel
            $formattedResponse = $this->messageAdapter->formatOutgoingMessage($response);
            return response()->json($formattedResponse);
        } catch (\Exception $e) {
            Log::error('Return to main menu error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to return to main menu'
            ], 500);
        }
    }

    /**
     * Handle exit command
     */
    public function handleExitCommand(array $parsedMessage): \Illuminate\Http\JsonResponse
    {
        try {
            // Deactivate any active logins
            ChatUserLogin::where('session_id', $parsedMessage['session_id'])
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // End the session
            if ($parsedMessage['session_id']) {
                $this->messageAdapter->endSession($parsedMessage['session_id']);
            }

            $response = [
                'message' => "Thank you for using our service. If you need further assistance, simply reply with 'Hi'.\n\nGoodbye 👋!",
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
            Log::error('Exit command error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process exit command'
            ], 500);
        }
    }
}
