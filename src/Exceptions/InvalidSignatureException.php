<?php

namespace ZoweSoft\LaravelCredo\Exceptions;

/**
 * Thrown when a webhook signature fails verification or the payload is not
 * valid JSON.
 *
 * The request may not have come from Credo: respond with HTTP 401 and do not
 * process the payload.
 *
 * @see https://docs.credocentral.com/docs/developers/webhooks
 */
class InvalidSignatureException extends CredoException
{
    /**
     * Build the exception for a missing or mismatched X-Credo-Signature header.
     */
    public static function signature(): static
    {
        return new static('Credo webhook signature verification failed.');
    }

    /**
     * Build the exception for a webhook body that is not valid JSON.
     */
    public static function malformedPayload(): static
    {
        return new static('Credo webhook payload is not valid JSON.');
    }
}
