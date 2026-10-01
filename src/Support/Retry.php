<?php

namespace ZoweSoft\LaravelCredo\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use ZoweSoft\LaravelCredo\CredoManager;

/**
 * Retry helper implementing the backoff strategy recommended by Credo's
 * API documentation: exponential delays (base, 2x, 4x...) on HTTP 429
 * (rate limited) and connection failures, never on other client errors.
 *
 * @see https://docs.credocentral.com/docs/developers/error-handling#http-status-codes (HTTP status codes)
 */
class Retry
{
    /**
     * Run the request, retrying with exponential backoff on HTTP 429 (rate
     * limited) and on connection failures, as recommended by Credo's API
     * documentation. Any other response is returned untouched, so client
     * errors such as 401/403/404/422 still fail fast.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $request  Performs one attempt; may return any value or throw.
     * @param  CredoManager  $manager  Provides retry_max_attempts and retry_base_delay_ms.
     * @param  (callable(int): void)|null  $onAttempt  Invoked before each attempt with the 1-indexed attempt number.
     * @return TReturn The value returned by the last (non-retried) call.
     *
     * @throws ConnectionException When every attempt fails to connect.
     */
    public static function attempt(callable $request, CredoManager $manager, ?callable $onAttempt = null): mixed
    {
        $maxAttempts = $manager->retryMaxAttempts();
        $baseDelayMs = $manager->retryBaseDelayMs();

        for ($attempt = 1; ; $attempt++) {
            if ($onAttempt !== null) {
                $onAttempt($attempt);
            }
            try {
                $response = $request();

                $shouldRetry = $response instanceof Response
                    && $response->status() === 429
                    && $attempt < $maxAttempts;
            } catch (ConnectionException $exception) {
                if ($attempt >= $maxAttempts) {
                    throw $exception;
                }

                static::backoff($baseDelayMs, $attempt);

                continue;
            }

            if ($shouldRetry) {
                static::backoff($baseDelayMs, $attempt);

                continue;
            }

            return $response;
        }
    }

    /**
     * Sleep for the exponential backoff delay before the given retry (1-indexed).
     */
    protected static function backoff(int $baseDelayMs, int $attempt): void
    {
        usleep(self::delayFor($baseDelayMs, $attempt));
    }

    /**
     * Delay in microseconds before the given retry (1-indexed): base, 2x, 4x...
     * Matches Credo's documented guidance of 1s, 2s, 4s for a 1000ms base.
     *
     * @see https://docs.credocentral.com/docs/developers/error-handling#http-status-codes (Rate limiting — 429)
     */
    public static function delayFor(int $baseDelayMs, int $attempt): int
    {
        return $baseDelayMs * (2 ** ($attempt - 1)) * 1000;
    }
}
