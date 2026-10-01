<?php

namespace ZoweSoft\LaravelCredo;

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

        $response = Retry::attempt(
            fn () => $this->request()
                ->withHeaders(['Authorization' => $this->manager->publicKey()])
                ->post('/transaction/initialize', $payload),
            $this->manager,
        );

        return $this->hydrate($response, fn (array $data) => InitializeResponse::fromArray($data, $payload));
    }

    public function verify(string $reference): Transaction
    {
        $this->manager->verifyConfiguration();

        $response = Retry::attempt(
            fn () => $this->request()
                ->withHeaders(['Authorization' => $this->manager->secretKey()])
                ->get("/transaction/{$reference}/verify"),
            $this->manager,
        );

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
