<?php

namespace App\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $graphApiToken;
    private string $endpoint;
    
    // WhatsApp API limits
    private const BUTTON_TITLE_MAX_LENGTH = 20;
    private const MAX_BUTTONS = 3;
    private const BODY_MAX_LENGTH = 1024;
    private const FOOTER_MAX_LENGTH = 60;
    private const HEADER_MAX_LENGTH = 60;
    private const SECTION_TITLE_MAX_LENGTH = 24;
    private const ROW_TITLE_MAX_LENGTH = 20;
    private const ROW_DESCRIPTION_MAX_LENGTH = 72;

    public function __construct()
    {
        $this->graphApiToken = config('whatsapp.token');
        $this->endpoint = config('whatsapp.url');
    }

    // Send button template message with auto-generated numeric indices
    public function sendMessageWithButtons(string $businessPhoneNumberId, string $from, string $messageId, string $body, array $buttonsList)
    {
        $buttons = [];
        $index = 1; // Start from 1
        foreach ($buttonsList as $button) {
            // Handle both formats: direct text and array with 'text' key
            $title = is_array($button) ? ($button['text'] ?? '') : $button;
            
            // Calculate space needed for the numeric prefix (e.g., "1. ")
            $prefixLength = strlen($index . '. ');
            // Calculate remaining space for the actual title
            $maxTitleLength = self::BUTTON_TITLE_MAX_LENGTH - $prefixLength;
            // Truncate title if needed and ensure total length stays within limit
            $truncatedTitle = substr($title, 0, $maxTitleLength);
            
            $buttons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => (string)$index,
                    'title' => $index . '. ' . $truncatedTitle
                ]
            ];
            $index++;
            
            // Break if we've reached the maximum number of buttons
            if ($index > self::MAX_BUTTONS) {
                break;
            }
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $from,
            'recipient_type' => 'individual',
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => substr($body, 0, self::BODY_MAX_LENGTH)
                ],
                'action' => [
                    'buttons' => $buttons
                ]
            ]
        ];

        if ($messageId) {
            $payload['context'] = [
                'message_id' => $messageId,
            ];
        }

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
            'text' => ['body' => substr($text, 0, self::BODY_MAX_LENGTH)],
        ];

        if ($messageId) {
            $payload['context'] = ['message_id' => $messageId];
        }

        $response = Http::withToken($this->graphApiToken)
            ->timeout(30)
            ->post($this->endpoint . "/{$businessPhoneNumberId}/messages", $payload);

        return ($response->status() == 200);
    }

    /**
     * Send button template message with custom button IDs
     * Similar to sendMessageWithButtons but allows specifying custom IDs for buttons
     */
    public function sendMessageWithCustomButtons(string $businessPhoneNumberId, string $from, string $messageId, string $body, array $buttonsList)
    {
        $buttons = [];
        $index = 1; // For display numbering only
        foreach ($buttonsList as $button) {
            // Handle both formats: direct text and array with text/id
            if (is_array($button)) {
                $title = $button['text'] ?? '';
                $id = $button['id'] ?? (string)$index;
            } else {
                $title = $button;
                $id = (string)$index;
            }
            
            // Calculate space needed for the numeric prefix (e.g., "1. ")
            $prefixLength = strlen($index . '. ');
            // Calculate remaining space for the actual title
            $maxTitleLength = self::BUTTON_TITLE_MAX_LENGTH - $prefixLength;
            // Truncate title if needed and ensure total length stays within limit
            $truncatedTitle = substr($title, 0, $maxTitleLength);
            
            $buttons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $id,
                    'title' => $index . '. ' . $truncatedTitle
                ]
            ];
            $index++;
            
            // Break if we've reached the maximum number of buttons
            if ($index > self::MAX_BUTTONS) {
                break;
            }
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $from,
            'recipient_type' => 'individual',
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => substr($body, 0, self::BODY_MAX_LENGTH)
                ],
                'action' => [
                    'buttons' => $buttons
                ]
            ]
        ];

        if ($messageId) {
            $payload['context'] = [
                'message_id' => $messageId,
            ];
        }

        // Log the complete payload being sent to Meta
        Log::info('WhatsApp API Request Payload:', $payload);

        $response = Http::withToken($this->graphApiToken)
            ->timeout(30)
            ->post($this->endpoint . "/{$businessPhoneNumberId}/messages", $payload);

        // Log the response from Meta
        Log::info('WhatsApp API Response:', ['status' => $response->status(), 'body' => $response->json()]);

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
        $body = substr($body, 0, self::BODY_MAX_LENGTH);
        $footer = substr($footer, 0, self::FOOTER_MAX_LENGTH);

        foreach ($sections as &$section) {
            $section['title'] = substr($section['title'], 0, self::SECTION_TITLE_MAX_LENGTH);
            foreach ($section['rows'] as &$row) {
                $row['title'] = substr($row['title'], 0, self::ROW_TITLE_MAX_LENGTH);
                $row['description'] = substr($row['description'], 0, self::ROW_DESCRIPTION_MAX_LENGTH);
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
                        'text' => substr("Welcome to " . config('app.friendly_name'), 0, self::HEADER_MAX_LENGTH)
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
        // Apply length limits to menu sections
        $sections = [
            [
                'title' => substr("Account & Registration", 0, self::SECTION_TITLE_MAX_LENGTH),
                'rows' => [
                    [
                        'id' => "1",
                        'title' => substr("Register", 0, self::ROW_TITLE_MAX_LENGTH),
                        'description' => substr("Register for a new account", 0, self::ROW_DESCRIPTION_MAX_LENGTH)
                    ],
                    [
                        'id' => "4",
                        'title' => substr("Account Services", 0, self::ROW_TITLE_MAX_LENGTH),
                        'description' => substr("Balance inquiry, statements, and PIN management", 0, self::ROW_DESCRIPTION_MAX_LENGTH)
                    ]
                ]
            ],
            [
                'title' => substr("Transactions", 0, self::SECTION_TITLE_MAX_LENGTH),
                'rows' => [
                    [
                        'id' => "2",
                        'title' => substr("Money Transfer", 0, self::ROW_TITLE_MAX_LENGTH),
                        'description' => substr("Send money to bank accounts or mobile money", 0, self::ROW_DESCRIPTION_MAX_LENGTH)
                    ],
                    [
                        'id' => "3",
                        'title' => substr("Bill Payments", 0, self::ROW_TITLE_MAX_LENGTH),
                        'description' => substr("Pay your bills and utilities", 0, self::ROW_DESCRIPTION_MAX_LENGTH)
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
                        'text' => substr("Welcome to " . config('app.friendly_name'), 0, self::HEADER_MAX_LENGTH)
                    ],
                    'body' => [
                        'text' => substr($body, 0, self::BODY_MAX_LENGTH)
                    ],
                    'footer' => [
                        'text' => substr("Reply: 00 for menu, 000 to exit", 0, self::FOOTER_MAX_LENGTH)
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
