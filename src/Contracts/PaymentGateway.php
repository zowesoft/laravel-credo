<?php

namespace ZoweSoft\LaravelCredo\Contracts;

use Illuminate\Http\Client\ConnectionException;
use ZoweSoft\LaravelCredo\CredoManager;
use ZoweSoft\LaravelCredo\CredoServiceProvider;
use ZoweSoft\LaravelCredo\Data\Transaction;
use ZoweSoft\LaravelCredo\Exceptions\InvalidConfigurationException;
use ZoweSoft\LaravelCredo\Exceptions\RequestFailedException;
use ZoweSoft\LaravelCredo\PaymentBuilder;
use ZoweSoft\LaravelCredo\Responses\InitializeResponse;
use ZoweSoft\LaravelCredo\WebhookManager;

/**
 * Credo payment gateway contract.
 *
 * Type-hint this interface instead of the concrete {@see CredoManager}
 * for easier testing and swap-ability. The interface is bound in the container
 * by {@see CredoServiceProvider}.
 *
 * @see https://docs.credocentral.com/docs/developers/accept-payments
 */
interface PaymentGateway
{
    /**
     * Start building a payment with the fluent builder.
     */
    public function payment(): PaymentBuilder;

    /**
     * Initialize a Credo payment session.
     *
     * @param  PaymentBuilder|array<string, mixed>  $payment
     *
     * @throws InvalidConfigurationException
     * @throws RequestFailedException
     * @throws ConnectionException
     */
    public function initialize(PaymentBuilder|array $payment): InitializeResponse;

    /**
     * Verify a transaction by its Credo reference (transRef).
     *
     * @throws InvalidConfigurationException
     * @throws RequestFailedException
     * @throws ConnectionException
     */
    public function verify(string $reference): Transaction;

    /**
     * Create a WebhookManager for signature verification and payload parsing.
     */
    public function webhooks(): WebhookManager;

    /**
     * Returns the active mode string: `'LIVE'` or `'DEMO'`.
     */
    public function mode(): string;

    /**
     * Whether the integration is pointed at the live Credo API.
     */
    public function isLiveMode(): bool;
}
