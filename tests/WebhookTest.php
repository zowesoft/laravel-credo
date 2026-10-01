<?php

use Illuminate\Http\Request;
use ZoweSoft\LaravelCredo\Data\WebhookEvent;
use ZoweSoft\LaravelCredo\Exceptions\InvalidSignatureException;
use ZoweSoft\LaravelCredo\Facades\Credo;
use ZoweSoft\LaravelCredo\Tests\TestCase;

function webhookPayload(): array
{
    return [
        'event' => 'transaction.successful',
        'data' => [
            'businessCode' => '700607002190001',
            'transRef' => 'cI9H00N2AB02Qb0s69Mj',
            'businessRef' => 'PG-APP-0001',
            'debitedAmount' => 1000.0,
            'transAmount' => 1000.0,
            'transFeeAmount' => 15.0,
            'settlementAmount' => 985.0,
            'customerId' => 'customer@example.com',
            'currencyCode' => 'NGN',
            'status' => 0,
            'paymentMethodType' => 'MasterCard',
            'paymentMethod' => 'Card',
        ],
    ];
}

it('computes and verifies the credo signature', function () {
    $signature = hash('sha512', TestCase::TEST_SECRET_KEY.'700607002190001');

    expect(Credo::webhooks()->verifySignature($signature, '700607002190001'))->toBeTrue();
    expect(Credo::webhooks()->verifySignature(strtoupper($signature), '700607002190001'))->toBeTrue();
    expect(Credo::webhooks()->verifySignature('  '.$signature.'  ', '700607002190001'))->toBeTrue();
});

it('rejects a signature from a different secret', function () {
    $signature = hash('sha512', 'some-other-secret700607002190001');

    expect(Credo::webhooks()->verifySignature($signature, '700607002190001'))->toBeFalse();
});

it('rejects verification without keys or business code', function () {
    config()->set('credo.secret_key', null);

    expect(Credo::webhooks()->verifySignature('anything', '700607002190001'))->toBeFalse();
    expect(Credo::webhooks()->verifySignature(hash('sha512', TestCase::TEST_SECRET_KEY), ''))->toBeFalse();
});

it('validates a payload and returns a typed event', function () {
    $payload = webhookPayload();
    $signature = hash('sha512', TestCase::TEST_SECRET_KEY.$payload['data']['businessCode']);

    $event = Credo::webhooks()->validate($signature, $payload);

    expect($event)->toBeInstanceOf(WebhookEvent::class);
    expect($event->event)->toBe('transaction.successful');
    expect($event->isSuccessful())->toBeTrue();
    expect($event->isFailed())->toBeFalse();
    expect($event->businessCode())->toBe('700607002190001');
    expect($event->transaction()->reference)->toBe('PG-APP-0001');
    expect($event->transaction()->credoReference)->toBe('cI9H00N2AB02Qb0s69Mj');
    expect($event->transaction()->successful())->toBeTrue();
});

it('throws on a tampered payload', function () {
    $payload = webhookPayload();
    $signature = hash('sha512', TestCase::TEST_SECRET_KEY.'999999999999999');

    Credo::webhooks()->validate($signature, $payload);
})->throws(InvalidSignatureException::class);

it('throws when the payload has no business code', function () {
    $payload = ['event' => 'transaction.successful', 'data' => ['transRef' => 'x']];

    Credo::webhooks()->validate(hash('sha512', 'secret'), $payload);
})->throws(InvalidSignatureException::class);

it('captures the inbound request content and header', function () {
    $payload = webhookPayload();
    $content = json_encode($payload);
    $signature = hash('sha512', TestCase::TEST_SECRET_KEY.$payload['data']['businessCode']);

    $this->app['request'] = Request::create(
        uri: '/webhooks/credo',
        method: 'POST',
        content: $content,
        server: ['HTTP_X_CREDO_SIGNATURE' => $signature],
    );

    $event = Credo::webhooks()->capture();

    expect($event->isSuccessful())->toBeTrue();
    expect($event->transaction()->email)->toBe('customer@example.com');
});

it('throws on malformed inbound json', function () {
    $this->app['request'] = Request::create(
        uri: '/webhooks/credo',
        method: 'POST',
        content: '{not-json',
        server: ['HTTP_X_CREDO_SIGNATURE' => 'sig'],
    );

    Credo::webhooks()->capture();
})->throws(InvalidSignatureException::class);

it('treats a missing signature header as invalid, not a type error', function () {
    $this->app['request'] = Request::create(
        uri: '/webhooks/credo',
        method: 'POST',
        content: json_encode(webhookPayload()),
    );

    Credo::webhooks()->capture();
})->throws(InvalidSignatureException::class);
