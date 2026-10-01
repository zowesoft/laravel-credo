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

### Version support

One stable release covers every supported Laravel major: the package requires
`illuminate/contracts ^9.0|^10.0|^11.0|^12.0|^13.0`, so the same `composer require`
works whether your project runs Laravel 9, 10, 11, 12 or 13.

| Laravel | Development branch |
|---|---|
| 11.x – 13.x | `main` |
| 10.x | `10.x` |
| 9.x | `9.x` |

Maintenance branches exist to run the test suite against older majors with their
matching dev tooling — the runtime package is identical on every branch.

### Installing a development branch

Packagist publishes each branch head as a mutable dev version (`dev-main`,
`10.x-dev`, `9.x-dev`). To try unreleased changes:

```bash
composer require zowesoft/laravel-credo:dev-10.x@dev
```

The `@dev` flag is required because branch snapshots are not stable releases. Branch
heads move as work is pushed, so avoid pinning production apps to one.

### Maintenance releases

Bugfixes for older majors land on their maintenance branch and are tagged there
(for example `v1.0.1` on `10.x`). Packagist indexes tags from any branch, so a
`^1.0` constraint automatically receives the newest release that supports your
Laravel version.

## Configuration

Add your keys to `.env`. You use one key pair per environment — swap the keys when you go live.

```env
CREDO_MODE=DEMO            # DEMO or LIVE
CREDO_PUBLIC_KEY=0PUB-...
CREDO_SECRET_KEY=0PRI-...
# Optional:
CREDO_CALLBACK_URL=https://yourapp.com/credo/callback
CREDO_TIMEOUT=30
CREDO_RETRY_MAX_ATTEMPTS=3
CREDO_RETRY_BASE_DELAY_MS=1000
# Optional request logging (set to a log channel name):
# CREDO_LOG_CHANNEL=credo
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

### Retries (rate limits & network failures)

Following Credo's API guidance, requests are retried automatically with exponential
backoff when Credo answers `429 Too Many Requests` or when the connection fails
outright (timeout, DNS error, connection reset). Any other client error — 401, 403,
404, 422 — is thrown immediately as `RequestFailedException`, since retrying it can
never succeed.

Two published-config keys control the behaviour (env vars `CREDO_RETRY_MAX_ATTEMPTS`
and `CREDO_RETRY_BASE_DELAY_MS`):

| Key | Default | Meaning |
| --- | ------- | ------- |
| `retry_max_attempts` | `3` | Total attempts per request, including the first. Set to `1` to disable retries. |
| `retry_base_delay_ms` | `1000` | Delay before the first retry; each further retry doubles it (1s, 2s, …). |

If every attempt fails, the original `ConnectionException` (network) or
`RequestFailedException` (API error) is thrown, so you can queue the work for a
later retry — a scheduled job re-verifying pending transactions is a natural fit.

### Request logging (opt-in)

Set `log_channel` in the published config (env: `CREDO_LOG_CHANNEL`) to a log channel
name — `daily`, `stderr`, or a dedicated channel in your `config/logging.php` — and
every API call writes one record:

```
[2026-10-01 12:00:00] local.INFO: Credo API request {"method":"GET","path":"/transaction/vs_xxx/verify","status":200,"duration_ms":148,"attempts":2,"transRef":"vs_xxx"}
```

Each record carries the HTTP method, path, status, duration in milliseconds, the
retry-aware attempt count, and the `transRef` when it is known — the verify path
itself, or the `data.transRef` of an initialize response. Connection failures that
survive all retry attempts are logged at error level instead.

Leave the channel unset (`CREDO_LOG_CHANNEL=null`) to disable logging entirely; the
package makes no log calls at all in that case.

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

// One-call version of Credo's verification checklist: successful status,
// expected amount (major units — verify responses are naira, not kobo),
// expected currency, and your business reference. Pass null to skip a
// check: matches(2500.00) validates just status + amount.
if ($transaction->matches(2500.00, 'NGN', 'PG-APP-0001')) {
    // mark order paid — status, amount, currency and reference all check out
}
```

`Transaction` exposes `credoReference`, `reference`, `amount`, `debitedAmount`,
`feeAmount`, `settlementAmount`, `email`, `currency`, `status()` (a backed enum with
labels), `successful()`, `amountEquals(float)`, `matches(?amount, ?currency, ?reference)`
(the verification checklist in one call), `metadataValue(string $key)` and the raw array.

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

### Error handling

Every package exception extends `ZoweSoft\LaravelCredo\Exceptions\CredoException`, so a
single `catch (CredoException)` covers everything the package throws itself. The one
exception outside that tree is Laravel's `Illuminate\Http\Client\ConnectionException`,
which surfaces when the connection itself fails on every retry attempt.

| Exception | When it is thrown | Retry? |
| --- | --- | --- |
| `InvalidConfigurationException` | Keys missing, or a key prefix does not match the active mode — thrown before any HTTP call | No — fix your `.env` / config |
| `RequestFailedException` | Credo answered with an error. `$e->httpStatus` (HTTP code), `$e->apiStatus` (Credo body status), `$e->errors` (the API's `error` array) | 429 and connection errors are retried automatically; once exhausted, queue the work for later. Other 4xx will never succeed on retry |
| `InvalidSignatureException` | Webhook signature check failed or the body was not valid JSON | No — likely not from Credo; respond `401` (see [Webhooks](#webhooks)) |
| `ConnectionException` | Timeout, DNS failure or connection reset, after all retries | Yes — network failures are always safe to retry |

Map API errors to friendly, actionable messages for customers — never surface the raw
`$e->errors` array (per Credo's error-handling guidance):

```php
use Illuminate\Http\Client\ConnectionException;
use ZoweSoft\LaravelCredo\Exceptions\InvalidConfigurationException;
use ZoweSoft\LaravelCredo\Exceptions\RequestFailedException;

try {
    $transaction = $credo->verify($transRef);
} catch (InvalidConfigurationException $e) {
    report($e); // misconfiguration is a developer problem, not a user problem

    return back()->withError('Payment is temporarily unavailable.');
} catch (ConnectionException $e) {
    VerifyPayment::dispatch($transRef)->delay(now()->addMinutes(5)); // retry later

    return back()->withError('Payment service unreachable — try again shortly.');
} catch (RequestFailedException $e) {
    return match (true) {
        $e->httpStatus === 404 => back()->withError('We could not find that payment.'),
        $e->httpStatus === 429 => back()->withError('Too many requests — try again in a moment.'),
        $e->httpStatus >= 500 => back()->withError('Payment service error — try again shortly.'),
        default => back()->withError('Payment could not be verified.'),
    };
}
```

Two habits worth keeping: log every caught exception with the `transRef` for
reconciliation (enable [request logging](#request-logging-opt-in) and most of that
comes for free), and remember that retried requests are already handled for you —
these catches only fire once all automatic attempts are exhausted.

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
