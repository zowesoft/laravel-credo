<?php

namespace ZoweSoft\LaravelCredo\Enums;

/**
 * Currencies supported by the Credo API.
 *
 * Initialize requests express amounts in the lowest unit of the selected
 * currency (kobo for NGN, cents for USD), while verify and webhook responses
 * return amounts in major units.
 *
 * @see https://docs.credocentral.com/docs/concepts#amounts (Amounts)
 */
enum Currency: string
{
    case NGN = 'NGN';
    case USD = 'USD';
}
