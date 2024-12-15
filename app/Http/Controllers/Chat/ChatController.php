<?php

namespace App\Http\Controllers\Chat;

use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ChatUser;
use App\Adapters\WhatsAppMessageAdapter;

class ChatController extends BaseMessageController
{
    protected StateController $stateController;
    protected AuthenticationController $authenticationController;
    protected MenuController $menuController;
    protected SessionController $sessionController;
    protected RegistrationController $registrationController;

    public function __construct(
        MessageAdapterInterface $messageAdapter,
        SessionManager $sessionManager,
        StateController $stateController,
        AuthenticationController $authenticationController,
        MenuController $menuController,
        SessionController $sessionController,
        RegistrationController $registrationController
    ) {
        parent::__construct($messageAdapter, $sessionManager);
        $this->stateController = $stateController;
        $this->authenticationController = $authenticationController;
        $this->menuController = $menuController;
        $this->sessionController = $sessionController;
        $this->registrationController = $registrationController;
    }

    /**
     * Process incoming chat message
     */
    public function processMessage(Request $request)
    {
        try {
            // Parse incoming message
            $parsedMessage = $this->messageAdapter->parseIncomingMessage($request->all());

            if (config('app.debug')) {
                Log::info('Parsed message:', [
                    'message' => $parsedMessage,
                    'raw_request' => $request->all()
                ]);
            }

            if ($parsedMessage['message_id'] == null) {
                return response()->json(['status' => 'error', 'message' => 'Message cannot be processed']);
            }

            // Mark message as read
            $this->messageAdapter->markMessageAsRead($parsedMessage['sender'], $parsedMessage['message_id']);

            // Check if message already processed
            if ($this->messageAdapter->isMessageProcessed($parsedMessage['message_id'])) {
                return response()->json(['status' => 'already_processed']);
            }

            // Check for exit command '000'
            if ($parsedMessage['content'] === '000') {
                return $this->authenticationController->handleLogout($parsedMessage);
            }

            // Check for return to main menu command '00'
            if ($parsedMessage['content'] === '00') {
                return $this->sessionController->handleReturnToMainMenu($parsedMessage);
            }
            
            // Get or create session
            $sessionData = $this->messageAdapter->getSessionData($parsedMessage['session_id']);

            if (config('app.debug')) {
                Log::info('Session data:', [
                    'session_id' => $parsedMessage['session_id'],
                    'data' => $sessionData
                ]);
            }

            if (!$sessionData) {
                // New session - show welcome message
                $sessionId = $this->messageAdapter->createSession([
                    'session_id' => $parsedMessage['session_id'],
                    'sender' => $parsedMessage['sender'],
                    'state' => 'WELCOME',
                    'data' => [
                        'last_message' => $parsedMessage['content']
                    ],
                ]);

                $response = $this->handleWelcome($parsedMessage);
            } else {
                // Process based on current state
                $response = $this->stateController->processState(
                    $sessionData['state'],
                    $parsedMessage,
                    $sessionData
                );
            }

            // Mark message as processed
            $this->messageAdapter->markMessageAsProcessed($parsedMessage['message_id']);

            // Send response via message adapter if not already sent
            if (!isset($response['already_sent'])) {
                $options = [];
                if ($response['type'] === 'interactive')
                    $options['buttons'] = $this->messageAdapter->formatButtons($response['buttons']);

                $options['message_id'] = $parsedMessage['message_id'];
                $this->messageAdapter->sendMessage(
                    $parsedMessage['sender'],
                    $response['message'],
                    $options
                );

                if (config('app.debug')) {
                    Log::info('Response sent:', [
                        'response' => $response,
                        'options' => $options
                    ]);
                }
            }

            // Format response for channel
            $formattedResponse = $this->messageAdapter->formatOutgoingMessage($response);

            return response()->json($formattedResponse);

        } catch (\Exception $e) {
            Log::error('Chat processing error: ' . $e->getMessage());
            Log::error('Chat processing error trace: ' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process message'
            ], 500);
        }
    }

    /**
     * Handle welcome state
     */
    protected function handleWelcome(array $message): array
    {
        $chatUser = ChatUser::where('phone_number', $message['sender'])->first();
        
        if (!$chatUser) {
            if ($this->messageAdapter instanceof WhatsAppMessageAdapter) {
                return [
                    'message' => "Welcome to Social Banking!\n\nYou are not registered. Please register to continue.",
                    'type' => 'text'
                ];
            } else {
                // For USSD
                return [
                    'message' => "Welcome to Social Banking\n1. Register\n2. Help",
                    'type' => 'text'
                ];
            }
        }

        // User is registered, check if authenticated
        if (!isset($message['session_id']) || !$this->authenticationController->isUserAuthenticated($message['session_id'])) {
            if ($this->messageAdapter instanceof WhatsAppMessageAdapter) {
                // For WhatsApp, initiate OTP verification
                return $this->authenticationController->initiateOTPVerification($message);
            } else {
                // For USSD, request PIN
                return [
                    'message' => "Welcome to Social Banking\nPlease enter your PIN to continue:",
                    'type' => 'text'
                ];
            }
        }

        return $this->menuController->showMainMenu($message);
    }
}
