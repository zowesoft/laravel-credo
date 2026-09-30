<?php

namespace ZoweSoft\LaravelCredo\Enums;

enum TransactionStatus: int
{
    case SUCCESSFUL = 0;
    case REFUNDED = 1;
    case REFUND = 2;
    case FAILED = 3;
    case SETTLE = 4;
    case SETTLED = 5;
    case REVIEW = 6;
    case DECLINED = 7;
    case CANCELLED_BY_CUSTOMER = 9;
    case CANCELLED_BY_MERCHANT = 10;
    case ATTEMPTED_AWAITING_CREDIT = 12;
    case ATTEMPTED = 13;
    case INITIALIZED = 14;
    case INITIALIZING = 15;

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

    public function isSuccessful(): bool
    {
        return $this === self::SUCCESSFUL;
    }
}
