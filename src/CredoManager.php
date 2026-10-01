<?php

namespace ZoweSoft\LaravelCredo;

use Illuminate\Config\Repository;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Traits\ForwardsCalls;
use ZoweSoft\LaravelCredo\Contracts\PaymentGateway;
use ZoweSoft\LaravelCredo\Data\Transaction;
use ZoweSoft\LaravelCredo\Exceptions\InvalidConfigurationException;
use ZoweSoft\LaravelCredo\Responses\InitializeResponse;

class CredoManager implements PaymentGateway
{
    use ForwardsCalls;

    protected ?Client $client = null;

    public function __construct(
        protected Repository $config,
        protected Factory $http,
    ) {}

    public function mode(): string
    {
        return $this->isLiveMode() ? 'LIVE' : 'DEMO';
    }

    public function isLiveMode(): bool
    {
        return strtoupper((string) $this->config->get('credo.mode', 'DEMO')) === 'LIVE';
    }

    public function baseUrl(): string
    {
        $configured = $this->config->get('credo.base_urls.'.strtolower($this->mode()));

        if (is_string($configured) && trim($configured) !== '') {
            return rtrim(trim($configured), '/');
        }

        return $this->isLiveMode()
            ? 'https://api.credocentral.com'
            : 'https://api.credodemo.com';
    }

    public function publicKey(): string
    {
        $key = $this->config->get('credo.public_key');

        return is_string($key) ? trim($key) : '';
    }

    public function secretKey(): string
    {
        $key = $this->config->get('credo.secret_key');

        return is_string($key) ? trim($key) : '';
    }

    public function validateKeys(): bool
    {
        return (bool) $this->config->get('credo.validate_keys', true);
    }

    public function verifyConfiguration(): void
    {
        if ($this->publicKey() === '' || $this->secretKey() === '') {
            throw InvalidConfigurationException::missingKeys($this->mode());
        }

        $this->assertKeyFormat($this->publicKey(), $this->keyPrefix('public'), 'public');
        $this->assertKeyFormat($this->secretKey(), $this->keyPrefix('secret'), 'secret');
    }

    protected function keyPrefix(string $role): string
    {
        $prefix = $this->config->get('credo.key_prefixes.'.strtolower($this->mode()).".{$role}");

        return is_string($prefix) ? $prefix : '';
    }

    protected function assertKeyFormat(string $key, string $prefix, string $role): void
    {
        if (! $this->validateKeys() || $prefix === '' || str_starts_with($key, $prefix)) {
            return;
        }

        throw InvalidConfigurationException::invalidKeyPrefix($role, $prefix, $this->mode());
    }

    public function timeout(): int
    {
        return (int) $this->config->get('credo.timeout', 30);
    }

    public function config(): Repository
    {
        return $this->config;
    }

    public function http(): Factory
    {
        return $this->http;
    }

    public function client(): Client
    {
        return $this->client ??= new Client($this);
    }

    public function usingClient(Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function payment(): PaymentBuilder
    {
        return new PaymentBuilder($this);
    }

    public function initialize(PaymentBuilder|array $payment): InitializeResponse
    {
        $payload = $payment instanceof PaymentBuilder ? $payment->toArray() : $payment;

        return $this->client()->initialize($payload);
    }

    public function verify(string $reference): Transaction
    {
        return $this->client()->verify($reference);
    }

    public function webhooks(): WebhookManager
    {
        return new WebhookManager($this);
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->forwardCallTo($this->client(), $method, $parameters);
    }
}
