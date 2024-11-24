<?php

namespace App\Channels;

use App\Interfaces\ChannelInterface;
use App\Models\WhatsAppSessions;
use App\Integrations\WhatsAppService;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel implements ChannelInterface
{
    protected WhatsAppService $whatsappService;
    protected WhatsAppSessions $sessions;

    public function __construct(WhatsAppService $whatsappService, WhatsAppSessions $sessions)
    {
        $this->whatsappService = $whatsappService;
        $this->sessions = $sessions;
    }

    public function processRequest(array $request): array
    {
        try {
            $messageType = $request['type'] ?? 'text';
            $sender = $request['from'] ?? null;
            $content = $request['content'] ?? null;

            if (!$sender || !$content) {
                throw new \Exception('Invalid request parameters');
            }

            // Handle different message types
            return match($messageType) {
                'text' => $this->handleTextMessage($sender, $content),
                'interactive' => $this->handleInteractiveMessage($sender, $content),
                'button' => $this->handleButtonMessage($sender, $content),
                default => throw new \Exception('Unsupported message type')
            };
        } catch (\Exception $e) {
            Log::error('WhatsApp channel error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to process WhatsApp request',
                'error' => $e->getMessage()
            ];
        }
    }

    public function formatResponse(array $response): array
    {
        try {
            $type = $response['type'] ?? 'text';
            
            return match($type) {
                'text' => [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'type' => 'text',
                    'text' => [
                        'body' => $response['message']
                    ]
                ],
                'interactive' => [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'type' => 'interactive',
                    'interactive' => $response['content']
                ],
                default => throw new \Exception('Unsupported response type')
            };
        } catch (\Exception $e) {
            Log::error('WhatsApp response formatting error: ' . $e->getMessage());
            return [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'type' => 'text',
                'text' => [
                    'body' => 'Sorry, we encountered an error. Please try again.'
                ]
            ];
        }
    }

    public function validateSession(string $sessionId): bool
    {
        try {
            $session = $this->sessions->find($sessionId);
            if (!$session) {
                return false;
            }

            // Check if session is expired (30 minutes)
            $expiryTime = now()->subMinutes(30);
            if ($session->updated_at < $expiryTime) {
                $this->endSession($sessionId);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Session validation error: ' . $e->getMessage());
            return false;
        }
    }

    public function initializeSession(array $data): string
    {
        try {
            $session = $this->sessions->create([
                'sender' => $data['sender'],
                'state' => 'INIT',
                'data' => json_encode($data),
                'status' => 'active'
            ]);

            return $session->id;
        } catch (\Exception $e) {
            Log::error('Session initialization error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function endSession(string $sessionId): bool
    {
        try {
            $session = $this->sessions->find($sessionId);
            if ($session) {
                $session->update([
                    'status' => 'ended',
                    'state' => 'END'
                ]);
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Session end error: ' . $e->getMessage());
            return false;
        }
    }

    protected function handleTextMessage(string $sender, string $content): array
    {
        // Get or create session
        $session = $this->sessions->firstOrCreate(
            ['sender' => $sender],
            [
                'state' => 'INIT',
                'data' => json_encode(['last_message' => $content]),
                'status' => 'active'
            ]
        );

        // Process based on current state
        return match($session->state) {
            'INIT' => $this->handleInitialState($session, $content),
            'AWAITING_PIN' => $this->handlePinState($session, $content),
            'MENU' => $this->handleMenuState($session, $content),
            default => $this->handleUnknownState($session)
        };
    }

    protected function handleInteractiveMessage(string $sender, array $content): array
    {
        // Handle interactive message responses (buttons, list selections)
        return [
            'status' => 'success',
            'type' => 'text',
            'message' => 'Interactive response received'
        ];
    }

    protected function handleButtonMessage(string $sender, array $content): array
    {
        // Handle button message responses
        return [
            'status' => 'success',
            'type' => 'text',
            'message' => 'Button response received'
        ];
    }

    protected function handleInitialState(WhatsAppSessions $session, string $content): array
    {
        // Handle initial state logic
        return [
            'status' => 'success',
            'type' => 'text',
            'message' => 'Welcome to Social Banking! Please enter your PIN to continue.'
        ];
    }

    protected function handlePinState(WhatsAppSessions $session, string $content): array
    {
        // Handle PIN validation state
        return [
            'status' => 'success',
            'type' => 'text',
            'message' => 'PIN validation in progress'
        ];
    }

    protected function handleMenuState(WhatsAppSessions $session, string $content): array
    {
        // Handle menu state
        return [
            'status' => 'success',
            'type' => 'interactive',
            'content' => [
                'type' => 'button',
                'body' => ['text' => 'Please select an option:'],
                'action' => [
                    'buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => '1', 'title' => 'Check Balance']],
                        ['type' => 'reply', 'reply' => ['id' => '2', 'title' => 'Send Money']],
                        ['type' => 'reply', 'reply' => ['id' => '3', 'title' => 'Pay Bills']]
                    ]
                ]
            ]
        ];
    }

    protected function handleUnknownState(WhatsAppSessions $session): array
    {
        // Handle unknown state
        return [
            'status' => 'error',
            'type' => 'text',
            'message' => 'Session in unknown state. Please start over.'
        ];
    }
}
