<?php

namespace ZoweSoft\LaravelCredo\Data;

use ZoweSoft\LaravelCredo\CredoManager;
use ZoweSoft\LaravelCredo\Enums\TransactionStatus;

/**
 * Typed representation of a verified Credo transaction.
 *
 * Returned by {@see CredoManager::verify()} and
 * reconstructed from webhook payloads via
 * {@see WebhookEvent::transaction()}.
 *
 * @see https://docs.credocentral.com/docs/developers/verify-transaction
 */
class Transaction
{
    /**
     * @param  string  $credoReference  Credo's internal reference (API field: transRef).
     * @param  string  $reference  Your business reference (API field: businessRef).
     * @param  float  $amount  Amount charged in major units (e.g. naira). Note:
     *                         Credo returns verify/webhook amounts as major-unit
     *                         floats despite the docs claiming kobo.
     * @param  float  $debitedAmount  Total amount debited from the customer in major units.
     * @param  float  $feeAmount  Processing fee in major units.
     * @param  float|null  $settlementAmount  Amount to be settled to you, or null if not yet settled.
     * @param  string  $email  Customer email address (API field: customerId).
     * @param  string  $currency  ISO 4217 currency code, e.g. 'NGN'.
     * @param  int  $statusCode  Raw integer status from the API. Cast to the
     *                           {@see TransactionStatus} enum via {@see self::status()}.
     * @param  string|null  $transactionDate  ISO 8601 datetime string, or null if not yet settled.
     * @param  array<string, mixed>|null  $metadata  Key-value bag passed at initialize time, or null.
     * @param  string|null  $paymentMethod  Payment method used, e.g. 'CARD'.
     * @param  string|null  $paymentMethodType  Payment method sub-type, e.g. 'VISA'.
     * @param  array<string, mixed>  $raw  Full API response object for forward-compatibility.
     */
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

    /**
     * Cast the raw status code to a {@see TransactionStatus} enum case.
     *
     * Returns null when the API returns a code not yet known to this package;
     * check {@see self::$raw} or {@see self::$statusCode} directly in that case.
     */
    public function status(): ?TransactionStatus
    {
        return TransactionStatus::tryFrom($this->statusCode);
    }

    /**
     * Whether the transaction status is {@see TransactionStatus::SUCCESSFUL} (code 0).
     *
     * Prefer {@see self::matches()} for a one-call post-payment verification
     * that also checks amount, currency, and your business reference.
     */
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

    /**
     * Validate a verified transaction against the checklist from Credo's API
     * documentation in one call: successful status, expected amount, expected
     * currency and (optionally) the business reference you sent.
     *
     * The currency and reference checks are skipped when null is passed, so a
     * plain status + amount check is simply:
     *
     *     $transaction->matches(2500.00);
     *
     * @param  float|null  $expectedAmount  In major units (naira), like the verify response.
     * @param  string|null  $expectedCurrency  e.g. 'NGN' or 'USD'; null skips the check.
     * @param  string|null  $expectedReference  Your business reference; null skips the check.
     */
    public function matches(
        ?float $expectedAmount,
        ?string $expectedCurrency = null,
        ?string $expectedReference = null,
    ): bool {
        if (! $this->successful()) {
            return false;
        }

        if ($expectedAmount !== null && ! $this->amountEquals($expectedAmount)) {
            return false;
        }

        if ($expectedCurrency !== null
            && strcasecmp($this->currency, $expectedCurrency) !== 0) {
            return false;
        }

        if ($expectedReference !== null && $this->reference !== $expectedReference) {
            return false;
        }

        return true;
    }

    /**
     * Retrieve a single value from the metadata bag by key.
     *
     * The bag is populated from the `metadata` object passed to the initialize
     * request. Returns null when the key is absent or no metadata was sent.
     */
    public function metadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }
}
