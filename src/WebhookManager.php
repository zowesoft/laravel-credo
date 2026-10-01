<?php

namespace ZoweSoft\LaravelCredo;

use Illuminate\Http\Request;
use ZoweSoft\LaravelCredo\Data\WebhookEvent;
use ZoweSoft\LaravelCredo\Exceptions\InvalidSignatureException;

/**
 * Verifies and parses inbound Credo webhooks.
 *
 * The X-Credo-Signature header is SHA-512 of secretKey + businessCode (a
 * plain hash, not an HMAC of the body). Validate with validate() or capture()
 * before trusting any payload, and respond HTTP 200 immediately; Credo
 * retries delivery up to 5 times when the endpoint does not respond.
 *
 * @see https://docs.credocentral.com/docs/developers/webhooks
 */
class WebhookManager
{
    /** Fired when a payment completes successfully. */
    public const EVENT_TRANSACTION_SUCCESSFUL = 'transaction.successful';

    /** Fired when a payment attempt fails. */
    public const EVENT_TRANSACTION_FAILED = 'transaction.failed';

    /** Fired when a bank transfer is reversed. */
    public const EVENT_TRANSFER_REVERSE = 'transaction.transaction.transfer.reverse';

    /** Fired when a merchant settlement is processed. */
    public const EVENT_SETTLEMENT_SUCCESS = 'transaction.settlement.success';

    /**
     * @param  CredoManager  $manager  Provides the secret key used for signature checks.
     */
    public function __construct(protected CredoManager $manager) {}

    /**
     * Verify an X-Credo-Signature header value: sha512(secretKey + businessCode).
     */
    public function verifySignature(string $signature, string $businessCode): bool
    {
        if ($this->manager->secretKey() === '' || $businessCode === '') {
            return false;
        }

        return hash_equals(
            $this->expectedSignature($businessCode),
            strtolower(trim($signature)),
        );
    }

    /**
     * Validate a webhook payload against its signature and return the event.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidSignatureException
     */
    public function validate(string $signature, array $payload): WebhookEvent
    {
        $event = WebhookEvent::fromArray($payload);

        $businessCode = $event->businessCode();

        if ($businessCode === null || ! $this->verifySignature($signature, $businessCode)) {
            throw InvalidSignatureException::signature();
        }

        return $event;
    }

    /**
     * Validate the current inbound request (body + X-Credo-Signature header).
     *
     * @throws InvalidSignatureException
     */
    public function capture(?string $content = null, ?string $signature = null): WebhookEvent
    {
        $content ??= $this->inboundContent();
        $signature ??= $this->inboundSignature();

        $payload = json_decode($content ?: '[]', true);

        if (! is_array($payload)) {
            throw InvalidSignatureException::malformedPayload();
        }

        return $this->validate((string) $signature, $payload);
    }

    /**
     * Compute the expected signature: SHA-512(secretKey + businessCode).
     */
    protected function expectedSignature(string $businessCode): string
    {
        return hash('sha512', $this->manager->secretKey().$businessCode);
    }

    /**
     * Raw body of the current inbound request.
     */
    protected function inboundContent(): ?string
    {
        return app('request')->getContent();
    }

    /**
     * X-Credo-Signature header of the current inbound request, or null when absent.
     */
    protected function inboundSignature(): ?string
    {
        /** @var Request $request */
        $request = app('request');

        return $request->header('X-Credo-Signature');
    }
}
