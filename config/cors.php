<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'register'],

    'allowed_methods' => ['*'],

    // IMPORTANT: When supports_credentials is true, cannot use '*'
    // Must specify exact origins
    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175',
        'https://frizerino.com',
        'https://www.frizerino.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
