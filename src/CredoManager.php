<?php

namespace ZoweSoft\LaravelCredo;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Traits\ForwardsCalls;
use Psr\Log\LoggerInterface;
use ZoweSoft\LaravelCredo\Contracts\PaymentGateway;
use ZoweSoft\LaravelCredo\Data\Transaction;
use ZoweSoft\LaravelCredo\Exceptions\InvalidConfigurationException;
use ZoweSoft\LaravelCredo\Exceptions\RequestFailedException;
use ZoweSoft\LaravelCredo\Facades\Credo;
use ZoweSoft\LaravelCredo\Responses\InitializeResponse;
use ZoweSoft\LaravelCredo\Support\Retry;

/**
 * Central manager / service container entry point for the Credo payment
 * integration. Bound as a singleton under the `credo` alias and exposed
 * via the {@see Credo} facade.
 *
 * Holds all configuration accessors (mode, keys, URLs, timeouts, retry
 * settings, logging), lazily creates the {@see Client} and
 * {@see WebhookManager} instances, and satisfies the {@see PaymentGateway}
 * contract so callers can type-hint the interface instead of the concrete
 * class.
 *
 * @see https://docs.credocentral.com/docs/developers/accept-payments (Payment flow)
 * @see https://docs.credocentral.com/docs/developers/authentication (API keys)
 */
class CredoManager implements PaymentGateway
{
    use ForwardsCalls;

    protected ?Client $client = null;

    protected ?Container $app = null;

    /**
     * @param  Repository  $config  Application config repository (credo.* keys).
     * @param  Factory  $http  The shared HTTP factory (the instance Http::fake() mutates).
     * @param  Container|null  $app  Application container, used to resolve log channels; optional for manual instantiation.
     */
    public function __construct(
        protected Repository $config,
        protected Factory $http,
        ?Container $app = null,
    ) {
        $this->app = $app;
    }

    /**
     * Returns the active mode string: `'LIVE'` or `'DEMO'`.
     */
    public function mode(): string
    {
        return $this->isLiveMode() ? 'LIVE' : 'DEMO';
    }

    /**
     * Whether the integration is running against the live Credo API.
     */
    public function isLiveMode(): bool
    {
        return strtoupper((string) $this->config->get('credo.mode', 'DEMO')) === 'LIVE';
    }

    /**
     * Active mode base URL (trailing slash stripped).
     * Reads `credo.base_urls.{mode}` from config and falls back to the
     * Credo defaults: `https://api.credocentral.com` (live) or
     * `https://api.credodemo.com` (demo).
     */
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

    /**
     * Configured public key (trimmed), or an empty string when not set.
     * Used for initializing transactions (client-safe).
     *
     * @see https://docs.credocentral.com/docs/concepts#api-keys (API keys)
     */
    public function publicKey(): string
    {
        $key = $this->config->get('credo.public_key');

        return is_string($key) ? trim($key) : '';
    }

    /**
     * Configured secret key (trimmed), or an empty string when not set.
     * Used for verifying transactions and webhook signatures. Server-side only.
     *
     * @see https://docs.credocentral.com/docs/concepts#api-keys (API keys)
     */
    public function secretKey(): string
    {
        $key = $this->config->get('credo.secret_key');

        return is_string($key) ? trim($key) : '';
    }

    /**
     * Whether key-prefix validation is enabled (`credo.validate_keys`).
     * When `true`, each API call checks that the configured keys start with
     * the prefix expected for the active mode (e.g. `0PUB`/`0PRI` for demo).
     */
    public function validateKeys(): bool
    {
        return (bool) $this->config->get('credo.validate_keys', true);
    }

    /**
     * Assert that both API keys are present and match the expected prefix for
     * the active mode. Called automatically before every outgoing request.
     *
     * @throws InvalidConfigurationException
     *                                       When a key is missing or has the wrong prefix.
     */
    public function verifyConfiguration(): void
    {
        if ($this->publicKey() === '' || $this->secretKey() === '') {
            throw InvalidConfigurationException::missingKeys($this->mode());
        }

        $this->assertKeyFormat($this->publicKey(), $this->keyPrefix('public'), 'public');
        $this->assertKeyFormat($this->secretKey(), $this->keyPrefix('secret'), 'secret');
    }

