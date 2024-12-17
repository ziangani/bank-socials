<?php

namespace App\Channels;

use App\Common\GeneralStatus;
use App\Interfaces\ChannelInterface;
use App\Models\WhatsAppSessions;
use App\Models\ChatUser;
use App\Services\AuthenticationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class USSDChannel implements ChannelInterface
{
    protected AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    public function processRequest(array $request): array
    {
        try {
            $sessionId = $request['sessionId'] ?? null;
            $phoneNumber = $request['phoneNumber'] ?? null;
            $input = $request['text'] ?? '';
            $serviceCode = $request['serviceCode'] ?? '*123#';

            if (!$sessionId || !$phoneNumber) {
                throw new \Exception('Invalid USSD request parameters');
            }

            // Check for logout command
            if ($input === '000') {
                return $this->handleLogout($sessionId, $phoneNumber);
            }

            // New session
            if (empty($input)) {
                return $this->handleNewSession($sessionId, $phoneNumber, $serviceCode);
            }

            // Get active session
            $session = WhatsAppSessions::getActiveSession($sessionId);
            if (!$session) {
                return [
                    'status' => 'error',
                    'message' => 'Session error. Please try again.',
                    'type' => 'END'
                ];
            }

            // Process input based on current state
            return $this->processUSSDInput($session, $input);

        } catch (\Exception $e) {
            Log::error('USSD channel error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'System error. Please try again.',
                'type' => 'END'
            ];
        }
    }

    public function formatResponse(array $response): array
    {
        try {
            return [
                'message' => $response['message'],
                'type' => $response['type'] ?? 'CON' // CON to continue, END to terminate
            ];
        } catch (\Exception $e) {
            Log::error('USSD response formatting error: ' . $e->getMessage());
            return [
                'message' => 'System error. Please try again.',
                'type' => 'END'
            ];
        }
    }

    public function validateSession(string $sessionId): bool
    {
        $session = WhatsAppSessions::getActiveSession($sessionId);
        return $session ? $session->isActive() : false;
    }

    public function initializeSession(array $data): string
    {
        try {
            $sessionId = $data['sessionId'] ?? 'USSD_' . uniqid();
            $phoneNumber = $data['phoneNumber'] ?? null;
            $serviceCode = $data['serviceCode'] ?? '*123#';

            if (!$phoneNumber) {
                throw new \Exception('Phone number is required for USSD session');
            }

            // End any existing active sessions for this phone number
            WhatsAppSessions::endActiveSessions($phoneNumber);

            // Create new session
            WhatsAppSessions::createNewState(
                $sessionId,
                $phoneNumber,
                'INIT',
                ['service_code' => $serviceCode],
                'ussd'
            );

            return $sessionId;
        } catch (\Exception $e) {
            Log::error('USSD session initialization error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function endSession(string $sessionId): bool
    {
        try {
            $session = WhatsAppSessions::getActiveSession($sessionId);
            if ($session) {
                return $session->update([
                    'status' => 'ended',
                    'state' => 'END'
                ]);
            }
            return false;
        } catch (\Exception $e) {
            Log::error('USSD session end error: ' . $e->getMessage());
            return false;
        }
    }

    protected function handleNewSession(string $sessionId, string $phoneNumber, string $serviceCode): array
    {
        // Initialize new session
        $this->initializeSession([
            'sessionId' => $sessionId,
            'phoneNumber' => $phoneNumber,
            'serviceCode' => $serviceCode
        ]);

        // Check if user is registered
        $chatUser = ChatUser::where('phone_number', $phoneNumber)->first();

        if (!$chatUser) {
            WhatsAppSessions::createNewState(
                $sessionId,
                $phoneNumber,
                'REGISTRATION_INIT',
                [],
                'ussd'
            );

            return [
                'message' => "Welcome to Social Banking\n1. Register\n2. Help",
                'type' => 'CON'
            ];
        }

        return [
            'message' => "Welcome to Social Banking\nPlease enter your PIN to continue:",
            'type' => 'CON'
        ];
    }

    protected function handleLogout(string $sessionId, string $phoneNumber): array
    {
        try {
            // End current session
            $this->endSession($sessionId);

            // Clear any stored authentication state
            WhatsAppSessions::where('session_id', $sessionId)
                ->update([
                    'status' => 'ended',
                    'state' => 'END',
                    'data' => json_encode([
                        'authenticated' => false,
                        'logged_out_at' => now()
                    ])
                ]);

            return [
                'message' => "You have been logged out successfully.\n\nDial *123# to start a new session.",
                'type' => 'END'
            ];

        } catch (\Exception $e) {
            Log::error('USSD logout error: ' . $e->getMessage());
            return [
                'message' => 'Error processing logout. Please try again.',
                'type' => 'END'
            ];
        }
    }

    protected function processUSSDInput(WhatsAppSessions $session, string $input): array
    {
        return match($session->state) {
            'INIT' => $this->handleInitialPin($session, $input),
            'REGISTRATION_INIT' => $this->handleRegistrationInit($session, $input),
            'ACCOUNT_NUMBER_INPUT' => $this->handleAccountNumberInput($session, $input),
            'PIN_SETUP' => $this->handlePinSetup($session, $input),
            'CONFIRM_PIN' => $this->handleConfirmPin($session, $input),
            'MAIN_MENU' => $this->handleMainMenu($session, $input),
            'SEND_MONEY' => $this->handleSendMoney($session, $input),
            'AWAITING_AMOUNT' => $this->handleAmount($session, $input),
            'AWAITING_PIN' => $this->handleTransactionPin($session, $input),
            default => $this->handleUnknownState($session)
        };
    }

    protected function handleInitialPin(WhatsAppSessions $session, string $input): array
    {
        $chatUser = ChatUser::where('phone_number', $session->sender)->first();

        if (!$chatUser) {
            return [
                'message' => "Account not registered. Please register first.\n1. Register\n2. Help",
                'type' => 'CON'
            ];
        }

        if (!Hash::check($input, $chatUser->pin)) {
            return [
                'message' => 'Invalid PIN. Please try again.',
                'type' => 'END'
            ];
        }

        WhatsAppSessions::createNewState(
            $session->session_id,
            $session->sender,
            'MAIN_MENU',
            $session->data,
            'ussd'
        );

        return [
            'message' => "Main Menu\n1. Check Balance\n2. Send Money\n3. Pay Bills\n4. Mini Statement\n5. My Account",
            'type' => 'CON'
        ];
    }

    protected function handleRegistrationInit(WhatsAppSessions $session, string $input): array
    {
        if ($input === '1') {
            WhatsAppSessions::createNewState(
                $session->session_id,
                $session->sender,
                'ACCOUNT_NUMBER_INPUT',
                [],
                'ussd'
            );

            return [
                'message' => 'Please enter your account number (10 digits):',
                'type' => 'CON'
            ];
        } elseif ($input === '2') {
            return [
                'message' => "Help Information:\n" .
                            "1. Registration requires:\n" .
                            "   - Valid account number\n" .
                            "   - 4-digit PIN setup\n" .
                            "2. For assistance call: 100\n\n" .
                            "0. Back",
                'type' => 'CON'
            ];
        }

        return [
            'message' => "Invalid selection.\n1. Register\n2. Help",
            'type' => 'CON'
        ];
    }

    protected function handleAccountNumberInput(WhatsAppSessions $session, string $input): array
    {
        $validation = $this->authService->validateAccountDetails([
            'account_number' => $input,
            'phone_number' => $session->sender
        ]);

        if ($validation['status'] !== GeneralStatus::SUCCESS) {
            return [
                'message' => $validation['message'],
                'type' => 'CON'
            ];
        }

        WhatsAppSessions::createNewState(
            $session->session_id,
            $session->sender,
            'PIN_SETUP',
            ['account_number' => $input],
            'ussd'
        );

        return [
            'message' => 'Please set up your 4-digit PIN:',
            'type' => 'CON'
        ];
    }

    protected function handlePinSetup(WhatsAppSessions $session, string $input): array
    {
        if (!preg_match('/^\d{4}$/', $input)) {
            return [
                'message' => 'Invalid PIN. Please enter exactly 4 digits:',
                'type' => 'CON'
            ];
        }

        WhatsAppSessions::createNewState(
            $session->session_id,
            $session->sender,
            'CONFIRM_PIN',
            [
                ...$session->data,
                'pin' => $input
            ],
            'ussd'
        );

        return [
            'message' => 'Please confirm your PIN:',
            'type' => 'CON'
        ];
    }

    protected function handleConfirmPin(WhatsAppSessions $session, string $input): array
    {
        if ($input !== $session->data['pin']) {
            return [
                'message' => 'PINs do not match. Please try again:',
                'type' => 'CON'
            ];
        }

        // Create ChatUser record
        ChatUser::create([
            'phone_number' => $session->sender,
            'account_number' => $session->data['account_number'],
            'pin' => Hash::make($input),
            'is_verified' => true
        ]);

        return [
            'message' => "Registration successful!\nThank you for registering.\n\nDial *123# to start using our services.",
            'type' => 'END'
        ];
    }

    protected function handleMainMenu(WhatsAppSessions $session, string $input): array
    {
        $response = match($input) {
            '1' => [
                'message' => 'Please enter your PIN to view balance:',
                'type' => 'CON',
                'next_state' => 'AWAITING_PIN',
                'action' => 'balance'
            ],
            '2' => [
                'message' => "Enter recipient's phone number:",
                'type' => 'CON',
                'next_state' => 'SEND_MONEY'
            ],
            '3' => [
                'message' => "Select bill to pay:\n1. Electricity\n2. Water\n3. TV\n4. Internet",
                'type' => 'CON',
                'next_state' => 'BILL_PAYMENT'
            ],
            '4' => [
                'message' => 'Please enter your PIN for mini statement:',
                'type' => 'CON',
                'next_state' => 'AWAITING_PIN',
                'action' => 'statement'
            ],
            '5' => [
                'message' => "My Account:\n1. Change PIN\n2. Update Profile\n3. Limits\n4. Help",
                'type' => 'CON',
                'next_state' => 'ACCOUNT_MENU'
            ],
            default => [
                'message' => 'Invalid selection. Please try again.',
                'type' => 'END',
                'next_state' => 'END'
            ]
        };

        // Create new state
        WhatsAppSessions::createNewState(
            $session->session_id,
            $session->sender,
            $response['next_state'],
            array_merge($session->data, ['action' => $response['action'] ?? null]),
            'ussd'
        );

        return [
            'message' => $response['message'],
            'type' => $response['type']
        ];
    }

    protected function handleSendMoney(WhatsAppSessions $session, string $input): array
    {
        // Validate phone number
        if (!preg_match('/^\d{10,12}$/', $input)) {
            return [
                'message' => 'Invalid phone number. Please try again.',
                'type' => 'END'
            ];
        }

        // Store recipient number and update state
        WhatsAppSessions::createNewState(
            $session->session_id,
            $session->sender,
            'AWAITING_AMOUNT',
            array_merge($session->data, ['recipient' => $input]),
            'ussd'
        );

        return [
            'message' => 'Enter amount to send:',
            'type' => 'CON'
        ];
    }

    protected function handleAmount(WhatsAppSessions $session, string $input): array
    {
        // Validate amount
        if (!is_numeric($input) || $input <= 0) {
            return [
                'message' => 'Invalid amount. Please try again.',
                'type' => 'END'
            ];
        }

        // Store amount and update state
        WhatsAppSessions::createNewState(
            $session->session_id,
            $session->sender,
            'AWAITING_PIN',
            array_merge($session->data, ['amount' => $input]),
            'ussd'
        );

        return [
            'message' => 'Enter PIN to confirm transfer:',
            'type' => 'CON'
        ];
    }

    protected function handleTransactionPin(WhatsAppSessions $session, string $input): array
    {
        $chatUser = ChatUser::where('phone_number', $session->sender)->first();

        if (!$chatUser || !Hash::check($input, $chatUser->pin)) {
            return [
                'message' => 'Invalid PIN. Please try again.',
                'type' => 'END'
            ];
        }

        $action = $session->data['action'] ?? null;
        if ($action === 'balance') {
            return [
                'message' => "Your balance is:\nKES 25,000.00",
                'type' => 'END'
            ];
        }

        if ($action === 'statement') {
            return [
                'message' => "Mini Statement:\n" .
                            "1. KES 1,000 Sent to 07XXXXXXXX\n" .
                            "2. KES 500 Airtime Purchase\n" .
                            "3. KES 2,500 Received",
                'type' => 'END'
            ];
        }

        $recipient = $session->getDataValue('recipient');
        $amount = $session->getDataValue('amount');

        // End the session
        $this->endSession($session->session_id);

        return [
            'message' => "Confirmed: KES {$amount} sent to {$recipient}.\nReference: " . substr(md5($session->session_id), 0, 8),
            'type' => 'END'
        ];
    }

    protected function handleUnknownState(WhatsAppSessions $session): array
    {
        return [
            'message' => 'Session error. Please try again.',
            'type' => 'END'
        ];
    }
}
