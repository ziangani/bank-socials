<?php

return [

    'url' => env('GRAPH_API_ENDPOINT', 'https://graph.facebook.com/v19.0'),
    'token' => env('WHATSAPP_TOKEN', ''),
    'throw_http_exceptions' => true,
    'business_phone_id' => env('WHATSAPP_BUSINESS_PHONE_ID', '516038201587401')
];
