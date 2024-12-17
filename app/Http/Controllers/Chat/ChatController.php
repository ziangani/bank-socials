<?php

namespace App\Http\Controllers\Chat;

use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ChatUser;
use App\Models\ChatUserLogin;
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

            // Get chat user and active login
            $chatUser = ChatUser::where('phone_number', $parsedMessage['sender'])->first();
            $activeLogin = ChatUserLogin::getActiveLogin($parsedMessage['sender']);
            $isAuthenticated = $activeLogin && $activeLogin->isValid();

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

                $response = $this->handleWelcome($parsedMessage, $chatUser, $isAuthenticated);
            } else {
                // Check if this is a return to main menu command
                if ($parsedMessage['content'] === '00') {
                    // Only allow return to main menu if user is registered
                    if ($chatUser) {
                        // Update session state to WELCOME
                        $this->messageAdapter->updateSession($parsedMessage['session_id'], [
                            'state' => 'WELCOME',
                            'data' => []
                        ]);
                        
                        // Show appropriate menu based on authentication
                        if ($isAuthenticated) {
                            $response = $this->menuController->showMainMenu($parsedMessage);
                        } else {
                            $response = $this->authenticationController->initiateOTPVerification($parsedMessage);
                        }
                    } else {
                        $response = $this->menuController->showUnregisteredMenu($parsedMessage);
                    }
                } else {
                    // Check if authentication is required for current state
                    $requiresAuth = !in_array($sessionData['state'], [
                        'WELCOME', 
                        'REGISTRATION_INIT', 
                        'ACCOUNT_REGISTRATION', 
                        'OTP_VERIFICATION',
                        'HELP'
                    ]);
                    
                    if ($requiresAuth && !$isAuthenticated) {
                        // User needs to authenticate
                        $this->messageAdapter->updateSession($parsedMessage['session_id'], [
                            'state' => 'OTP_VERIFICATION'
                        ]);
                        $response = $this->authenticationController->initiateOTPVerification($parsedMessage);
                    } else {
                        // Add authenticated user to session data if available
                        if ($isAuthenticated) {
                            $sessionData['authenticated_user'] = $activeLogin->chatUser;
                        }

                        // Process based on current state
                        $response = $this->stateController->processState(
                            $sessionData['state'],
                            $parsedMessage,
                            $sessionData
                        );
                    }
                }
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
    protected function handleWelcome(array $message, ?ChatUser $chatUser, bool $isAuthenticated): array
    {
        if (!$chatUser) {
            if ($this->messageAdapter instanceof WhatsAppMessageAdapter) {
                return $this->menuController->showUnregisteredMenu($message);
            } else {
                // For USSD
                return [
                    'message' => "Welcome to Social Banking\n1. Register\n2. Help",
                    'type' => 'text'
                ];
            }
        }

        // User is registered, check if authenticated
        if (!$isAuthenticated) {
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
