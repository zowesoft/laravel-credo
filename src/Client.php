<?php

namespace ZoweSoft\LaravelCredo;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use ZoweSoft\LaravelCredo\Data\Transaction;
use ZoweSoft\LaravelCredo\Exceptions\RequestFailedException;
use ZoweSoft\LaravelCredo\Responses\InitializeResponse;
use ZoweSoft\LaravelCredo\Support\Retry;

class Client
{
    public function __construct(protected CredoManager $manager) {}

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
     * @param  callable(callable): Response  $call
     * @param  callable(Response): (string|null)|null  $transRefFrom
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
