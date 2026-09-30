<?php

namespace ZoweSoft\LaravelCredo;

use Illuminate\Http\Request;
use ZoweSoft\LaravelCredo\Data\WebhookEvent;
use ZoweSoft\LaravelCredo\Exceptions\InvalidSignatureException;

class WebhookManager
{
    public const EVENT_TRANSACTION_SUCCESSFUL = 'transaction.successful';

    public const EVENT_TRANSACTION_FAILED = 'transaction.failed';

    public const EVENT_TRANSFER_REVERSE = 'transaction.transaction.transfer.reverse';

    public const EVENT_SETTLEMENT_SUCCESS = 'transaction.settlement.success';

    public function __construct(protected CredoManager $manager) {}

    /**
     * Verify an X-Credo-Signature header value: sha512(secretKey + businessCode).
     */
    public function verifySignature(string $signature, string $businessCode): bool
    {
        if ($this->manager->secretKey() === '' || $businessCode === '') {
            return false;
        }

        return hash_equals(
            $this->expectedSignature($businessCode),
            strtolower(trim($signature)),
        );
    }

    /**
     * Validate a webhook payload against its signature and return the event.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidSignatureException
     */
    public function validate(string $signature, array $payload): WebhookEvent
    {
        $event = WebhookEvent::fromArray($payload);

        $businessCode = $event->businessCode();

        if ($businessCode === null || ! $this->verifySignature($signature, $businessCode)) {
            throw InvalidSignatureException::signature();
        }

        return $event;
    }

    /**
     * Validate the current inbound request (body + X-Credo-Signature header).
     *
     * @throws InvalidSignatureException
     */
    public function capture(?string $content = null, ?string $signature = null): WebhookEvent
    {
        $content ??= $this->inboundContent();
        $signature ??= $this->inboundSignature();

        $payload = json_decode($content ?: '[]', true);

        if (! is_array($payload)) {
            throw InvalidSignatureException::malformedPayload();
        }

        return $this->validate((string) $signature, $payload);
    }

    protected function expectedSignature(string $businessCode): string
    {
        return hash('sha512', $this->manager->secretKey().$businessCode);
    }

    protected function inboundContent(): ?string
    {
        return app('request')->getContent();
    }

    protected function inboundSignature(): ?string
    {
        /** @var Request $request */
        $request = app('request');

        return $request->header('X-Credo-Signature');
    }
}
