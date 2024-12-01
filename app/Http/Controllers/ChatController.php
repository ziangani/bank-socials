<?php

namespace App\Http\Controllers;

use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    protected MessageAdapterInterface $messageAdapter;
    protected SessionManager $sessionManager;

    protected array $mainMenu = [
        '1' => [
            'text' => 'Register',
            'function' => 'handleRegistration',
            'state' => 'REGISTRATION_INIT'
        ],
        '2' => [
            'text' => 'Money Transfer',
            'function' => 'handleTransfer',
            'state' => 'TRANSFER_INIT'
        ],
        '3' => [
            'text' => 'Bill Payments',
            'function' => 'handleBillPayment',
            'state' => 'BILL_PAYMENT_INIT'
        ],
        '4' => [
            'text' => 'Account Services',
            'function' => 'handleAccountServices',
            'state' => 'SERVICES_INIT'
        ]
    ];

    protected array $registrationMenu = [
        '1' => [
            'text' => 'Card Registration',
            'function' => 'handleCardRegistration',
            'state' => 'CARD_REGISTRATION'
        ],
        '2' => [
            'text' => 'Account Registration',
            'function' => 'handleAccountRegistration',
            'state' => 'ACCOUNT_REGISTRATION'
        ]
    ];

    protected array $transferMenu = [
        '1' => [
            'text' => 'Internal Transfer',
            'function' => 'handleInternalTransfer',
            'state' => 'INTERNAL_TRANSFER'
        ],
        '2' => [
            'text' => 'Bank Transfer',
            'function' => 'handleBankTransfer',
            'state' => 'BANK_TRANSFER'
        ],
        '3' => [
            'text' => 'Mobile Money',
            'function' => 'handleMobileMoneyTransfer',
            'state' => 'MOBILE_MONEY_TRANSFER'
        ]
    ];

    protected array $accountServicesMenu = [
        '1' => [
            'text' => 'Balance Inquiry',
            'function' => 'handleBalanceInquiry',
            'state' => 'BALANCE_INQUIRY'
        ],
        '2' => [
            'text' => 'Mini Statement',
            'function' => 'handleMiniStatement',
            'state' => 'MINI_STATEMENT'
        ],
        '3' => [
            'text' => 'Full Statement',
            'function' => 'handleFullStatement',
            'state' => 'FULL_STATEMENT'
        ],
        '4' => [
            'text' => 'PIN Management',
            'function' => 'handlePINManagement',
            'state' => 'PIN_MANAGEMENT'
        ]
    ];

    public function __construct(MessageAdapterInterface $messageAdapter, SessionManager $sessionManager)
    {
        $this->messageAdapter = $messageAdapter;
        $this->sessionManager = $sessionManager;
    }

    /**
     * Process incoming chat message
     */
    public function processMessage(Request $request)
    {
        try {
            // Parse incoming message
            $parsedMessage = $this->messageAdapter->parseIncomingMessage($request->all());
            
            // Check if message already processed
            if ($this->messageAdapter->isMessageProcessed($parsedMessage['message_id'])) {
                return response()->json(['status' => 'already_processed']);
            }

            // Get or create session
            $sessionData = $this->messageAdapter->getSessionData($parsedMessage['session_id']);
            
            if (!$sessionData) {
                // New session - show welcome message
                $sessionId = $this->messageAdapter->createSession([
                    'session_id' => $parsedMessage['session_id'],
                    'sender' => $parsedMessage['sender'],
                    'state' => 'WELCOME',
                    'data' => [
                        'contact_name' => $parsedMessage['contact_name'] ?? null,
                        'last_message' => $parsedMessage['content']
                    ]
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
            if ($response['type'] === 'interactive') {
                $options['buttons'] = $this->messageAdapter->formatButtons($response['buttons']);
            }

            $this->messageAdapter->sendMessage(
                $parsedMessage['sender'],
                $response['message'],
                $options
            );

            // Format response for channel
            $formattedResponse = $this->messageAdapter->formatOutgoingMessage($response);

            return response()->json($formattedResponse);

        } catch (\Exception $e) {
            Log::error('Chat processing error: ' . $e->getMessage());
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
        $contactName = $message['contact_name'] ?? 'there';
        
        $menuText = "Hello {$contactName}! 👋\n\n";
        $menuText .= "Welcome to our Social Banking Service. Please select an option:\n\n";
        
        $options = [];
        foreach ($this->mainMenu as $key => $option) {
            $options[$key] = $option['text'];
        }

        return [
            'message' => $menuText,
            'type' => 'interactive',
            'buttons' => $options,
            'end_session' => false
        ];
    }

    /**
     * Process message based on current state
     */
    protected function processState(string $state, array $message, array $sessionData): array
    {
        return match($state) {
            'WELCOME' => $this->processWelcomeInput($message, $sessionData),
            'REGISTRATION_INIT' => $this->processRegistrationInit($message, $sessionData),
            'TRANSFER_INIT' => $this->processTransferInit($message, $sessionData),
            'BILL_PAYMENT_INIT' => $this->processBillPaymentInit($message, $sessionData),
            'SERVICES_INIT' => $this->processServicesInit($message, $sessionData),
            'CARD_REGISTRATION' => $this->processCardRegistration($message, $sessionData),
            'ACCOUNT_REGISTRATION' => $this->processAccountRegistration($message, $sessionData),
            'INTERNAL_TRANSFER' => $this->processInternalTransfer($message, $sessionData),
            'BANK_TRANSFER' => $this->processBankTransfer($message, $sessionData),
            'MOBILE_MONEY_TRANSFER' => $this->processMobileMoneyTransfer($message, $sessionData),
            'BALANCE_INQUIRY' => $this->processBalanceInquiry($message, $sessionData),
            'MINI_STATEMENT' => $this->processMiniStatement($message, $sessionData),
            'FULL_STATEMENT' => $this->processFullStatement($message, $sessionData),
            'PIN_MANAGEMENT' => $this->processPINManagement($message, $sessionData),
            default => $this->handleUnknownState($message, $sessionData)
        };
    }

    /**
     * Process registration initialization
     */
    protected function processRegistrationInit(array $message, array $sessionData): array
    {
        $menuText = "Please select registration type:\n\n";
        return [
            'message' => $menuText,
            'type' => 'interactive',
            'buttons' => array_column($this->registrationMenu, 'text'),
            'end_session' => false
        ];
    }

    /**
     * Process transfer initialization
     */
    protected function processTransferInit(array $message, array $sessionData): array
    {
        $menuText = "Please select transfer type:\n\n";
        return [
            'message' => $menuText,
            'type' => 'interactive',
            'buttons' => array_column($this->transferMenu, 'text'),
            'end_session' => false
        ];
    }

    /**
     * Process bill payment initialization
     */
    protected function processBillPaymentInit(array $message, array $sessionData): array
    {
        return [
            'message' => "Please enter the bill account number:",
            'type' => 'text',
            'end_session' => false
        ];
    }

    /**
     * Process services initialization
     */
    protected function processServicesInit(array $message, array $sessionData): array
    {
        $menuText = "Please select a service:\n\n";
        return [
            'message' => $menuText,
            'type' => 'interactive',
            'buttons' => array_column($this->accountServicesMenu, 'text'),
            'end_session' => false
        ];
    }

    /**
     * Process card registration
     */
    protected function processCardRegistration(array $message, array $sessionData): array
    {
        return [
            'message' => "Please enter your 16-digit card number:",
            'type' => 'text',
            'end_session' => false
        ];
    }

    /**
     * Process account registration
     */
    protected function processAccountRegistration(array $message, array $sessionData): array
    {
        return [
            'message' => "Please enter your account number:",
            'type' => 'text',
            'end_session' => false
        ];
    }

    /**
     * Process internal transfer
     */
    protected function processInternalTransfer(array $message, array $sessionData): array
    {
        return [
            'message' => "Please enter recipient's account number:",
            'type' => 'text',
            'end_session' => false
        ];
    }

    /**
     * Process bank transfer
     */
    protected function processBankTransfer(array $message, array $sessionData): array
    {
        return [
            'message' => "Please enter recipient's bank account number:",
            'type' => 'text',
            'end_session' => false
        ];
    }

    /**
     * Process mobile money transfer
     */
    protected function processMobileMoneyTransfer(array $message, array $sessionData): array
    {
        return [
            'message' => "Please enter recipient's mobile number:",
            'type' => 'text',
            'end_session' => false
        ];
    }

    /**
     * Process balance inquiry
     */
    protected function processBalanceInquiry(array $message, array $sessionData): array
    {
        return [
            'message' => "Please enter your PIN to view balance:",
            'type' => 'text',
            'end_session' => false
        ];
    }

    /**
     * Process mini statement
     */
    protected function processMiniStatement(array $message, array $sessionData): array
    {
        return [
            'message' => "Please enter your PIN to view mini statement:",
            'type' => 'text',
            'end_session' => false
        ];
    }

    /**
     * Process full statement
     */
    protected function processFullStatement(array $message, array $sessionData): array
    {
        return [
            'message' => "Please enter your PIN to view full statement:",
            'type' => 'text',
            'end_session' => false
        ];
    }

    /**
     * Process PIN management
     */
    protected function processPINManagement(array $message, array $sessionData): array
    {
        return [
            'message' => "PIN Management:\n1. Change PIN\n2. Reset PIN\n3. Set Transaction PIN",
            'type' => 'interactive',
            'buttons' => [
                '1' => 'Change PIN',
                '2' => 'Reset PIN',
                '3' => 'Set Transaction PIN'
            ],
            'end_session' => false
        ];
    }

    /**
     * Handle unknown state
     */
    protected function handleUnknownState(array $message, array $sessionData): array
    {
        return [
            'message' => "Sorry, we encountered an error. Please try again.",
            'type' => 'text',
            'end_session' => true
        ];
    }

    /**
     * Process welcome menu input
     */
    protected function processWelcomeInput(array $message, array $sessionData): array
    {
        $input = $message['content'];
        
        foreach ($this->mainMenu as $key => $option) {
            if ($input === $key || strtolower($input) === strtolower($option['text'])) {
                $this->messageAdapter->updateSession($message['session_id'], [
                    'state' => $option['state'],
                    'data' => [
                        'last_message' => $input,
                        'selected_option' => $key
                    ]
                ]);

                return $this->{$option['function']}($message, $sessionData);
            }
        }

        return [
            'message' => "Invalid option. Please select from the menu below:",
            'type' => 'interactive',
            'buttons' => array_column($this->mainMenu, 'text'),
            'end_session' => false
        ];
    }
}
