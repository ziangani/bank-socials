<?php

namespace App\Adapters;

use App\Interfaces\MessageAdapterInterface;
use App\Integrations\WhatsAppService;
use App\Models\ProcessedMessages;
use App\Models\WhatsAppSessions;
use Illuminate\Support\Facades\Log;

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
            $entry = $request['entry'][0] ?? null;
            $changes = $entry['changes'][0] ?? null;
            $value = $changes['value'] ?? null;
            $message = $value['messages'][0] ?? null;

            if (!$message) {
                throw new \Exception('Invalid WhatsApp message format');
            }

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
                'template' => [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'type' => 'template',
                    'template' => $response['template']
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
            $session = WhatsAppSessions::where('session_id', $sessionId)
                ->where('status', 'active')
                ->first();

            if (!$session) {
                return null;
            }

            return [
                'id' => $session->id,
                'session_id' => $session->session_id,
                'sender' => $session->sender,
                'state' => $session->state,
                'data' => json_decode($session->data, true),
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
            $session = new WhatsAppSessions();
            $session->session_id = $data['session_id'];
            $session->sender = $data['sender'];
            $session->state = $data['state'] ?? 'INIT';
            $session->data = json_encode($data['data'] ?? []);
            $session->status = 'active';
            $session->save();

            return $session->session_id;
        } catch (\Exception $e) {
            Log::error('WhatsApp session creation error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateSession(string $sessionId, array $data): bool
    {
        try {
            $session = WhatsAppSessions::where('session_id', $sessionId)
                ->where('status', 'active')
                ->first();

            if (!$session) {
                return false;
            }

            $session->state = $data['state'] ?? $session->state;
            $session->data = json_encode($data['data'] ?? json_decode($session->data, true));
            $session->save();

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
        $entry = $request['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value = $changes['value'] ?? null;
        $message = $value['messages'][0] ?? null;

        return $message['from'] ?? '';
    }

    public function getMessageContent(array $request): string
    {
        $entry = $request['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value = $changes['value'] ?? null;
        $message = $value['messages'][0] ?? null;

        if (!$message) {
            return '';
        }

        return match($message['type']) {
            'text' => $message['text']['body'] ?? '',
            'interactive' => $this->getInteractiveMessageContent($message),
            'button' => $message['button']['text'] ?? '',
            default => ''
        };
    }

    public function getMessageType(array $request): string
    {
        $entry = $request['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value = $changes['value'] ?? null;
        $message = $value['messages'][0] ?? null;

        return $message['type'] ?? 'text';
    }

    public function formatMenuOptions(array $options): array
    {
        $formattedOptions = [];
        foreach ($options as $key => $option) {
            $formattedOptions[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => (string)$key,
                    'title' => substr($option, 0, 20) // WhatsApp button title limit
                ]
            ];
        }
        return $formattedOptions;
    }

    public function formatButtons(array $buttons): array
    {
        $formattedButtons = [];
        foreach ($buttons as $key => $text) {
            $formattedButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => (string)$key,
                    'title' => substr($text, 0, 20) // WhatsApp button title limit
                ]
            ];
        }
        return $formattedButtons;
    }

    public function sendMessage(string $recipient, string $message, array $options = []): bool
    {
        try {
            $businessPhoneId = $options['business_phone_id'] ?? null;
            $messageId = $options['message_id'] ?? null;

            if (!$businessPhoneId) {
                throw new \Exception('Business phone ID is required');
            }

            if (isset($options['buttons'])) {
                return $this->whatsAppService->sendMessageWithButtons(
                    $businessPhoneId,
                    $recipient,
                    $messageId,
                    $message,
                    $options['buttons']
                );
            }

            if (isset($options['template'])) {
                return $this->whatsAppService->sendTemplate(
                    $businessPhoneId,
                    $recipient,
                    $options['template'],
                    $options['template_data'] ?? []
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

        return match($type) {
            'button_reply' => $interactive['button_reply']['title'] ?? '',
            'list_reply' => $interactive['list_reply']['title'] ?? '',
            default => ''
        };
    }
}
