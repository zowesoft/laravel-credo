<?php

namespace ZoweSoft\LaravelCredo\Data;

use ZoweSoft\LaravelCredo\WebhookManager;

class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $payload
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

    public function businessCode(): ?string
    {
        return isset($this->data['businessCode']) ? (string) $this->data['businessCode'] : null;
    }

    public function transaction(): Transaction
    {
        return Transaction::fromArray($this->data);
    }

    public function isSuccessful(): bool
    {
        return $this->event === WebhookManager::EVENT_TRANSACTION_SUCCESSFUL;
    }

    public function isFailed(): bool
    {
        return $this->event === WebhookManager::EVENT_TRANSACTION_FAILED;
    }

    public function isSettlement(): bool
    {
        return $this->event === WebhookManager::EVENT_SETTLEMENT_SUCCESS;
    }

    public function isTransferReversal(): bool
    {
        return $this->event === WebhookManager::EVENT_TRANSFER_REVERSE;
    }
}
