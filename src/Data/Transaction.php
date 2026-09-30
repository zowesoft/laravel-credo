<?php

namespace ZoweSoft\LaravelCredo\Data;

use ZoweSoft\LaravelCredo\Enums\TransactionStatus;

class Transaction
{
    public function __construct(
        public readonly string $credoReference,
        public readonly string $reference,
        public readonly float $amount,
        public readonly float $debitedAmount,
        public readonly float $feeAmount,
        public readonly ?float $settlementAmount,
        public readonly string $email,
        public readonly string $currency,
        public readonly int $statusCode,
        public readonly ?string $transactionDate,
        public readonly ?array $metadata,
        public readonly ?string $paymentMethod = null,
        public readonly ?string $paymentMethodType = null,
        public readonly array $raw = [],
    ) {}

    /**
     * Map a Credo verify response "data" object (also fits webhook payloads).
     *
     * Note: Credo returns these amounts in major units (e.g. naira, with
     * decimals) — unlike the initialize request, which takes kobo.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            credoReference: (string) ($data['transRef'] ?? ''),
            reference: (string) ($data['businessRef'] ?? ''),
            amount: (float) ($data['transAmount'] ?? 0),
            debitedAmount: (float) ($data['debitedAmount'] ?? 0),
            feeAmount: (float) ($data['transFeeAmount'] ?? 0),
            settlementAmount: isset($data['settlementAmount']) ? (float) $data['settlementAmount'] : null,
            email: (string) ($data['customerId'] ?? ''),
            currency: (string) ($data['currencyCode'] ?? ''),
            statusCode: (int) ($data['status'] ?? -1),
            transactionDate: $data['transactionDate'] ?? null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : null,
            paymentMethod: $data['paymentMethod'] ?? null,
            paymentMethodType: $data['paymentMethodType'] ?? null,
            raw: $data,
        );
    }

    public function status(): ?TransactionStatus
    {
        return TransactionStatus::tryFrom($this->statusCode);
    }

    public function successful(): bool
    {
        return $this->statusCode === TransactionStatus::SUCCESSFUL->value;
    }

    /**
     * Whether the paid amount matches the expected amount within one kobo
     * (Credo returns decimal amounts, so compare with a small tolerance).
     */
    public function amountEquals(float $expected): bool
    {
        return abs($this->amount - $expected) < 0.01;
    }

    public function metadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }
}
