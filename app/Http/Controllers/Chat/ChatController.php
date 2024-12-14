<?php

namespace App\Http\Controllers\Chat;

use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
                // return response()->json(['status' => 'already_processed']);
            }

            // Check for exit command '000'
            if ($parsedMessage['content'] === '000') {
                return $this->handleExitCommand($parsedMessage);
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

            // Send response via message adapter
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
     * Handle return to main menu command
     */
    protected function handleReturnToMainMenu(array $parsedMessage): \Illuminate\Http\JsonResponse
    {
        // Update session state to WELCOME
        if ($parsedMessage['session_id']) {
            $this->messageAdapter->updateSession($parsedMessage['session_id'], [
                'state' => 'WELCOME',
                'data' => [
                    'last_message' => '00'
                ]
            ]);
        }

        // Get welcome message response
        $response = $this->handleWelcome($parsedMessage);

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
        $contactName = $message['contact_name'] ?? 'there';

        $menuText = "Hello {$contactName}! 👋\n\n";
        $menuText .= "Welcome to our Social Banking Service. Please select an option:\n\n";

        $mainMenu = $this->getMenuConfig('main');
        $menuText .= "\nTo return to this menu at any time, reply with 00.\nTo exit at any time, reply with 000.";

        return $this->formatMenuResponse($menuText, $mainMenu);
    }

    /**
     * Process message based on current state
     */
    protected function processState(string $state, array $message, array $sessionData): array
    {
        if (config('app.debug')) {
            Log::info('Processing state:', [
                'state' => $state,
                'message' => $message,
                'session_data' => $sessionData
            ]);
        }

        // Main menu states
        if (in_array($state, ['WELCOME'])) {
            return $this->processWelcomeInput($message, $sessionData);
        }

        // Registration states
        if (in_array($state, [
            'REGISTRATION_INIT',
            'CARD_REGISTRATION',
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
                'CARD_REGISTRATION' => $this->registrationController->processCardRegistration($message, $sessionData),
                'ACCOUNT_REGISTRATION' => $this->registrationController->processAccountRegistration($message, $sessionData),
                default => $this->handleUnknownState($message, $sessionData)
            };
        }

        // Transfer states
        if (in_array($state, [
            'TRANSFER_INIT', 
            'INTERNAL_TRANSFER', 
            'BANK_TRANSFER', 
            'MOBILE_MONEY_TRANSFER'
        ])) {
            return match ($state) {
                'TRANSFER_INIT' => $this->transferController->handleTransfer($message, $sessionData),
                'INTERNAL_TRANSFER' => $this->transferController->processInternalTransfer($message, $sessionData),
                'BANK_TRANSFER' => $this->transferController->processBankTransfer($message, $sessionData),
                'MOBILE_MONEY_TRANSFER' => $this->transferController->processMobileMoneyTransfer($message, $sessionData),
                default => $this->handleUnknownState($message, $sessionData)
            };
        }

        // Bill payment states
        if ($state === 'BILL_PAYMENT_INIT' || isset($sessionData['data']['step'])) {
            if (config('app.debug')) {
                Log::info('Processing bill payment:', [
                    'state' => $state,
                    'step' => $sessionData['data']['step'] ?? null
                ]);
            }

            // If we're in a bill payment step, process it
            if (isset($sessionData['data']['step'])) {
                return $this->billPaymentController->processBillPayment($message, $sessionData);
            }

            // Otherwise, initialize bill payment
            return $this->billPaymentController->handleBillPayment($message, $sessionData);
        }

        // Account services states
        if (in_array($state, [
            'SERVICES_INIT',
            'BALANCE_INQUIRY',
            'MINI_STATEMENT',
            'FULL_STATEMENT',
            'PIN_MANAGEMENT'
        ])) {
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
        $mainMenu = $this->getMenuConfig('main');

        if (config('app.debug')) {
            Log::info('Processing welcome input:', [
                'input' => $input,
                'menu' => $mainMenu
            ]);
        }

        foreach ($mainMenu as $key => $option) {
            if ($input == $key || strtolower($input) == strtolower($option['text'])) {
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
                    'TRANSFER_INIT' => $this->transferController->handleTransfer($message, $sessionData),
                    'BILL_PAYMENT_INIT' => $this->billPaymentController->handleBillPayment($message, $sessionData),
                    'SERVICES_INIT' => $this->accountServicesController->handleAccountServices($message, $sessionData),
                    default => $this->handleUnknownState($message, $sessionData)
                };
            }
        }

        if (config('app.debug')) {
            Log::warning('Invalid menu option:', ['input' => $input]);
        }

        return $this->formatMenuResponse(
            "Invalid option. Please select from the menu below:",
            $mainMenu
        );
    }
}
