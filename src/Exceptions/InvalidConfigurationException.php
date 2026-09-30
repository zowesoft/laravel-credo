<?php

namespace ZoweSoft\LaravelCredo\Exceptions;

class InvalidConfigurationException extends CredoException
{
    public static function missingKeys(string $mode): static
    {
        return new static(
            "Credo API keys are not configured for [{$mode}] mode. ".
            'Set CREDO_PUBLIC_KEY and CREDO_SECRET_KEY in your environment.'
        );
    }

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
