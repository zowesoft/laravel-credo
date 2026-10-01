<?php

namespace ZoweSoft\LaravelCredo\Enums;

/**
 * Who bears the Credo transaction processing fee.
 *
 * CUSTOMER (0): the fee is added on top — the customer pays more and you
 * receive the full amount. MERCHANT (1): the fee is deducted — the customer
 * pays the base amount and you receive less.
 *
 * @see https://docs.credocentral.com/docs/concepts#fee-bearer (Fee bearer)
 */
enum FeeBearer: int
{
    case CUSTOMER = 0;
    case MERCHANT = 1;
}
