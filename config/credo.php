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

    /*
    |--------------------------------------------------------------------------
    | Retries (Rate Limits & Network Failures)
    |--------------------------------------------------------------------------
    |
    | Credo's API docs recommend retrying with exponential backoff when you
    | are rate limited (HTTP 429) or when the connection itself fails
    | (timeout, DNS error, connection reset). Other client errors (401, 403,
    | 404, 422) are never retried - they fail immediately as a
    | RequestFailedException.
    |
    | retry_max_attempts: total attempts per request (1 disables retries).
    | retry_base_delay_ms: delay before the first retry; each further retry
    | doubles it (1s, 2s, 4s for the 1000ms default).
    |
    */

    'retry_max_attempts' => env('CREDO_RETRY_MAX_ATTEMPTS', 3),
    'retry_base_delay_ms' => env('CREDO_RETRY_BASE_DELAY_MS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    |
    | Opt in to logging every API call - method, path, HTTP status, duration
    | in milliseconds, attempt count and, when known, the Credo transaction
    | reference (transRef). Set this to a log channel name such as "daily"
    | or a dedicated "credo" channel from config/logging.php, or leave it
    | null (CREDO_LOG_CHANNEL=null) to disable logging entirely.
    |
    */

    'log_channel' => env('CREDO_LOG_CHANNEL'),

];
