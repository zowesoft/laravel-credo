<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use ZoweSoft\LaravelCredo\Exceptions\RequestFailedException;
use ZoweSoft\LaravelCredo\Facades\Credo;
use ZoweSoft\LaravelCredo\Support\Retry;

it('retries a rate limited request and succeeds on a later attempt', function () {
    config()->set('credo.retry_max_attempts', 3);
    config()->set('credo.retry_base_delay_ms', 0);

    Http::fake([
        'https://api.credodemo.com/transaction/vs_retry/verify' => Http::sequence()
            ->pushStatus(429)
            ->pushStatus(429)
            ->push([
                'status' => 200,
                'message' => 'ok',
                'data' => ['transRef' => 'vs_retry', 'businessRef' => 'R', 'status' => 0, 'transAmount' => 100.0],
            ]),
    ]);

    $transaction = Credo::verify('vs_retry');

    expect($transaction->successful())->toBeTrue();
    Http::assertSentCount(3);
});

it('gives up after the configured attempts when rate limited', function () {
    config()->set('credo.retry_max_attempts', 3);
    config()->set('credo.retry_base_delay_ms', 0);

    Http::fake([
        'https://api.credodemo.com/transaction/initialize' => Http::sequence()
            ->pushStatus(429)
            ->pushStatus(429)
            ->pushStatus(429),
    ]);

    try {
        Credo::payment()->amount(1000)->email('a@b.com')->send();
        $this->fail('Expected RequestFailedException was not thrown.');
    } catch (RequestFailedException $exception) {
        expect($exception->httpStatus)->toBe(429);
    }

    Http::assertSentCount(3);
});

it('retries a connection failure and succeeds', function () {
    config()->set('credo.retry_max_attempts', 3);
    config()->set('credo.retry_base_delay_ms', 0);

    $connectionFailures = 0;

    Http::fake(function ($request) use (&$connectionFailures) {
        if (str_contains($request->url(), '/transaction/vs_conn/verify') && $connectionFailures < 1) {
            $connectionFailures++;

            throw new ConnectionException('cURL error 28: Connection timed out');
        }

        return Http::response([
            'status' => 200,
            'message' => 'ok',
            'data' => ['transRef' => 'vs_conn', 'businessRef' => 'R', 'status' => 0, 'transAmount' => 100.0],
        ]);
    });

    $transaction = Credo::verify('vs_conn');

    expect($transaction->credoReference)->toBe('vs_conn')
        ->and($connectionFailures)->toBe(1);
});

it('throws the connection exception once every attempt fails', function () {
    config()->set('credo.retry_max_attempts', 2);
    config()->set('credo.retry_base_delay_ms', 0);

    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('cURL error 6: Could not resolve host');
    });

    try {
        Credo::verify('vs_dead');
        $this->fail('Expected ConnectionException was not thrown.');
    } catch (ConnectionException $exception) {
        expect($exception->getMessage())->toContain('cURL error 6');
    }

    expect($attempts)->toBe(2);
});

it('never retries other client errors like an invalid key', function () {
    config()->set('credo.retry_max_attempts', 3);
    config()->set('credo.retry_base_delay_ms', 0);

    Http::fake([
        'https://api.credodemo.com/transaction/vs_401/verify' => Http::response([
            'status' => 401,
            'message' => 'Unauthorized',
        ], 401),
    ]);

    try {
        Credo::verify('vs_401');
        $this->fail('Expected RequestFailedException was not thrown.');
    } catch (RequestFailedException $exception) {
        expect($exception->httpStatus)->toBe(401);
    }

    Http::assertSentCount(1);
});

it('does not retry when retries are disabled', function () {
    config()->set('credo.retry_max_attempts', 1);
    config()->set('credo.retry_base_delay_ms', 0);

    Http::fake([
        'https://api.credodemo.com/transaction/vs_once/verify' => Http::sequence()
            ->pushStatus(429)
            ->pushStatus(429),
    ]);

    try {
        Credo::verify('vs_once');
        $this->fail('Expected RequestFailedException was not thrown.');
    } catch (RequestFailedException $exception) {
        expect($exception->httpStatus)->toBe(429);
    }

    Http::assertSentCount(1);
});

it('doubles the delay for each successive retry', function () {
    expect(Retry::delayFor(1000, 1))->toBe(1000000);
    expect(Retry::delayFor(1000, 2))->toBe(2000000);
    expect(Retry::delayFor(1000, 3))->toBe(4000000);
    expect(Retry::delayFor(0, 2))->toBe(0);
});

it('waits between retries using the configured base delay', function () {
    config()->set('credo.retry_max_attempts', 3);
    config()->set('credo.retry_base_delay_ms', 60);

    Http::fake([
        'https://api.credodemo.com/transaction/vs_slow/verify' => Http::sequence()
            ->pushStatus(429)
            ->pushStatus(429)
            ->push([
                'status' => 200,
                'message' => 'ok',
                'data' => ['transRef' => 'vs_slow', 'businessRef' => 'R', 'status' => 0, 'transAmount' => 100.0],
            ]),
    ]);

    $startedAt = microtime(true);

    Credo::verify('vs_slow');

    $elapsedMs = (microtime(true) - $startedAt) * 1000;

    expect($elapsedMs)->toBeGreaterThanOrEqual(150.0);
});
