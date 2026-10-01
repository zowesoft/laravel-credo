<?php

namespace ZoweSoft\LaravelCredo\Facades;

use Illuminate\Support\Facades\Facade;
use ZoweSoft\LaravelCredo\Contracts\PaymentGateway;
use ZoweSoft\LaravelCredo\CredoManager;

/**
 * Laravel facade for the Credo payment integration.
 *
 * Proxies all calls to the singleton {@see CredoManager} bound under the
 * `credo` alias. Use `Credo::payment()->...->send()` to initialize payments
 * and `Credo::verify($transRef)` to confirm results server-side.
 * Inject {@see PaymentGateway} in tests.
 *
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
 * @see https://docs.credocentral.com/docs/developers/authentication (Authentication)
 * @see https://docs.credocentral.com/docs/developers/accept-payments (Accept Payments)
 */
class Credo extends Facade
{
    /**
     * The facade proxies the CredoManager singleton (container alias "credo").
     */
    protected static function getFacadeAccessor(): string
    {
        return CredoManager::class;
    }
}
