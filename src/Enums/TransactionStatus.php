<?php

namespace ZoweSoft\LaravelCredo\Enums;

/**
 * Transaction status codes from Credo verification and webhook payloads.
 *
 * Codes 14 (Initialized) and 15 (Initializing) are pre-payment states and
 * may not appear in transaction history.
 *
 * @see https://docs.credocentral.com/docs/concepts#transaction-statuses (Transaction statuses)
 */
enum TransactionStatus: int
{
    case SUCCESSFUL = 0;
    case REFUNDED = 1;             // Refund completed; customer has been refunded.
    case REFUND = 2;               // Queued for refund; not yet returned to customer.
    case FAILED = 3;
    case SETTLE = 4;               // Queued for settlement; not yet paid out to merchant.
    case SETTLED = 5;              // Settlement complete; funds paid out to merchant.
    case REVIEW = 6;               // Flagged for manual review by Credo.
    case DECLINED = 7;             // Declined by fraud check.
    case CANCELLED_BY_CUSTOMER = 9;
    case CANCELLED_BY_MERCHANT = 10;
    case ATTEMPTED_AWAITING_CREDIT = 12; // Bank account generated; customer has not yet paid.
    case ATTEMPTED = 13;           // Customer started payment but did not complete it.
    case INITIALIZED = 14;         // Payment page was loaded (pre-payment state).
    case INITIALIZING = 15;        // Payment URL was generated (pre-payment state).

    /**
     * Human-readable description of the status, as used in Credo's documentation.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUCCESSFUL => 'Successful',
            self::REFUNDED => 'Refunded',
            self::REFUND => 'Queued for refund',
            self::FAILED => 'Failed',
            self::SETTLE => 'Queued for settlement',
            self::SETTLED => 'Settled',
            self::REVIEW => 'Flagged for manual review',
            self::DECLINED => 'Failed fraud check',
            self::CANCELLED_BY_CUSTOMER => 'Cancelled by customer',
            self::CANCELLED_BY_MERCHANT => 'Cancelled by merchant',
            self::ATTEMPTED_AWAITING_CREDIT => 'Account generated, awaiting credit',
            self::ATTEMPTED => 'Customer attempted payment',
            self::INITIALIZED => 'Payment page loaded',
            self::INITIALIZING => 'Payment URL generated',
        };
    }

    /**
     * Whether the status code is 0 (successful).
     */
    public function isSuccessful(): bool
    {
        return $this === self::SUCCESSFUL;
    }
}
