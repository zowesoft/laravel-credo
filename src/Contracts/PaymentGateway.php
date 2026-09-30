<?php

namespace ZoweSoft\LaravelCredo\Contracts;

use ZoweSoft\LaravelCredo\Data\Transaction;
use ZoweSoft\LaravelCredo\PaymentBuilder;
use ZoweSoft\LaravelCredo\Responses\InitializeResponse;
use ZoweSoft\LaravelCredo\WebhookManager;

interface PaymentGateway
{
    public function payment(): PaymentBuilder;

    /**
     * @param  PaymentBuilder|array<string, mixed>  $payment
     */
    public function initialize(PaymentBuilder|array $payment): InitializeResponse;

    public function verify(string $reference): Transaction;

    public function webhooks(): WebhookManager;

    public function mode(): string;

    public function isLiveMode(): bool;
}
