<?php
return [
    
    'channel' => [

        'google-chat-default' => [
            'level' => 'debug',
            'url' => env('LOG_GOOGLE_CHAT_WEBHOOK_URL', ''),
            'bubble' => true,
            'append-stack-channels' => true,
        ],

        'google-chat-other' => [
            'level' => 'debug',
            'url' => env('LOG_GOOGLE_CHAT_WEBHOOK_URL', ''),
            'bubble' => true,
            'append-stack-channels' => false,
        ]
    ],
];
