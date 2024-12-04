<?php

namespace App\Adapters;

use App\Interfaces\MessageAdapterInterface;
use App\Integrations\WhatsAppService;
use App\Models\ProcessedMessages;
use App\Models\WhatsAppSessions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class WhatsAppMessageAdapter implements MessageAdapterInterface
{
    protected WhatsAppService $whatsAppService;
    protected string $channel = 'whatsapp';

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    public function parseIncomingMessage(array $request): array
    {
        try {
            // Check if the request is in the new format (flattened structure)
            if (isset($request['session_id']) && isset($request['message_id'])) {
                return [
                    'session_id' => $request['session_id'],
                    'message_id' => $request['message_id'],
                    'sender' => $request['sender'],
                    'recipient' => $request['recipient'],
                    'type' => $request['type'],
                    'content' => $request['content'],
                    'timestamp' => $request['timestamp'],
                    'contact_name' => $request['contact_name'],
                    'business_phone_id' => $request['business_phone_id'],
                    'raw_data' => $request['raw_data']
                ];
            }

            // Legacy format parsing
            $entry = $request['entry'][0] ?? null;
            $changes = $entry['changes'][0] ?? null;
            $value = $changes['value'] ?? null;
            $message = $value['messages'][0] ?? null;

            return [
                'session_id' => $entry['id'] ?? null,
                'message_id' => $message['id'] ?? null,
                'sender' => $message['from'] ?? null,
                'recipient' => $value['metadata']['display_phone_number'] ?? null,
                'type' => $message['type'] ?? 'text',
                'content' => $this->getMessageContent($request),
                'timestamp' => $message['timestamp'] ?? time(),
                'contact_name' => $value['contacts'][0]['profile']['name'] ?? 'Unknown',
                'business_phone_id' => $value['metadata']['phone_number_id'] ?? null,
                'raw_data' => $request
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp message parsing error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function formatOutgoingMessage(array $response): array
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
                    'interactive' => [
                        'type' => 'button',
                        'body' => [
                            'text' => $response['message']
                        ],
                        'action' => [
                            'buttons' => $this->formatButtons($response['buttons'] ?? [])
                        ]
                    ]
                ],
                default => throw new \Exception('Unsupported message type: ' . $type)
            };
        } catch (\Exception $e) {
            Log::error('WhatsApp message formatting error: ' . $e->getMessage());
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

    public function getSessionData(string $sessionId): ?array
    {
        try {
            $session = WhatsAppSessions::getActiveSession($sessionId);

            if (!$session) {
                // Try to find by sender
                $session = WhatsAppSessions::getActiveSessionBySender($sessionId);
            }

            if (!$session) {
                return null;
            }

            return [
                'id' => $session->id,
                'session_id' => $session->session_id,
                'sender' => $session->sender,
                'state' => $session->state,
                'data' => $session->data,
                'created_at' => $session->created_at,
                'updated_at' => $session->updated_at
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp session retrieval error: ' . $e->getMessage());
            return null;
        }
    }

    public function createSession(array $data): string
    {
        try {
            // End any existing active sessions for this sender
            WhatsAppSessions::endActiveSessions($data['sender']);

            // Create new session
            $session = WhatsAppSessions::createNewState(
                $data['session_id'],
                $data['sender'],
                $data['state'] ?? 'INIT',
                array_merge($data['data'] ?? [], [
                    'business_phone_id' => $data['business_phone_id'] ?? null,
                    'message_id' => $data['message_id'] ?? null,
                    'contact_name' => $data['contact_name'] ?? null
                ]),
                $this->channel
            );

            return $session->session_id;
        } catch (\Exception $e) {
            Log::error('WhatsApp session creation error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateSession(string $sessionId, array $data): bool
    {
        try {
            $currentSession = WhatsAppSessions::getActiveSession($sessionId);

            if (!$currentSession) {
                return false;
            }

            // Create new session state
            WhatsAppSessions::createNewState(
                $sessionId,
                $currentSession->sender,
                $data['state'] ?? $currentSession->state,
                array_merge($currentSession->data ?? [], $data['data'] ?? []),
                $this->channel
            );

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsApp session update error: ' . $e->getMessage());
            return false;
        }
    }

    public function endSession(string $sessionId): bool
    {
        try {
            return WhatsAppSessions::where('session_id', $sessionId)
                ->where('status', 'active')
                ->update(['status' => 'ended', 'state' => 'END']);
        } catch (\Exception $e) {
            Log::error('WhatsApp session end error: ' . $e->getMessage());
            return false;
        }
    }

    public function isMessageProcessed(string $messageId): bool
    {
        return ProcessedMessages::where('message_id', $messageId)
            ->where('driver', $this->channel)
            ->exists();
    }

    public function markMessageAsProcessed(string $messageId): bool
    {
        try {
            $processedMessage = new ProcessedMessages();
            $processedMessage->message_id = $messageId;
            $processedMessage->driver = $this->channel;
            $processedMessage->save();

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsApp message processing error: ' . $e->getMessage());
            return false;
        }
    }

    public function getUserIdentifier(array $request): string
    {
        // Check if request is in new format
        if (isset($request['sender'])) {
            return $request['sender'];
        }

        // Legacy format
        $entry = $request['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value = $changes['value'] ?? null;
        $message = $value['messages'][0] ?? null;

        return $message['from'] ?? '';
    }

    public function getMessageContent(array $request): string
    {
        // Check if request is in new format
        if (isset($request['content'])) {
            return $request['content'];
        }

        // Legacy format
        $entry = $request['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value = $changes['value'] ?? null;
        $message = $value['messages'][0] ?? null;

        if (!$message) {
            return '';
        }

        $content = match($message['type']) {
            'text' => $message['text']['body'] ?? '',
            'interactive' => $this->getInteractiveMessageContent($message),
            'button' => $message['button']['text'] ?? '',
            default => ''
        };

        // Extract number if content starts with a number followed by period and space
        if (preg_match('/^(\d+)\.\s*(.+)$/', $content, $matches)) {
            return $matches[1];
        }

        // If content is just a number, return it
        if (is_numeric($content)) {
            return $content;
        }

        return $content;
    }

    public function getMessageType(array $request): string
    {
        // Check if request is in new format
        if (isset($request['type'])) {
            return $request['type'];
        }

        // Legacy format
        $entry = $request['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value = $changes['value'] ?? null;
        $message = $value['messages'][0] ?? null;

        return $message['type'] ?? 'text';
    }

    public function formatMenuOptions(array $options): array
    {
        $formattedOptions = [];
        $index = 1;
        foreach ($options as $key => $option) {
            $formattedOptions[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => (string)$key,
                    'title' => $index . '. ' . substr($option, 0, 18) // WhatsApp button title limit minus prefix
                ]
            ];
            $index++;
        }
        return $formattedOptions;
    }

    public function formatButtons(array $buttons): array
    {
        $formattedButtons = [];
        $index = 1;
        foreach ($buttons as $key => $text) {
            $formattedButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => (string)$key,
                    'title' => $index . '. ' . substr($text, 0, 18) // WhatsApp button title limit minus prefix
                ]
            ];
            $index++;
        }
        return $formattedButtons;
    }

    public function sendMessage(string $recipient, string $message, array $options = []): bool
    {
        try {
            // First try to get business phone ID from options
            $businessPhoneId = $options['business_phone_id'] ?? null;

            // If not in options, try to get from session
            if (!$businessPhoneId) {
                $session = WhatsAppSessions::getActiveSessionBySender($recipient);
                if ($session) {
                    $sessionData = $session->data;
                    if (is_string($sessionData)) {
                        $sessionData = json_decode($sessionData, true);
                    }
                    $businessPhoneId = $sessionData['business_phone_id'] ?? null;
                }
            }

            // If still not found, use config value as fallback
            if (!$businessPhoneId) {
                $businessPhoneId = config('whatsapp.business_phone_id');
            }

            if (!$businessPhoneId) {
                throw new \Exception('Business phone ID is required');
            }

            $messageId = $options['message_id'] ?? null;

            if (isset($options['buttons'])) {
                return $this->whatsAppService->sendMessageWithButtons(
                    $businessPhoneId,
                    $recipient,
                    $messageId,
                    $message,
                    $options['buttons']
                );
            }

            return $this->whatsAppService->sendMessage(
                $businessPhoneId,
                $recipient,
                $messageId,
                $message
            );
        } catch (\Exception $e) {
            Log::error('WhatsApp message sending error: ' . $e->getMessage());
            return false;
        }
    }

    protected function getInteractiveMessageContent(array $message): string
    {
        $interactive = $message['interactive'] ?? [];
        $type = $interactive['type'] ?? '';

        $content = match($type) {
            'button_reply' => $interactive['button_reply']['title'] ?? '',
            'list_reply' => $interactive['list_reply']['title'] ?? '',
            default => ''
        };

        // Extract number if content starts with a number followed by period and space
        if (preg_match('/^(\d+)\.\s*(.+)$/', $content, $matches)) {
            return $matches[1];
        }

        // If content is just a number, return it
        if (is_numeric($content)) {
            return $content;
        }

        return $content;
    }
}
