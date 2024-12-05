<?php

namespace App\Adapters\WhatsApp;

use Illuminate\Support\Facades\Log;

class WhatsAppMessageParser
{
    public function parseIncomingMessage(array $request): array
    {
        try {
            // Check if the request is in the new format (flattened structure)
            if (isset($request['session_id']) && isset($request['raw_data'])) {
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
}
