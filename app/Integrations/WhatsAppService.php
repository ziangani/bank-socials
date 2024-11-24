<?php

namespace App\Integrations;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    private string $graphApiToken;
    private string $endpoint;

    public function __construct()
    {
        $this->graphApiToken = config('whatsapp.token');
        $this->endpoint = config('whatsapp.url');
    }

    //Button Template
    //{
    //    "messaging_product": "whatsapp",
    //    "to": "260964926646",
    //    "recipient_type": "individual",
    //    "type": "interactive",
    //    "interactive": {
    //        "type": "button",
    //        "body": {
    //            "text": "Hi Charles! How can I help you today?"
    //        },
    //        "action": {
    //            "buttons": [
    //                {
    //                    "type": "reply",
    //                    "reply": {
    //                        "id": "makepayment",
    //                        "title": "Make payment"
    //                    }
    //                },
    //                {
    //                    "type": "reply",
    //                    "reply": {
    //                        "id": "checkstatement",
    //                        "title": "Last Zesco Token(s)"
    //                    }
    //                }
    //            ]
    //        }
    //    }
    //}

    //send button template message
    public function sendMessageWithButtons(string $businessPhoneNumberId, string $from, string $messageId, string $body, array $buttonsList)
    {
        $buttons = array_map(function ($id, $title) {
            return [
                'type' => 'reply',
                'reply' => [
                    'id' => $id,
                    'title' => substr($title, 0, 20)
                ]
            ];
        }, array_keys($buttonsList), $buttonsList);
        $buttons = array_slice($buttons, 0, 3);

        Http::withToken($this->graphApiToken)
            ->timeout(30)
            ->post($this->endpoint . "/{$businessPhoneNumberId}/messages", [
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
            ]);
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

    //send interactive menu message
    /*
        {
            "messaging_product": "whatsapp",
            "to": "260964926646",
            "recipient_type": "individual",
            "type": "interactive",
            "interactive": {
                "type": "list",
                "header": {
                    "type": "text",
                    "text": "Welcome to TechPay"
                },
                "body": {
                    "text": "Hi Charles!\nI am here to help you make payments to the Council via Mobile Money & Credit/Debit Card.\nGet started by selecting an option below"
                },
                "footer": {
                    "text": "Powered by TechPay"
                },
                "action": {
                    "button": "Get Started",
                    "sections": [
                        {
                            "title": "Make payment",
                            "rows": [
                                {
                                    "id": "makepayment",
                                    "title": "Make payment",
                                    "description": "Make payment via Mobile Money or Credit/Debit Card"
                                }
                            ]
                        },
                        {
                            "title": "View your history",
                            "rows": [
                                {
                                    "id": "checkstatement",
                                    "title": "Check statement",
                                    "description": "View your account statement"
                                },
                                {
                                    "id": "checkhistory",
                                    "title": "Check history",
                                    "description": "View your transaction history"
                                }
                            ]
                        }
                    ]
                }
            }
        }

     */
    public function sendWelcomeMenu(string $businessPhoneNumberId, string $from, string $messageId, string $body)
    {
        $sections = [
            [
                'title' => "Make payment",
                'rows' => [
                    [
                        'id' => "Make payment",
                        'title' => "Make payment",
                        'description' => "Make payment via Mobile Money or Credit/Debit Card"
                    ]
                ]
            ],
            [
                'title' => "Access your history",
                'rows' => [
                    [
                        'id' => "Check statement",
                        'title' => "View Account Statement",
                        'description' => "Access your account statement"
                    ],
                    [
                        'id' => "Check history",
                        'title' => "View Transaction History",
                        'description' => "Review your past transactions"
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
                        'text' => "Powered by " . config('app.powered_by')
                    ],
                    'action' => [
                        'button' => "Get Started",
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

    public function sendDocument(string $businessPhoneNumberId, string $from, string $messageId, string $caption, string $filename, string $link)
    {

        $res = Http::withToken($this->graphApiToken)
            ->timeout(30)
            ->post($this->endpoint . "/{$businessPhoneNumberId}/messages", [
                'recipient_type' => 'individual',
                'messaging_product' => 'whatsapp',
                'to' => $from,
                'type' => 'document',
                'document' => [
                    'caption' => $caption,
                    'filename' => $filename,
                    'link' => $link
                ],
                'context' => [
                    'message_id' => $messageId,
                ],
            ]);
        return $res->json();
    }

    public function sendBillerMenu(string $businessPhoneNumberId, string $from, string $messageId, string $body)
    {
        $sections = [
            [
                'title' => "Airtime Purchase",
                'rows' => [
                    [
                        'id' => "Airtime",
                        'title' => "Direct Top-up",
                        'description' => "Purchase airtime for on Airtel, MTN or Zamtel"
                    ],
                ]
            ],
//            [
//                'title' => "Zesco Token Purchase",
//                'rows' => [
//                    [
//                        'id' => "Zesco",
//                        'title' => "Zesco",
//                        'description' => "Purchase Zesco tokens"
//                    ]
//                ]
//            ],
            [
                'title' => "TV Subscriptions",
                'rows' => [
                    [
                        'id' => "DStv",
                        'title' => "DStv",
                        'description' => "Top-up your DStv account"
                    ],
                    [
                        'id' => "GOtv",
                        'title' => "GOtv",
                        'description' => "Top-up your GOtv account"
                    ],
                    [
                        'id' => "TopStar",
                        'title' => "TopStar",
                        'description' => "Top-up your TopStar account"
                    ]
                ]
            ],
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
                        'text' => "Welcome to PayEasy"
                    ],
                    'body' => [
                        'text' => $body
                    ],
                    'footer' => [
                        'text' => "Powered by " . config('app.powered_by')
                    ],
                    'action' => [
                        'button' => "Get Started",
                        'sections' => $sections
                    ]
                ],
                'context' => [
                    'message_id' => $messageId,
                ],
            ]);
        if ($res->status() != 200)
            throw new \Exception('Failed to send biller menu' . $res->body());
    }

    public function sendZescoWelcomeMenu(string $businessPhoneNumberId, string $from, string $messageId, string $body)
    {
        $sections = [
            [
                'title' => "Make payment",
                'rows' => [
                    [
                        'id' => "Make payment",
                        'title' => "Pre-Paid Payment",
                        'description' => "Make payment via Mobile Money or Credit/Debit Card"
                    ],
                    [
                        'id' => "Make ppayment",
                        'title' => "Post Paid Payment",
                        'description' => "Make payment via Mobile Money or Credit/Debit Card"
                    ]
                ]
            ],
            [
                'title' => "Access your history",
                'rows' => [
                    [
                        'id' => "Check statement",
                        'title' => "View Last Token",
                        'description' => "Access your account statement"
                    ],
                    [
                        'id' => "Check history",
                        'title' => "View Transaction History",
                        'description' => "Review your past transactions"
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
                        'text' => "Welcome to ZESCO Limited"
                    ],
                    'body' => [
                        'text' => $body
                    ],
                    'footer' => [
                        'text' => "Powered by " . config('app.powered_by')
                    ],
                    'action' => [
                        'button' => "Get Started",
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
