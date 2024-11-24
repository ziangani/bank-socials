<?php

namespace App\Channels;

use App\Interfaces\ChannelInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class USSDChannel implements ChannelInterface
{
    // USSD session timeout in seconds (2 minutes)
    protected const SESSION_TIMEOUT = 120;

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
                return $this->handleNewSession($sessionId, $phoneNumber);
            }

            // Process input based on current state
            $currentState = $this->getSessionState($sessionId);
            return $this->processUSSDInput($sessionId, $currentState, $input);

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
        try {
            $session = Cache::get("ussd_session_{$sessionId}");
            if (!$session) {
                return false;
            }

            // Check session timeout
            if ((time() - $session['last_activity']) > self::SESSION_TIMEOUT) {
                $this->endSession($sessionId);
                return false;
            }

            // Update last activity
            $session['last_activity'] = time();
            Cache::put("ussd_session_{$sessionId}", $session, self::SESSION_TIMEOUT);

            return true;
        } catch (\Exception $e) {
            Log::error('USSD session validation error: ' . $e->getMessage());
            return false;
        }
    }

    public function initializeSession(array $data): string
    {
        try {
            $sessionId = $data['sessionId'];
            $session = [
                'phoneNumber' => $data['phoneNumber'],
                'state' => 'INIT',
                'data' => [],
                'last_activity' => time()
            ];

            Cache::put("ussd_session_{$sessionId}", $session, self::SESSION_TIMEOUT);
            return $sessionId;
        } catch (\Exception $e) {
            Log::error('USSD session initialization error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function endSession(string $sessionId): bool
    {
        try {
            Cache::forget("ussd_session_{$sessionId}");
            return true;
        } catch (\Exception $e) {
            Log::error('USSD session end error: ' . $e->getMessage());
            return false;
        }
    }

    protected function handleNewSession(string $sessionId, string $phoneNumber): array
    {
        $this->initializeSession([
            'sessionId' => $sessionId,
            'phoneNumber' => $phoneNumber
        ]);

        return [
            'message' => "Welcome to Social Banking\n1. Check Balance\n2. Send Money\n3. Pay Bills\n4. Mini Statement\n5. My Account",
            'type' => 'CON'
        ];
    }

    protected function processUSSDInput(string $sessionId, string $state, string $input): array
    {
        return match($state) {
            'INIT' => $this->handleMainMenu($sessionId, $input),
            'SEND_MONEY' => $this->handleSendMoney($sessionId, $input),
            'AWAITING_AMOUNT' => $this->handleAmount($sessionId, $input),
            'AWAITING_PIN' => $this->handlePin($sessionId, $input),
            default => $this->handleUnknownState($sessionId)
        };
    }

    protected function handleMainMenu(string $sessionId, string $input): array
    {
        $session = Cache::get("ussd_session_{$sessionId}");

        return match($input) {
            '1' => [
                'message' => 'Please enter your PIN to view balance:',
                'type' => 'CON'
            ],
            '2' => [
                'message' => "Enter recipient's phone number:",
                'type' => 'CON'
            ],
            '3' => [
                'message' => "Select bill to pay:\n1. Electricity\n2. Water\n3. TV\n4. Internet",
                'type' => 'CON'
            ],
            '4' => [
                'message' => 'Please enter your PIN for mini statement:',
                'type' => 'CON'
            ],
            '5' => [
                'message' => "My Account:\n1. Change PIN\n2. Update Profile\n3. Limits\n4. Help",
                'type' => 'CON'
            ],
            default => [
                'message' => 'Invalid selection. Please try again.',
                'type' => 'END'
            ]
        };
    }

    protected function handleSendMoney(string $sessionId, string $input): array
    {
        $session = Cache::get("ussd_session_{$sessionId}");
        
        // Validate phone number
        if (!preg_match('/^\d{10,12}$/', $input)) {
            return [
                'message' => 'Invalid phone number. Please try again.',
                'type' => 'END'
            ];
        }

        // Store recipient number and request amount
        $session['data']['recipient'] = $input;
        $session['state'] = 'AWAITING_AMOUNT';
        Cache::put("ussd_session_{$sessionId}", $session, self::SESSION_TIMEOUT);

        return [
            'message' => 'Enter amount to send:',
            'type' => 'CON'
        ];
    }

    protected function handleAmount(string $sessionId, string $input): array
    {
        $session = Cache::get("ussd_session_{$sessionId}");
        
        // Validate amount
        if (!is_numeric($input) || $input <= 0) {
            return [
                'message' => 'Invalid amount. Please try again.',
                'type' => 'END'
            ];
        }

        // Store amount and request PIN
        $session['data']['amount'] = $input;
        $session['state'] = 'AWAITING_PIN';
        Cache::put("ussd_session_{$sessionId}", $session, self::SESSION_TIMEOUT);

        return [
            'message' => 'Enter PIN to confirm transfer:',
            'type' => 'CON'
        ];
    }

    protected function handlePin(string $sessionId, string $input): array
    {
        $session = Cache::get("ussd_session_{$sessionId}");
        
        // Validate PIN (mock validation)
        if (strlen($input) !== 4 || !is_numeric($input)) {
            return [
                'message' => 'Invalid PIN. Please try again.',
                'type' => 'END'
            ];
        }

        // Process transaction (mock processing)
        $recipient = $session['data']['recipient'];
        $amount = $session['data']['amount'];

        return [
            'message' => "Confirmed: KES {$amount} sent to {$recipient}.\nReference: " . substr(md5($sessionId), 0, 8),
            'type' => 'END'
        ];
    }

    protected function handleUnknownState(string $sessionId): array
    {
        return [
            'message' => 'Session error. Please try again.',
            'type' => 'END'
        ];
    }

    protected function getSessionState(string $sessionId): string
    {
        $session = Cache::get("ussd_session_{$sessionId}");
        return $session['state'] ?? 'UNKNOWN';
    }
}
