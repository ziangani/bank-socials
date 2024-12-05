<?php

namespace App\Channels;

use App\Interfaces\ChannelInterface;
use App\Models\WhatsAppSessions;
use Illuminate\Support\Facades\Log;

class USSDChannel implements ChannelInterface
{
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

        return [
            'message' => "Welcome to Social Banking\n1. Check Balance\n2. Send Money\n3. Pay Bills\n4. Mini Statement\n5. My Account",
            'type' => 'CON'
        ];
    }

    protected function processUSSDInput(WhatsAppSessions $session, string $input): array
    {
        return match($session->state) {
            'INIT' => $this->handleMainMenu($session, $input),
            'SEND_MONEY' => $this->handleSendMoney($session, $input),
            'AWAITING_AMOUNT' => $this->handleAmount($session, $input),
            'AWAITING_PIN' => $this->handlePin($session, $input),
            default => $this->handleUnknownState($session)
        };
    }

    protected function handleMainMenu(WhatsAppSessions $session, string $input): array
    {
        $response = match($input) {
            '1' => [
                'message' => 'Please enter your PIN to view balance:',
                'type' => 'CON',
                'next_state' => 'AWAITING_PIN'
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
                'next_state' => 'AWAITING_PIN'
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
            $session->data,
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

    protected function handlePin(WhatsAppSessions $session, string $input): array
    {
        // Validate PIN (mock validation)
        if (strlen($input) !== 4 || !is_numeric($input)) {
            return [
                'message' => 'Invalid PIN. Please try again.',
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