    /**
     * Expected key prefix for the given role ("public" or "secret") in the
     * active mode, or an empty string when not configured.
     */
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

    /**
     * HTTP request timeout in seconds (`credo.timeout`, default 30).
     */
    public function timeout(): int
    {
        return (int) $this->config->get('credo.timeout', 30);
    }

    /**
     * Total attempts per request (including the first). 1 disables retries.
     */
    public function retryMaxAttempts(): int
    {
        return max(1, (int) $this->config->get('credo.retry_max_attempts', 3));
    }

    /**
     * Delay in milliseconds before the first retry; doubles on each further retry.
     */
    public function retryBaseDelayMs(): int
    {
        return max(0, (int) $this->config->get('credo.retry_base_delay_ms', 1000));
    }

    /**
     * Optional PSR-3 logger for request logging, or null when disabled.
     */
    public function logger(): ?LoggerInterface
    {
        $channel = $this->config->get('credo.log_channel');

        if (! is_string($channel) || trim($channel) === '' || strcasecmp($channel, 'null') === 0) {
            return null;
        }

        return $this->app?->make('log')->channel($channel);
    }

    /**
     * The underlying config repository (Laravel's `config()` service).
     */
    public function config(): Repository
    {
        return $this->config;
    }

    /**
     * The HTTP client factory used to build requests.
     */
    public function http(): Factory
    {
        return $this->http;
    }

    /**
     * Lazily instantiated HTTP client. Use {@see usingClient()} in tests to
     * inject a mock.
     */
    public function client(): Client
    {
        return $this->client ??= new Client($this);
    }

    /**
     * Replace the internal client — useful in tests to inject a mock or fake.
     */
    public function usingClient(Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Start building a payment with the fluent {@see PaymentBuilder}.
     *
     * @see https://docs.credocentral.com/docs/developers/accept-payments#step-1-initialize-a-transaction
     */
    public function payment(): PaymentBuilder
    {
        return new PaymentBuilder($this);
    }

    /**
     * Initialize a payment session and receive a checkout URL.
     * Accepts a fluent {@see PaymentBuilder} or a raw payload array.
     *
     * @param  PaymentBuilder|array<string, mixed>  $payment
     *
     * @throws InvalidConfigurationException
     * @throws RequestFailedException
     * @throws ConnectionException
     *
     * @see https://docs.credocentral.com/docs/developers/accept-payments#step-1-initialize-a-transaction
     */
    public function initialize(PaymentBuilder|array $payment): InitializeResponse
    {
        $payload = $payment instanceof PaymentBuilder ? $payment->toArray() : $payment;

        return $this->client()->initialize($payload);
    }

    /**
     * Fetch the authoritative server-side status of a transaction.
     * Always verify server-side; never trust the callback redirect alone.
     *
     * @param  string  $reference  The Credo transRef (from the initialize response or webhook).
     *
     * @throws InvalidConfigurationException
     * @throws RequestFailedException
     * @throws ConnectionException
     *
     * @see https://docs.credocentral.com/docs/developers/accept-payments#step-3-verify-the-transaction
     */
    public function verify(string $reference): Transaction
    {
        return $this->client()->verify($reference);
    }

    /**
     * Create a {@see WebhookManager} for verifying incoming Credo webhooks.
     *
     * @see https://docs.credocentral.com/docs/concepts#webhooks (Webhooks)
     * @see https://docs.credocentral.com/docs/developers/webhooks
     */
    public function webhooks(): WebhookManager
    {
        return new WebhookManager($this);
    }

    /**
     * Forward any unknown method calls to the underlying {@see Client},
     * making the manager a transparent proxy for low-level HTTP operations.
     *
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->forwardCallTo($this->client(), $method, $parameters);
    }
}
