<?php

namespace ZoweSoft\LaravelCredo\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use ZoweSoft\LaravelCredo\CredoManager;

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
     * @param  callable(): TReturn  $request
     * @return TReturn
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

    protected static function backoff(int $baseDelayMs, int $attempt): void
    {
        usleep(self::delayFor($baseDelayMs, $attempt));
    }

    /**
     * Delay in microseconds before the given retry (1-indexed): base, 2x, 4x, ...
     */
    public static function delayFor(int $baseDelayMs, int $attempt): int
    {
        return $baseDelayMs * (2 ** ($attempt - 1)) * 1000;
    }
}
