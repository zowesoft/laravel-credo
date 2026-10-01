<?php

namespace ZoweSoft\LaravelCredo\Exceptions;

use Exception;

/**
 * Base exception for every error raised by the laravel-credo package.
 *
 * A single `catch (CredoException $e)` handles any package failure; the
 * subclasses carry more specific context. The one exception outside this
 * tree is Illuminate\Http\Client\ConnectionException, which surfaces when
 * the connection itself fails on every retry attempt.
 *
 * @see https://docs.credocentral.com/docs/developers/error-handling
 */
abstract class CredoException extends Exception {}
