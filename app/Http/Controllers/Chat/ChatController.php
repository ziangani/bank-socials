<?php

namespace App\Http\Controllers\Chat;

use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ChatUser;
use Carbon\Carbon;

class ChatController extends BaseMessageController
{
    protected RegistrationController $registrationController;
    protected TransferController $transferController;
    protected BillPaymentController $billPaymentController;
    protected AccountServicesController $accountServicesController;

    public function __construct(
        MessageAdapterInterface   $messageAdapter,
        SessionManager            $sessionManager,
        RegistrationController    $registrationController,
        TransferController        $transferController,
        BillPaymentController     $billPaymentController,
        AccountServicesController $accountServicesController
    )
    {
        parent::__construct($messageAdapter, $sessionManager);
        $this->registrationController = $registrationController;
        $this->transferController = $transferController;
        $this->billPaymentController = $billPaymentController;
        $this->accountServicesController = $accountServicesController;
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
                Log::info('Parsed WhatsApp message:', [
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
                return $this->handleLogout($parsedMessage);
            }

            // Check for return to main menu command '00'
            if ($parsedMessage['content'] === '00') {
                return $this->handleReturnToMainMenu($parsedMessage);
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
                $data = $parsedMessage;
                $data['last_message'] = $parsedMessage['content'];
                // New session - show welcome message
                $sessionId = $this->messageAdapter->createSession([
                    'session_id' => $parsedMessage['session_id'],
                    'sender' => $parsedMessage['sender'],
                    'state' => 'WELCOME',
                    'data' => $data,
                ]);

                $response = $this->handleWelcome($parsedMessage);
            } else {
                // Process based on current state
                $response = $this->processState(
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
     * Handle user logout
     */
    protected function handleLogout(array $parsedMessage): \Illuminate\Http\JsonResponse
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

        protected function handleReturnToMainMenu(array $parsedMessage): \Illuminate\Http\JsonResponse
    {
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
        $response = $this->handleWelcome($parsedMessage);

        // Format response for channel
        $formattedResponse = $this->messageAdapter->formatOutgoingMessage($response);
        return response()->json($formattedResponse);
    }
    
 /**
     * Handle exit command
     */
    protected function handleExitCommand(array $parsedMessage): \Illuminate\Http\JsonResponse
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

    /**
     * Handle welcome state
     */
    protected function handleWelcome(array $message): array
    {
        $chatUser = ChatUser::where('phone_number', $message['sender'])->first();
        
        if (!$chatUser) {
            return $this->showUnregisteredMenu($message);
        }

        // Check if user needs OTP verification
        if (!isset($message['session_id']) || !$this->isUserAuthenticated($message['session_id'])) {
            return $this->initiateOTPVerification($message);
        }

        return $this->showMainMenu($message);
    }

    /**
     * Show main menu for authenticated users
     */
    protected function showMainMenu(array $message): array
    {
        $contactName = $message['contact_name'] ?? 'there';
        $welcomeText = "Hello {$contactName}! 👋\n\nPlease select an option from the menu below:\n";

        $mainMenu = $this->getMenuConfig('main');
        
        // Add menu options to the message text
        foreach ($mainMenu as $key => $option) {
            $welcomeText .= "{$key}. {$option['text']}\n";
        }

        $welcomeText .= "\nTo return to this menu at any time, reply with 00.\nTo exit at any time, reply with 000.";

        return [
            'message' => $welcomeText,
            'type' => 'text',
            'already_sent' => true // Mark as already sent to avoid duplicate
        ];
    }

    /**
     * Process message based on current state
     */
    protected function processState(string $state, array $message, array $sessionData): array
    {
        // Check session timeout first
        if ($this->isSessionExpired($sessionData)) {
            return $this->handleSessionExpiry($message);
        }

        // Check if user is registered
        $chatUser = ChatUser::where('phone_number', $message['sender'])->first();
        
        if (!$chatUser && !in_array($state, ['WELCOME', 'REGISTRATION_INIT', 'ACCOUNT_REGISTRATION', 'HELP'])) {
            return $this->showUnregisteredMenu($message);
        }

        if (config('app.debug')) {
            Log::info('Processing state:', [
                'state' => $state,
                'message' => $message,
                'session_data' => $sessionData
            ]);
        }

        // Process OTP verification if needed
        if ($state === 'OTP_VERIFICATION') {
            return $this->processOTPVerification($message, $sessionData);
        }

        // Main menu states
        if (in_array($state, ['WELCOME'])) {
            return $this->processWelcomeInput($message, $sessionData);
        }

        // Help state
        if ($state === 'HELP') {
            return $this->handleHelp($message, $sessionData);
        }

        // Registration states
        if (in_array($state, [
            'REGISTRATION_INIT',
            'ACCOUNT_REGISTRATION'
        ])) {
            if (config('app.debug')) {
                Log::info('Processing registration:', [
                    'state' => $state,
                    'step' => $sessionData['data']['step'] ?? null
                ]);
            }

            return match($state) {
                'REGISTRATION_INIT' => $this->registrationController->handleRegistration($message, $sessionData),
                'ACCOUNT_REGISTRATION' => $this->registrationController->processAccountRegistration($message, $sessionData),
                default => $this->handleUnknownState($message, $sessionData)
            };
        }

        // Check authentication for protected states
        if (!$this->isUserAuthenticated($message['session_id'])) {
            return $this->initiateOTPVerification($message);
        }

        // Transfer states
        if (in_array($state, [
            'TRANSFER_INIT', 
            'INTERNAL_TRANSFER', 
            'BANK_TRANSFER', 
            'MOBILE_MONEY_TRANSFER'
        ])) {
            return $this->handleTransferStates($state, $message, $sessionData);
        }

        // Bill payment states
        if ($state === 'BILL_PAYMENT_INIT') {
            return $this->billPaymentController->processBillPayment($message, $sessionData);
        }

        // Account services states
        if (in_array($state, [
            'SERVICES_INIT',
            'BALANCE_INQUIRY',
            'MINI_STATEMENT',
            'FULL_STATEMENT',
            'PIN_MANAGEMENT'
        ])) {
            return $this->handleAccountServicesStates($state, $message, $sessionData);
        }

        if (config('app.debug')) {
            Log::warning('Unknown state encountered:', ['state' => $state]);
        }

        return $this->handleUnknownState($message, $sessionData);
    }

    /**
     * Process welcome menu input
     */
    protected function processWelcomeInput(array $message, array $sessionData): array
    {
        $input = $message['content'];
        
        // Check if user is registered
        $chatUser = ChatUser::where('phone_number', $message['sender'])->first();
        $menuConfig = $chatUser ? 'main' : 'unregistered';
        $menu = $this->getMenuConfig($menuConfig);

        if (config('app.debug')) {
            Log::info('Processing welcome input:', [
                'input' => $input,
                'menu' => $menu,
                'is_registered' => (bool)$chatUser
            ]);
        }

        foreach ($menu as $key => $option) {
            if ($input == $key || strtolower($input) == strtolower($option['text'])) {
                // Update session with selected option
                $this->messageAdapter->updateSession($message['session_id'], [
                    'state' => $option['state'],
                    'data' => [
                        'last_message' => $input,
                        'selected_option' => $key
                    ]
                ]);

                if (config('app.debug')) {
                    Log::info('Menu option selected:', [
                        'key' => $key,
                        'option' => $option,
                        'new_state' => $option['state']
                    ]);
                }

                return match ($option['state']) {
                    'REGISTRATION_INIT' => $this->registrationController->handleRegistration($message, $sessionData),
                    'HELP' => $this->handleHelp($message, $sessionData),
                    'TRANSFER_INIT' => $this->transferController->handleTransfer($message, $sessionData),
                    'BILL_PAYMENT_INIT' => $this->billPaymentController->handleBillPayment($message, $sessionData),
                    'SERVICES_INIT' => $this->showAccountServicesMenu($message),
                    default => $this->handleUnknownState($message, $sessionData)
                };
            }
        }

        if (config('app.debug')) {
            Log::warning('Invalid menu option:', ['input' => $input]);
        }

        // Show appropriate menu again for invalid input
        return $chatUser ? $this->showMainMenu($message) : $this->showUnregisteredMenu($message);
    }

    /**
     * Show menu for unregistered users
     */
    protected function showUnregisteredMenu(array $message): array
    {
        $welcomeText = "Welcome to our banking service! 👋\n\nPlease select an option:\n\n";
        
        $unregisteredMenu = $this->getMenuConfig('unregistered');
        foreach ($unregisteredMenu as $key => $option) {
            $welcomeText .= "{$key}. {$option['text']}\n";
        }
        
        $welcomeText .= "\nReply with the number of your choice.";

        return [
            'message' => $welcomeText,
            'type' => 'text'
        ];
    }

    /**
     * Handle help menu option
     */
    protected function handleHelp(array $message, array $sessionData): array
    {
        $helpText = "Welcome to our Banking Service Help! 🤝\n\n";
        $helpText .= "Here's how to use our service:\n\n";
        $helpText .= "1. Registration:\n";
        $helpText .= "   - Select 'Register' from the menu\n";
        $helpText .= "   - Enter your 10-digit account number\n";
        $helpText .= "   - Set up a 4-digit PIN\n";
        $helpText .= "   - Verify with OTP\n\n";
        $helpText .= "2. Login:\n";
        $helpText .= "   - Verify with OTP each session\n";
        $helpText .= "   - For USSD, use your PIN\n\n";
        $helpText .= "3. Navigation:\n";
        $helpText .= "   - Use menu numbers to select options\n";
        $helpText .= "   - Type 00 to return to main menu\n";
        $helpText .= "   - Type 000 to exit\n\n";
        $helpText .= "Reply with 00 to return to the main menu.";

        return [
            'message' => $helpText,
            'type' => 'text'
        ];
    }

    /**
     * Show account services menu
     */
    protected function showAccountServicesMenu(array $message): array
    {
        $servicesMenu = $this->getMenuConfig('account_services');
        $menuText = "Account Services Menu:\n\n";
        
        foreach ($servicesMenu as $serviceKey => $serviceOption) {
            $menuText .= "{$serviceKey}. {$serviceOption['text']}\n";
        }
        
        $menuText .= "\nReply with the number of your choice.\n";
        $menuText .= "Reply with 00 for main menu or 000 to exit.";

        return [
            'message' => $menuText,
            'type' => 'text'
        ];
    }

    /**
     * Handle transfer states
     */
    protected function handleTransferStates(string $state, array $message, array $sessionData): array
    {
        if ($state === 'TRANSFER_INIT' && isset($message['content'])) {
            $transferMenu = $this->getMenuConfig('transfer');
            $selection = $message['content'];

            foreach ($transferMenu as $key => $option) {
                if ($selection == $key) {
                    // Update session with selected transfer type
                    $this->messageAdapter->updateSession($message['session_id'], [
                        'state' => $option['state']
                    ]);

                    // Route to appropriate transfer handler
                    return match($option['state']) {
                        'INTERNAL_TRANSFER' => $this->transferController->processInternalTransfer($message, $sessionData),
                        'BANK_TRANSFER' => $this->transferController->processBankTransfer($message, $sessionData),
                        'MOBILE_MONEY_TRANSFER' => $this->transferController->processMobileMoneyTransfer($message, $sessionData),
                        default => $this->handleUnknownState($message, $sessionData)
                    };
                }
            }

            // Invalid selection
            return $this->formatMenuResponse(
                "Invalid selection. Please select transfer type:\n\n",
                $transferMenu
            );
        }

        // Process based on current transfer state
        return match ($state) {
            'TRANSFER_INIT' => $this->transferController->handleTransfer($message, $sessionData),
            'INTERNAL_TRANSFER' => $this->transferController->processInternalTransfer($message, $sessionData),
            'BANK_TRANSFER' => $this->transferController->processBankTransfer($message, $sessionData),
            'MOBILE_MONEY_TRANSFER' => $this->transferController->processMobileMoneyTransfer($message, $sessionData),
            default => $this->handleUnknownState($message, $sessionData)
        };
    }

    /**
     * Handle account services states
     */
    protected function handleAccountServicesStates(string $state, array $message, array $sessionData): array
    {
        if ($state === 'SERVICES_INIT') {
            return $this->accountServicesController->handleAccountServices($message, $sessionData);
        }

        return match ($state) {
            'BALANCE_INQUIRY' => $this->accountServicesController->processBalanceInquiry($message, $sessionData),
            'MINI_STATEMENT' => $this->accountServicesController->processMiniStatement($message, $sessionData),
            'FULL_STATEMENT' => $this->accountServicesController->processFullStatement($message, $sessionData),
            'PIN_MANAGEMENT' => $this->accountServicesController->processPINManagement($message, $sessionData),
            default => $this->handleUnknownState($message, $sessionData)
        };
    }

    /**
     * Check if session is expired
     */
    protected function isSessionExpired(array $sessionData): bool
    {
        $timeout = config('whatsapp.session_timeout', 600);
        $lastActivity = Carbon::parse($sessionData['updated_at']);
        
        return $lastActivity->addSeconds($timeout)->isPast();
    }

    /**
     * Handle session expiry
     */
    protected function handleSessionExpiry(array $message): array
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
     * Initiate OTP verification
     */
    protected function initiateOTPVerification(array $message): array
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
    protected function processOTPVerification(array $message, array $sessionData): array
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

        return $this->showMainMenu($message);
    }

    /**
     * Check if user is authenticated in current session
     */
    protected function isUserAuthenticated(string $sessionId): bool
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
     * Handle unknown state
     */
    protected function handleUnknownState(array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::error('Unknown state:', [
                'state' => $sessionData['state'] ?? 'NO_STATE',
                'session' => $sessionData
            ]);
        }

        return [
            'message' => "Sorry, something went wrong. Please try again.\n\nReply with 00 to return to main menu.",
            'type' => 'text'
        ];
    }
    // ... (rest of the class remains the same)
}
