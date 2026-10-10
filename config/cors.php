<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | 公開チャットボットAPI (api/public/chatbot/*) は静的サイト（website/）から
    | 直接呼び出されるため、config('chatbot.public.allowed_origins') のオリジンを許可する。
    | その他のAPIは同一オリジン運用を想定。
    |
    */

    'paths' => ['api/public/chatbot/*', 'api/site-forms/*', 'api/trials/*', 'api/bookings'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_merge(
        ['http://localhost', 'http://localhost:8080', 'http://127.0.0.1:8080'],
        config('chatbot.public.allowed_origins', []),
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
