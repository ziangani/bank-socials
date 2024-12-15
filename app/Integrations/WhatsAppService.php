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

    public function sendMessage(string $businessPhoneNumberId, string $from, string $text,  string $messageId = null)
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $from,
            'text' => ['body' => $text],
        ];

        if ($messageId) {
            $payload['context'] = ['message_id' => $messageId];
        }

        $response = Http::withToken($this->graphApiToken)
            ->timeout(30)
            ->post($this->endpoint . "/{$businessPhoneNumberId}/messages", $payload);

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

    /**
     * Send a dynamic rich menu to a WhatsApp user.
     *
     * @param string $businessPhoneNumberId The business phone number ID.
     * @param string $from The recipient's phone number.
     * @param string $messageId The ID of the message to reply to.
     * @param string $body The body text of the message.
     * @param string $footer The footer text of the message.
     * @param array $sections The menu sections and options.
     * @return bool True if the message was sent successfully, otherwise false.
     * @throws \Exception If the message fails to send.
     */
    public function sendDynamicRichMenu(
        string $businessPhoneNumberId,
        string $from,
        string $messageId,
        string $body,
        string $footer,
        array $sections
    ) {
        // Truncate fields to meet Meta's API length requirements
        $body = substr($body, 0, 1024); // Assuming max length for body is 1024 characters
        $footer = substr($footer, 0, 60); // Assuming max length for footer is 60 characters

        foreach ($sections as &$section) {
            $section['title'] = substr($section['title'], 0, 24); // Assuming max length for section title is 24 characters
            foreach ($section['rows'] as &$row) {
                $row['title'] = substr($row['title'], 0, 20); // Assuming max length for row title is 20 characters
                $row['description'] = substr($row['description'], 0, 72); // Assuming max length for row description is 72 characters
            }
        }

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
                        'text' => $footer
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

        if ($res->status() != 200) {
            Log::error('Failed to send dynamic rich menu', ['response' => $res->json()]);
            throw new \Exception('Failed to send dynamic rich menu: ' . $res->body());
        }

        return true;
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
                        'text' => "Reply: 00 for menu, 000 to exit"
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

        if ($res->status() != 200) {
            Log::error('Failed to send welcome menu', ['response' => $res->json()]);
            throw new \Exception('Failed to send welcome menu: ' . $res->body());
        }

        return true;
    }

}
