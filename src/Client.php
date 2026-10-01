<?php

namespace ZoweSoft\LaravelCredo;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use ZoweSoft\LaravelCredo\Data\Transaction;
use ZoweSoft\LaravelCredo\Exceptions\InvalidConfigurationException;
use ZoweSoft\LaravelCredo\Exceptions\RequestFailedException;
use ZoweSoft\LaravelCredo\Responses\InitializeResponse;
use ZoweSoft\LaravelCredo\Support\Retry;

/**
 * Low-level HTTP client for the Credo Payment API.
 *
 * Builds requests against the active mode's base URL, applies retry with
 * exponential backoff on 429/connection failures, optional request logging,
 * and finally normalizes Credo's response envelope into typed DTOs (throwing
 * RequestFailedException for anything that is not a valid success).
 *
 * Prefer the manager (or the Credo facade) over using this class directly.
 *
 * @see https://docs.credocentral.com/docs/developers/accept-payments
 * @see https://docs.credocentral.com/docs/developers/authentication
 */
class Client
{
    /**
     * @param  CredoManager  $manager  Configuration and HTTP factory source.
     */
    public function __construct(protected CredoManager $manager) {}

    /**
     * Create a payment session and get a checkout URL to redirect the customer to.
     *
     * Authenticates with the PUBLIC key (client-safe), applies the configured
     * default callback URL when the payload omits one, and defaults the fee
     * bearer to the customer. Retries 429/connection failures per config.
     *
     * @param  array<string, mixed>  $payload  Initialize fields; see the Credo
     *                                         docs for the full list (amount in lowest unit, email, currency,
     *                                         bearer, channels, reference, callbackUrl, customer fields,
     *                                         metadata, serviceCode/splitConfiguration, pauseSettlement...).
     *
     * @throws InvalidConfigurationException When keys are missing or malformed.
     * @throws RequestFailedException When Credo rejects the request.
     * @throws ConnectionException When every attempt fails to connect.
     *
     * @see https://docs.credocentral.com/docs/reference/transactions/initializeTransaction
     */
    public function initialize(array $payload): InitializeResponse
    {
        $this->manager->verifyConfiguration();

        $payload = $this->applyDefaultCallbackUrl($payload);

        if (! array_key_exists('bearer', $payload)) {
            $payload['bearer'] = 0;
        }

        $response = $this->logged('POST', '/transaction/initialize', function (callable $onAttempt) use ($payload) {
            return Retry::attempt(
                fn () => $this->request()
                    ->withHeaders(['Authorization' => $this->manager->publicKey()])
                    ->post('/transaction/initialize', $payload),
                $this->manager,
                $onAttempt,
            );
        }, fn (Response $response) => $response->json('data.credoReference') ?? $response->json('data.transRef'));

        return $this->hydrate($response, fn (array $data) => InitializeResponse::fromArray($data, $payload));
    }

    /**
     * Fetch the authoritative server-side status of a transaction.
     *
     * Authenticates with the SECRET key. Never trust the callback redirect
     * alone; check the result with Transaction::matches() before fulfilling
     * an order.
     *
     * @param  string  $reference  The Credo transaction reference (transRef from
     *                             the initialize response or webhook payload) — not the businessRef.
     *
     * @throws InvalidConfigurationException When keys are missing or malformed.
     * @throws RequestFailedException When Credo rejects the request (e.g. 404 unknown reference).
     * @throws ConnectionException When every attempt fails to connect.
     *
     * @see https://docs.credocentral.com/docs/developers/accept-payments (Step 3: Verify the transaction)
     */
    public function verify(string $reference): Transaction
    {
        $this->manager->verifyConfiguration();

        $response = $this->logged('GET', "/transaction/{$reference}/verify", function (callable $onAttempt) use ($reference) {
            return Retry::attempt(
                fn () => $this->request()
                    ->withHeaders(['Authorization' => $this->manager->secretKey()])
                    ->get("/transaction/{$reference}/verify"),
                $this->manager,
                $onAttempt,
            );
        }, fn () => $reference);

        return $this->hydrate($response, fn (array $data) => Transaction::fromArray($data));
    }

    /**
     * Fill in credo.callback_url when the payload does not carry its own
     * callbackUrl; an explicit per-payment value always wins.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function applyDefaultCallbackUrl(array $payload): array
    {
        $hasCallback = isset($payload['callbackUrl']) && is_string($payload['callbackUrl']) && trim($payload['callbackUrl']) !== '';

        if ($hasCallback) {
            return $payload;
        }

        $default = $this->manager->config()->get('credo.callback_url');

        if (is_string($default) && trim($default) !== '') {
            $payload['callbackUrl'] = trim($default);
        }

        return $payload;
    }

    /**
     * Build a JSON request against the active mode's base URL with the
     * configured timeout.
     */
    protected function request(): PendingRequest
    {
        return $this->manager->http()
            ->baseUrl($this->manager->baseUrl())
            ->timeout($this->manager->timeout())
            ->acceptJson();
    }

    /**
     * Time the call and, when a log channel is configured, write one info
     * record per finished call (method, path, status, duration, attempts,
     * transRef when known) and one error record if the connection itself
     * failed on every attempt. Requests are untouched when logging is off.
     *
     * @param  string  $method  HTTP method, for the log record only.
     * @param  string  $path  Request path, for the log record only.
     * @param  callable(callable): Response  $call  Executes the (retried) request; receives the attempt counter callback.
     * @param  callable(Response): (string|null)|null  $transRefFrom  Extracts the transRef for the log record from the final response.
     */
    protected function logged(string $method, string $path, callable $call, ?callable $transRefFrom = null): Response
    {
        $logger = $this->manager->logger();

        if ($logger === null) {
            return $call(fn () => null);
        }

        $startedAt = microtime(true);
        $attempts = 0;

        try {
            $response = $call(function () use (&$attempts) {
                $attempts++;
            });
        } catch (ConnectionException $exception) {
            $logger->error('Credo API connection failed', [
                'method' => $method,
                'path' => $path,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'attempts' => max(1, $attempts),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $transRef = $transRefFrom === null ? null : $transRefFrom($response);

        $logger->info('Credo API request', [
            'method' => $method,
            'path' => $path,
            'status' => $response->status(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'attempts' => max(1, $attempts),
            'transRef' => is_string($transRef) && $transRef !== '' ? $transRef : null,
        ]);

        return $response;
    }

    /**
     * Normalize Credo's response envelope ({status: 200, data: {...}}) into a
     * typed object via $map, or throw RequestFailedException carrying the
     * HTTP status, Credo status and error list.
     *
     * @param  callable(array<string, mixed>): mixed  $map
     *
     * @throws RequestFailedException
     */
    protected function hydrate(Response $response, callable $map): mixed
    {
        $body = $response->json();

        if (
            $response->successful()
            && is_array($body)
            && (int) ($body['status'] ?? 0) === 200
            && is_array($body['data'] ?? null)
        ) {
            return $map($body['data']);
        }

        throw RequestFailedException::fromResponse($response);
    }
}
