<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credo Mode
    |--------------------------------------------------------------------------
    |
    | "DEMO" talks to https://api.credodemo.com and "LIVE" to
    | https://api.credocentral.com. The same key pair is used for both;
    | swap the keys in your .env when you go live.
    |
    */

    'mode' => env('CREDO_MODE', 'DEMO'),

    /*
    |--------------------------------------------------------------------------
    | API Keys
    |--------------------------------------------------------------------------
    |
    | One pair per environment. The key format reveals the environment:
    | demo/test keys start with "0PUB" (public) / "0PRI" (secret) while live
    | keys start with "1PUB" / "1PRI". The package validates these prefixes
    | against the configured mode before every API call (see validate_keys).
    |
    */

    'public_key' => env('CREDO_PUBLIC_KEY'),
    'secret_key' => env('CREDO_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | API Base URLs
    |--------------------------------------------------------------------------
    |
    | Override these if Credo ever changes their endpoints.
    |
    */

    'base_urls' => [
        'demo' => env('CREDO_DEMO_BASE_URL', 'https://api.credodemo.com'),
        'live' => env('CREDO_LIVE_BASE_URL', 'https://api.credocentral.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Key Validation
    |--------------------------------------------------------------------------
    |
    | Before every API call the package checks that the configured public key
    | starts with the expected public prefix for the active mode and the
    | secret key with the secret prefix (demo: 0PUB/0PRI, live: 1PUB/1PRI).
    | This catches swapped or wrong-environment keys early. Set
    | CREDO_VALIDATE_KEYS=false to disable the check entirely.
    |
    */

    'validate_keys' => env('CREDO_VALIDATE_KEYS', true),

    /*
    | Expected key prefixes per mode. Demo keys start with "0PUB"/"0PRI",
    | live keys with "1PUB"/"1PRI". Override these (or blank an entry to
    | skip that check) if Credo ever changes their format.
    |
    */

    'key_prefixes' => [
        'demo' => [
            'public' => '0PUB',
            'secret' => '0PRI',
        ],
        'live' => [
            'public' => '1PUB',
            'secret' => '1PRI',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Callback URL
    |--------------------------------------------------------------------------
    |
    | Optional. Used when a payment built with the fluent builder does not
    | set its own callbackUrl.
    |
    */

    'callback_url' => env('CREDO_CALLBACK_URL'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds before a request to the Credo API times out.
    |
    */

    'timeout' => env('CREDO_TIMEOUT', 30),

];
