<?php

namespace ZoweSoft\LaravelCredo\Exceptions;

use Illuminate\Http\Client\Response;

class RequestFailedException extends CredoException
{
    /**
     * @param  array<string, mixed>|null  $errors
     */
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        public readonly ?int $apiStatus = null,
        public readonly ?array $errors = null,
    ) {
        parent::__construct($message, $httpStatus);
    }

    public static function fromResponse(Response $response): static
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $message = filled($body['message'] ?? null)
            ? (string) $body['message']
            : 'Credo request failed';

        $errors = null;

        foreach (['error', 'errors'] as $key) {
            if (isset($body[$key])) {
                $errors = is_array($body[$key]) ? $body[$key] : ['error' => $body[$key]];

                break;
            }
        }

        return new static(
            "Credo request failed [HTTP {$response->status()}]: {$message}",
            $response->status(),
            isset($body['status']) ? (int) $body['status'] : null,
            $errors,
        );
    }
}
