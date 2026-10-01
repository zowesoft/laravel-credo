# Zowesoft Laravel Credo

[![Tests](https://github.com/zowesoft/laravel-credo/actions/workflows/tests.yml/badge.svg)](https://github.com/zowesoft/laravel-credo/actions/workflows/tests.yml)

A fluent Laravel package for the [Credo](https://credocentral.com) payment gateway (credocentral.com / credodemo.com).

- Supports **Laravel 9–13** and PHP **8.1–8.4**
- Fluent API: `Credo::payment()->amount(15000)->email($email)->send()`
- Typed DTOs and enums for every Credo response field
- Webhook signature verification (`X-Credo-Signature`)
- No legacy SDK dependency — talks to Credo over Laravel's HTTP client (mockable with `Http::fake()`)

## Installation

```bash
composer require zowesoft/laravel-credo
```

You can publish the config file:

```bash
php artisan vendor:publish --tag="credo-config"
```

## Configuration

Add your keys to `.env`. You use one key pair per environment — swap the keys when you go live.

```env
CREDO_MODE=DEMO            # DEMO or LIVE
CREDO_PUBLIC_KEY=0PUB-...
CREDO_SECRET_KEY=0PRI-...
# Optional:
CREDO_CALLBACK_URL=https://yourapp.com/credo/callback
CREDO_TIMEOUT=30
```

### Key validation

Credo key formats reveal the environment: demo/test keys start with `0PUB` (public) /
`0PRI` (secret), live keys with `1PUB` / `1PRI`. Before every API call the package
validates that your keys match the active mode, so a wrong-environment or swapped pair
fails fast with a clear message instead of a cryptic API error.

If Credo ever changes their format, publish the config and override `key_prefixes` (set
an entry to `""` to skip that check), or disable the check entirely with
`CREDO_VALIDATE_KEYS=false`.

### Base URL overrides

The demo and live API base URLs can be overridden in the published config (`base_urls`
or the `CREDO_DEMO_BASE_URL` / `CREDO_LIVE_BASE_URL` env vars) in case the endpoints
move.

## Usage

### Initialize a payment (fluent builder)

```php
use ZoweSoft\LaravelCredo\Facades\Credo;

$response = Credo::payment()
    ->amount(15000)                       // kobo
    ->email('student@example.com')
    ->reference('PG-APP-0001')
    ->callbackUrl(route('credo.callback'))
    ->customer('Ada', 'Obi', '2348012345678')
    ->narration('Application fee')
    ->channels(['card', 'bank'])
    ->send();

return redirect()->away($response->authorizationUrl);
```

Handy builder extras:

```php
->amountInMajorUnits(150.50)   // converts to 15050 kobo
->customerBearsFee()           // bearer = 0 (default)
->merchantBearsFee()           // bearer = 1
->card()                       // add CARD channel
->bank()                       // add BANK channel
->generateVirtualAccount()     // initializeAccount = 1
->metadata(['matric' => 'ENG/2024/001'])
->customField('Programme', 'programme', 'Computer Science')
->serviceCode('XXXXXXX')       // dashboard-configured split settlement
->pauseSettlement('2026-03-15') // escrow
```

You can also initialize from a raw array:

```php
$response = Credo::initialize([
    'amount' => 15000,
    'email' => 'student@example.com',
    'currency' => 'NGN',
    'reference' => 'PG-APP-0001',
]);
```

The `InitializeResponse` DTO exposes `authorizationUrl`, `reference` (your business
reference), `credoReference` (Credo's `vs_...` reference), `crn`, and the raw payload.

### Verify a transaction

Always verify server-side before fulfilling an order — never trust the redirect alone.

```php
$transaction = Credo::verify('vs_xxxxxxxxxxxx');

// Note: Credo returns verify amounts in major units (naira, with decimals),
// unlike the initialize request which takes kobo. amountEquals() compares
// with a one-kobo tolerance:
if ($transaction->successful() && $transaction->amountEquals(2500.00)) {
    // mark order paid
}
```

`Transaction` exposes `credoReference`, `reference`, `amount`, `debitedAmount`,
`feeAmount`, `settlementAmount`, `email`, `currency`, `status()` (a backed enum with
labels), `successful()`, `metadataValue(string $key)` and the raw array.

### Webhooks

Credo signs each webhook with an `X-Credo-Signature` header — SHA-512 of
`secretKey + businessCode`. Verify and parse in one call:

```php
use ZoweSoft\LaravelCredo\Exceptions\InvalidSignatureException;

public function credoWebhook(Request $request)
{
    try {
        $event = Credo::webhooks()->capture();
    } catch (InvalidSignatureException) {
        abort(401);
    }

    if ($event->isSuccessful()) {
        $txn = $event->transaction();
        // $txn->reference, $txn->credoReference, $txn->amount, ...
    }

    return response('OK');
}
```

`$event->event` is one of `transaction.successful`, `transaction.failed`,
`transaction.transaction.transfer.reverse`, `transaction.settlement.success`
(constants live on `WebhookManager`), with `isSuccessful()`, `isFailed()`,
`isSettlement()` and `isTransferReversal()` helpers.

### Dependency injection

The manager is bound as a singleton and aliased as `credo`, so you can inject
`CredoManager` or the `PaymentGateway` contract anywhere:

```php
public function __construct(private \ZoweSoft\LaravelCredo\CredoManager $credo) {}
```

### Testing your app

The package uses Laravel's HTTP client, so `Http::fake()` works out of the box:

```php
Http::fake([
    'api.credodemo.com/transaction/initialize' => Http::response([
        'status' => 200,
        'message' => 'Transaction initialized successfully',
        'data' => [
            'authorizationUrl' => 'https://pay.credocentral.com/checkout/xxx',
            'reference' => 'PG-APP-0001',
            'credoReference' => 'vs_test',
        ],
    ]),
]);
```

## Testing the package

```bash
composer install
composer test
```

## License

MIT
