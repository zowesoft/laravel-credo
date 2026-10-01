<?php

namespace ZoweSoft\LaravelCredo\Exceptions;

/**
 * Thrown before any HTTP call when the configured keys are missing or do not
 * match the expected format for the active mode.
 *
 * This always indicates a problem with your environment or config, never a
 * transient API issue - fix the configuration instead of retrying.
 *
 * @see https://docs.credocentral.com/docs/developers/authentication
 */
class InvalidConfigurationException extends CredoException
{
    /**
     * Build the exception for a missing public or secret key in the given mode.
     */
    public static function missingKeys(string $mode): static
    {
        return new static(
            "Credo API keys are not configured for [{$mode}] mode. ".
            'Set CREDO_PUBLIC_KEY and CREDO_SECRET_KEY in your environment.'
        );
    }

    /**
     * Build the exception for a key whose prefix does not match the active
     * mode (demo keys start 0PUB/0PRI, live keys 1PUB/1PRI).
     */
    public static function invalidKeyPrefix(string $role, string $expectedPrefix, string $mode): static
    {
        $envKey = $role === 'secret' ? 'CREDO_SECRET_KEY' : 'CREDO_PUBLIC_KEY';

        return new static(
            "The configured Credo {$role} key does not look like a {$mode} key ".
            "(expected it to start with \"{$expectedPrefix}\"). ".
            "Check {$envKey} and CREDO_MODE, override credo.key_prefixes in the published ".
            'config if Credo changes their format, or set CREDO_VALIDATE_KEYS=false to skip this check.'
        );
    }
}
