<?php

use Illuminate\Support\Facades\Http;
use ZoweSoft\LaravelCredo\Data\Transaction;
use ZoweSoft\LaravelCredo\Exceptions\RequestFailedException;
use ZoweSoft\LaravelCredo\Facades\Credo;
use ZoweSoft\LaravelCredo\Responses\InitializeResponse;
use ZoweSoft\LaravelCredo\Tests\TestCase;

it('initializes a payment with the public key and default bearer', function () {
    Http::fake([
        'https://api.credodemo.com/transaction/initialize' => Http::response([
            'status' => 200,
            'message' => 'Transaction initialized successfully',
            'data' => [
                'authorizationUrl' => 'https://pay.credocentral.com/checkout/xxx',
                'reference' => 'PG-APP-0001',
                'credoReference' => 'vs_test123',
                'crn' => '0000298483',
            ],
        ]),
    ]);

    $response = Credo::payment()
        ->amount(150000)
        ->email('student@example.com')
        ->reference('PG-APP-0001')
        ->callbackUrl('https://portal.test/credo/callback')
        ->send();

    expect($response)->toBeInstanceOf(InitializeResponse::class);
    expect($response->authorizationUrl)->toBe('https://pay.credocentral.com/checkout/xxx');
    expect($response->reference)->toBe('PG-APP-0001');
    expect($response->credoReference)->toBe('vs_test123');
    expect($response->crn)->toBe('0000298483');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', TestCase::TEST_PUBLIC_KEY)
            && $request['bearer'] === 0
            && $request['amount'] === 150000
            && $request['reference'] === 'PG-APP-0001';
    });
});

it('initializes from an array payload via the manager', function () {
    Http::fake([
        'https://api.credodemo.com/transaction/initialize' => Http::response([
            'status' => 200,
            'message' => 'ok',
            'data' => ['authorizationUrl' => 'https://pay.test/x', 'reference' => 'REF-1', 'credoReference' => 'vs_1'],
        ]),
    ]);

    $response = Credo::initialize([
        'amount' => 500,
        'email' => 'a@b.com',
        'currency' => 'NGN',
    ]);

    expect($response->reference)->toBe('REF-1');
    expect($response->credoReference)->toBe('vs_1');
});

it('falls back to the configured callback url when the builder omits one', function () {
    config()->set('credo.callback_url', 'https://portal.test/default-callback');

    Http::fake([
        'https://api.credodemo.com/transaction/initialize' => Http::response([
            'status' => 200,
            'message' => 'ok',
            'data' => ['authorizationUrl' => 'https://pay.test/x', 'reference' => 'R', 'credoReference' => 'v'],
        ]),
    ]);

    Credo::initialize(['amount' => 500, 'email' => 'a@b.com', 'currency' => 'NGN']);

    Http::assertSent(fn ($request) => $request['callbackUrl'] === 'https://portal.test/default-callback');
});

it('keeps an explicit callback url over the configured default', function () {
    config()->set('credo.callback_url', 'https://portal.test/default-callback');

    Http::fake([
        'https://api.credodemo.com/transaction/initialize' => Http::response([
            'status' => 200,
            'message' => 'ok',
            'data' => ['authorizationUrl' => 'https://pay.test/x', 'reference' => 'R', 'credoReference' => 'v'],
        ]),
    ]);

    Credo::payment()->amount(1)->email('a@b.com')->callbackUrl('https://portal.test/explicit')->send();

    Http::assertSent(fn ($request) => $request['callbackUrl'] === 'https://portal.test/explicit');
});

it('throws a request failed exception with api details on error', function () {
    Http::fake([
        'https://api.credodemo.com/transaction/initialize' => Http::response([
            'status' => 401,
            'message' => 'Invalid public key',
            'error' => 'invalid_key',
        ], 401),
    ]);

    try {
        Credo::payment()->amount(100)->email('a@b.com')->send();
        $this->fail('Expected RequestFailedException was not thrown.');
    } catch (RequestFailedException $exception) {
        expect($exception->httpStatus)->toBe(401);
        expect($exception->apiStatus)->toBe(401);
        expect($exception->errors)->toBe(['error' => 'invalid_key']);
        expect($exception->getMessage())->toContain('Invalid public key');
    }
});

it('verifies a transaction with the secret key and maps the response', function () {
    Http::fake([
        'https://api.credodemo.com/transaction/vs_test123/verify' => Http::response([
            'status' => 200,
            'message' => 'Transaction fetched successfully',
            'data' => [
                'transRef' => 'vs_test123',
                'businessRef' => 'PG-APP-0001',
                'debitedAmount' => 2637.5,
                'transAmount' => 2500.0,
                'transFeeAmount' => 137.5,
                'settlementAmount' => 2500.0,
                'customerId' => 'student@example.com',
                'transactionDate' => '2026-02-07T14:30:00.000Z',
                'currencyCode' => 'NGN',
                'status' => 0,
                'metadata' => ['matric' => 'ENG/2024/001'],
            ],
        ]),
    ]);

    $transaction = Credo::verify('vs_test123');

    expect($transaction)->toBeInstanceOf(Transaction::class);
    expect($transaction->credoReference)->toBe('vs_test123');
    expect($transaction->reference)->toBe('PG-APP-0001');
    expect($transaction->amount)->toBe(2500.0);
    expect($transaction->debitedAmount)->toBe(2637.5);
    expect($transaction->feeAmount)->toBe(137.5);
    expect($transaction->amountEquals(2500))->toBeTrue();
    expect($transaction->amountEquals(2499.99))->toBeFalse();
    expect($transaction->email)->toBe('student@example.com');
    expect($transaction->currency)->toBe('NGN');
    expect($transaction->successful())->toBeTrue();
    expect($transaction->status()->label())->toBe('Successful');
    expect($transaction->metadataValue('matric'))->toBe('ENG/2024/001');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', TestCase::TEST_SECRET_KEY));
});

it('maps unsuccessful statuses on verification', function () {
    Http::fake([
        'https://api.credodemo.com/transaction/vs_failed/verify' => Http::response([
            'status' => 200,
            'message' => 'ok',
            'data' => ['transRef' => 'vs_failed', 'businessRef' => 'R', 'status' => 3, 'transAmount' => 0.0],
        ]),
    ]);

    $transaction = Credo::verify('vs_failed');

    expect($transaction->successful())->toBeFalse();
    expect($transaction->status()->label())->toBe('Failed');
});

it('hides the secret key from verification requests', function () {
    Http::fake([
        'https://api.credodemo.com/transaction/vs_x/verify' => Http::response([
            'status' => 200,
            'message' => 'ok',
            'data' => ['transRef' => 'vs_x', 'status' => 0],
        ]),
    ]);

    Credo::verify('vs_x');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', TestCase::TEST_SECRET_KEY)
        && ! $request->hasHeader('Authorization', TestCase::TEST_PUBLIC_KEY));
});
