<?php

namespace App\Http\Controllers\Chat;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;

class SessionController extends BaseMessageController
{
    protected MenuController $menuController;

    public function __construct(
        MessageAdapterInterface $messageAdapter,
        SessionManager $sessionManager,
        MenuController $menuController
    ) {
        parent::__construct($messageAdapter, $sessionManager);
        $this->menuController = $menuController;
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
            // End current session and create a new clean session
            if ($parsedMessage['session_id']) {
                // First end the current session
                $this->messageAdapter->endSession($parsedMessage['session_id']);
                
                // Create a new clean session
                $this->messageAdapter->createSession([
                    'session_id' => $parsedMessage['session_id'],
                    'sender' => $parsedMessage['sender'],
                    'state' => 'WELCOME',
                    'data' => [
                        'session_id' => $parsedMessage['session_id'],
                        'message_id' => $parsedMessage['message_id'],
                        'sender' => $parsedMessage['sender'],
                        'last_message' => '00'
                    ]
                ]);
            }

            // Get welcome message response
            $response = $this->menuController->showMainMenu($parsedMessage);

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
    }
}