<?php

namespace ZoweSoft\LaravelCredo\Facades;

use Illuminate\Support\Facades\Facade;
use ZoweSoft\LaravelCredo\CredoManager;

/**
 * @method static \ZoweSoft\LaravelCredo\PaymentBuilder payment()
 * @method static \ZoweSoft\LaravelCredo\Responses\InitializeResponse initialize(\ZoweSoft\LaravelCredo\PaymentBuilder|array $payment)
 * @method static \ZoweSoft\LaravelCredo\Data\Transaction verify(string $reference)
 * @method static \ZoweSoft\LaravelCredo\WebhookManager webhooks()
 * @method static string mode()
 * @method static bool isLiveMode()
 * @method static string baseUrl()
 * @method static string publicKey()
 * @method static string secretKey()
 * @method static int timeout()
 * @method static void verifyConfiguration()
 * @method static \ZoweSoft\LaravelCredo\Client client()
 * @method static \ZoweSoft\LaravelCredo\CredoManager usingClient(\ZoweSoft\LaravelCredo\Client $client)
 *
 * @see CredoManager
 */
class Credo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CredoManager::class;
    }
}
