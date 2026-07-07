<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CORS Configuration — buddy-script
    |--------------------------------------------------------------------------
    | Allows the React frontend (running on localhost:5173 in dev or the
    | production domain) to communicate with the Laravel API.
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false, // Using token auth, not cookie-based SPA
];
