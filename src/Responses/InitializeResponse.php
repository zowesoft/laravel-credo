<?php

namespace ZoweSoft\LaravelCredo\Responses;

class InitializeResponse
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $authorizationUrl,
        public readonly string $reference,
        public readonly string $credoReference,
        public readonly ?string $crn,
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $requestPayload
     */
    public static function fromArray(array $data, array $requestPayload = []): self
    {
        return new self(
            authorizationUrl: (string) ($data['authorizationUrl'] ?? ''),
            reference: (string) ($data['reference'] ?? ($requestPayload['reference'] ?? '')),
            credoReference: (string) ($data['credoReference'] ?? ''),
            crn: $data['crn'] ?? null,
            raw: $data,
        );
    }
}
