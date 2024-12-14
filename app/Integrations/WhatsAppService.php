<?php

namespace App\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $graphApiToken;
    private string $endpoint;

    public function __construct()
    {
        $this->graphApiToken = config('whatsapp.token');
        $this->endpoint = config('whatsapp.url');
    }

    //send button template message
    public function sendMessageWithButtons(string $businessPhoneNumberId, string $from, string $messageId, string $body, array $buttonsList)
    {
        $buttons = [];
        $index = 1; // Start from 1
        foreach ($buttonsList as $title) {
            $buttons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => (string)$index,
                    'title' => substr($title['reply']['title'] ?? $title, 0, 20)
                ]
            ];
            $index++;
        }
        $buttons = array_slice($buttons, 0, 3);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $from,
            'recipient_type' => 'individual',
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $body
                ],
                'action' => [
                    'buttons' => $buttons
                ]
            ],
            'context' => [
                'message_id' => $messageId,
            ],
        ];

        // Log the complete payload being sent to Meta
        Log::info('WhatsApp API Request Payload:', $payload);

        $response = Http::withToken($this->graphApiToken)
            ->timeout(30)
            ->post($this->endpoint . "/{$businessPhoneNumberId}/messages", $payload);

        // Log the response from Meta
        Log::info('WhatsApp API Response:', ['status' => $response->status(), 'body' => $response->json()]);

        return ($response->status() == 200);
    }

    public function sendMessage(string $businessPhoneNumberId, string $from, string $messageId, string $text)
    {
        $response = Http::withToken($this->graphApiToken)
            ->timeout(30)
            ->post($this->endpoint . "/{$businessPhoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $from,
                'text' => ['body' => $text],
                'context' => [
                    'message_id' => $messageId,
                ],
            ]);
        return ($response->status() == 200);
    }

    public function markMessageAsRead(string $businessPhoneNumberId, string $messageId)
    {
        Http::withToken($this->graphApiToken)
            ->timeout(30)
            ->post($this->endpoint . "/{$businessPhoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $messageId,
            ]);
    }

    public function getMessageText(array $message)
    {
        if ($message['type'] === 'text') {
            return $message['text']['body'];
        } elseif ($message['type'] === 'image') {
            return $message['image']['caption'];
        } elseif ($message['type'] === 'document') {
            return $message['document']['caption'];
        } elseif ($message['type'] === 'location') {
            return $message['location']['name'];
        } elseif ($message['type'] === 'button') {
            return $message['button']['text'];
        } elseif ($message['type'] === 'interactive') {
            return isset($message['interactive']['button_reply']) ? $message['interactive']['button_reply']['id'] : $message['interactive']['list_reply']['id'];
        }
        return '';
    }

    public function getInteractiveMenuReplyById(array $message)
    {
        if ($message['type'] === 'button') {
            return $message['button']['id'];
        } elseif ($message['type'] === 'interactive') {
            return isset($message['interactive']['button_reply']) ? $message['interactive']['button_reply']['id'] : $message['interactive']['list_reply']['id'];
        }
        return '';
    }

    public function sendWelcomeMenu(string $businessPhoneNumberId, string $from, string $messageId, string $body)
    {
        $sections = [
            [
                'title' => "Account & Registration",
                'rows' => [
                    [
                        'id' => "1",
                        'title' => "Register",
                        'description' => "Register for a new account"
                    ],
                    [
                        'id' => "4",
                        'title' => "Account Services",
                        'description' => "Balance inquiry, statements, and PIN management"
                    ]
                ]
            ],
            [
                'title' => "Transactions",
                'rows' => [
                    [
                        'id' => "2",
                        'title' => "Money Transfer",
                        'description' => "Send money to bank accounts or mobile money"
                    ],
                    [
                        'id' => "3",
                        'title' => "Bill Payments",
                        'description' => "Pay your bills and utilities"
                    ]
                ]
            ]
        ];

        $res = Http::withToken($this->graphApiToken)
            ->timeout(30)
            ->post($this->endpoint . "/{$businessPhoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $from,
                'recipient_type' => 'individual',
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'list',
                    'header' => [
                        'type' => 'text',
                        'text' => "Welcome to " . config('app.friendly_name')
                    ],
                    'body' => [
                        'text' => $body
                    ],
                    'footer' => [
                        'text' => "To return to this menu, reply with 00. To exit, reply with 000."
                    ],
                    'action' => [
                        'button' => "Select Option",
                        'sections' => $sections
                    ]
                ],
                'context' => [
                    'message_id' => $messageId,
                ],
            ]);
        if ($res->status() != 200)
            throw new \Exception('Failed to send welcome menu' . $res->body());
    }

}
