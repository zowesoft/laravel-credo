<?php

namespace ZoweSoft\LaravelCredo\Exceptions;

class InvalidSignatureException extends CredoException
{
    public static function signature(): static
    {
        return new static('Credo webhook signature verification failed.');
    }

    public static function malformedPayload(): static
    {
        return new static('Credo webhook payload is not valid JSON.');
    }
}
