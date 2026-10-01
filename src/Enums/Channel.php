<?php

namespace ZoweSoft\LaravelCredo\Enums;

/**
 * Payment channels offered on the Credo hosted checkout.
 *
 * A request may restrict the visible channels to Card and/or Bank Transfer;
 * when the field is omitted, Credo presents every channel available to the
 * customer.
 *
 * @see https://docs.credocentral.com/docs/concepts#payment-channels (Payment channels)
 */
enum Channel: string
{
    case CARD = 'CARD';
    case BANK = 'BANK';
}
