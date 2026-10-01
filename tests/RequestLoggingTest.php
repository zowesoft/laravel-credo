<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use ZoweSoft\LaravelCredo\CredoManager;
use ZoweSoft\LaravelCredo\Facades\Credo;

it('is silent by default when no log channel is configured', function () {
    config()->set('credo.log_channel', null);

    Log::shouldReceive('channel')->never();

    Http::fake([
        'https://api.credodemo.com/transaction/vs_quiet/verify' => Http::response([
            'status' => 200,
            'message' => 'ok',
            'data' => ['transRef' => 'vs_quiet', 'businessRef' => 'R', 'status' => 0, 'transAmount' => 100.0],
        ]),
    ]);

    $transaction = Credo::verify('vs_quiet');

    expect($transaction->credoReference)->toBe('vs_quiet');
});

it('logs path, status, duration and transRef for a verified transaction', function () {
    config()->set('credo.log_channel', 'credo-test');

    Log::shouldReceive('channel')->once()->with('credo-test')->andReturn(
        $logger = Mockery::mock(LoggerInterface::class)
    );

    $logger->shouldReceive('info')->once()->with('Credo API request', Mockery::on(function (array $context) {
        return $context['method'] === 'GET'
            && $context['path'] === '/transaction/vs_log/verify'
            && $context['status'] === 200
            && is_int($context['duration_ms'])
            && $context['attempts'] === 1
            && $context['transRef'] === 'vs_log';
    }));

    $logger->shouldNotReceive('error');

    Http::fake([
        'https://api.credodemo.com/transaction/vs_log/verify' => Http::response([
            'status' => 200,
            'message' => 'ok',
            'data' => ['transRef' => 'vs_log', 'businessRef' => 'R', 'status' => 0, 'transAmount' => 100.0],
        ]),
    ]);

    Credo::verify('vs_log');
});

it('logs the transRef taken from an initialize response', function () {
    config()->set('credo.log_channel', 'credo-test');

    Log::shouldReceive('channel')->once()->with('credo-test')->andReturn(
        $logger = Mockery::mock(LoggerInterface::class)
    );

    $logger->shouldReceive('info')->once()->with('Credo API request', Mockery::on(function (array $context) {
        return $context['method'] === 'POST'
            && $context['path'] === '/transaction/initialize'
            && $context['status'] === 200
            && $context['attempts'] === 1
            && $context['transRef'] === 'vs_init1';
    }));

    Http::fake([
        'https://api.credodemo.com/transaction/initialize' => Http::response([
            'status' => 200,
            'message' => 'ok',
            'data' => [
                'authorizationUrl' => 'https://pay.credocentral.com/checkout/xxx',
                'reference' => 'REF-1',
                'credoReference' => 'vs_init1',
                'crn' => '0000298483',
            ],
        ]),
    ]);

    Credo::payment()->amount(150000)->email('student@example.com')->send();
});

it('logs a single error record when every attempt fails to connect', function () {
    config()->set('credo.log_channel', 'credo-test');
    config()->set('credo.retry_max_attempts', 2);
    config()->set('credo.retry_base_delay_ms', 0);

    Log::shouldReceive('channel')->once()->with('credo-test')->andReturn(
        $logger = Mockery::mock(LoggerInterface::class)
    );

    $logger->shouldReceive('error')->once()->withArgs(function (string $message, array $context) {
        return $message === 'Credo API connection failed'
            && $context['method'] === 'GET'
            && $context['path'] === '/transaction/vs_dead/verify'
            && $context['attempts'] === 2
            && isset($context['duration_ms'], $context['error']);
    })->andReturnTrue();

    $logger->shouldNotReceive('info');

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
});

it('reports the retry-aware attempt count in the log record', function () {
    config()->set('credo.log_channel', 'credo-test');
    config()->set('credo.retry_max_attempts', 3);
    config()->set('credo.retry_base_delay_ms', 0);

    Log::shouldReceive('channel')->once()->with('credo-test')->andReturn(
        $logger = Mockery::mock(LoggerInterface::class)
    );

    $logger->shouldReceive('info')->once()->withArgs(function (string $message, array $context) {
        return $message === 'Credo API request'
            && $context['path'] === '/transaction/vs_retry/verify'
            && $context['status'] === 200
            && $context['attempts'] === 3
            && $context['transRef'] === 'vs_retry';
    })->andReturnTrue();

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

    Credo::verify('vs_retry');
});

it('tolerates a missing container without breaking logging-free requests', function () {
    $manager = $this->app->make(CredoManager::class);

    $property = new ReflectionProperty(CredoManager::class, 'app');
    $property->setAccessible(true);
    $property->setValue($manager, null);

    config()->set('credo.log_channel', 'credo-test');

    Log::shouldReceive('channel')->never();

    Http::fake([
        'https://api.credodemo.com/transaction/vs_x/verify' => Http::response([
            'status' => 200,
            'message' => 'ok',
            'data' => ['transRef' => 'vs_x', 'businessRef' => 'R', 'status' => 0, 'transAmount' => 100.0],
        ]),
    ]);

    $transaction = $manager->verify('vs_x');

    expect($transaction->credoReference)->toBe('vs_x');
});
