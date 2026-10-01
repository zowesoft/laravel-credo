<?php

namespace ZoweSoft\LaravelCredo\Data;

use ZoweSoft\LaravelCredo\WebhookManager;

/**
 * Parsed representation of an inbound Credo webhook request.
 *
 * Returned by {@see WebhookManager::capture()} and
 * {@see WebhookManager::validate()} after signature
 * verification has passed.
 *
 * @see https://docs.credocentral.com/docs/developers/webhooks
 */
class WebhookEvent
{
    /**
     * @param  string  $event  Raw event name, e.g. 'transaction.successful'.
     *                         Compare against the {@see WebhookManager}::EVENT_* constants
     *                         or use the typed helper methods (isSuccessful, isFailed, …).
     * @param  array<string, mixed>  $data  The `data` sub-object from the webhook payload
     *                                      (cast to a {@see Transaction} via {@see self::transaction()}).
     * @param  array<string, mixed>  $payload  Full raw webhook payload for forward-compatibility.
     */
    public function __construct(
        public readonly string $event,
        public readonly array $data,
        public readonly array $payload = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            event: (string) ($payload['event'] ?? ''),
            data: is_array($payload['data'] ?? null) ? $payload['data'] : [],
            payload: $payload,
        );
    }

    /**
     * The merchant's business code extracted from the webhook data.
     *
     * Used internally during signature verification. Returns null if the
     * field is absent (which would cause verification to fail).
     */
    public function businessCode(): ?string
    {
        return isset($this->data['businessCode']) ? (string) $this->data['businessCode'] : null;
    }

    /**
     * Parse the webhook's `data` sub-object into a typed {@see Transaction}.
     *
     * The same amounts-in-major-units caveat applies as on a verify response.
     */
    public function transaction(): Transaction
    {
        return Transaction::fromArray($this->data);
    }

    /**
     * Whether this event is `transaction.successful`.
     */
    public function isSuccessful(): bool
    {
        return $this->event === WebhookManager::EVENT_TRANSACTION_SUCCESSFUL;
    }

    /**
     * Whether this event is `transaction.failed`.
     */
    public function isFailed(): bool
    {
        return $this->event === WebhookManager::EVENT_TRANSACTION_FAILED;
    }

    /**
     * Whether this event is `transaction.settlement.success`.
     */
    public function isSettlement(): bool
    {
        return $this->event === WebhookManager::EVENT_SETTLEMENT_SUCCESS;
    }

    /**
     * Whether this event is `transaction.transaction.transfer.reverse`.
     */
    public function isTransferReversal(): bool
    {
        return $this->event === WebhookManager::EVENT_TRANSFER_REVERSE;
    }
}
