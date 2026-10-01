<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use ZoweSoft\LaravelCredo\Client;
use ZoweSoft\LaravelCredo\CredoManager;
use ZoweSoft\LaravelCredo\Exceptions\InvalidConfigurationException;
use ZoweSoft\LaravelCredo\Facades\Credo;
use ZoweSoft\LaravelCredo\Tests\TestCase;

function credoConfigurationIsValid(): bool
{
    try {
        Credo::verifyConfiguration();

        return true;
    } catch (InvalidConfigurationException $exception) {
        return false;
    }
}

it('boots the package and resolves the manager via the facade', function () {
    expect(Credo::mode())->toBe('DEMO');
    expect(Credo::isLiveMode())->toBeFalse();
    expect($this->app->make(CredoManager::class))->toBeInstanceOf(CredoManager::class);
});

it('binds the manager as a singleton sharing the http factory', function () {
    $first = $this->app->make(CredoManager::class);
    $second = $this->app->make(CredoManager::class);

    expect($first)->toBe($second);
    expect($first->http())->toBe(Http::getFacadeRoot());
});

it('targets the demo api in demo mode', function () {
    expect(Credo::baseUrl())->toBe('https://api.credodemo.com');
    expect(Credo::publicKey())->toBe(TestCase::TEST_PUBLIC_KEY);
    expect(Credo::secretKey())->toBe(TestCase::TEST_SECRET_KEY);
});

it('targets the live api in live mode while keeping the same key pair', function () {
    config()->set('credo.mode', 'LIVE');

    expect(Credo::mode())->toBe('LIVE');
    expect(Credo::baseUrl())->toBe('https://api.credocentral.com');
    expect(Credo::publicKey())->toBe(TestCase::TEST_PUBLIC_KEY);
    expect(Credo::secretKey())->toBe(TestCase::TEST_SECRET_KEY);
});

it('allows base urls to be overridden per mode', function () {
    config()->set('credo.base_urls.demo', 'https://api.staging.credodemo.com/');

    expect(Credo::baseUrl())->toBe('https://api.staging.credodemo.com');

    config()->set('credo.mode', 'LIVE');
    config()->set('credo.base_urls.live', 'https://api.future.credocentral.com');

    expect(Credo::baseUrl())->toBe('https://api.future.credocentral.com');
});

it('accepts keys matching the active mode prefixes', function () {
    config()->set('credo.mode', 'DEMO');

    expect(credoConfigurationIsValid())->toBeTrue();

    config()->set('credo.mode', 'LIVE');
    config()->set('credo.public_key', TestCase::LIVE_PUBLIC_KEY);
    config()->set('credo.secret_key', TestCase::LIVE_SECRET_KEY);

    expect(credoConfigurationIsValid())->toBeTrue();
});

it('rejects a demo key used in live mode', function () {
    config()->set('credo.mode', 'LIVE');

    Credo::verifyConfiguration();
})->throws(InvalidConfigurationException::class, 'does not look like a LIVE key');

it('rejects a live key used in demo mode', function () {
    config()->set('credo.public_key', TestCase::LIVE_PUBLIC_KEY);

    Credo::verifyConfiguration();
})->throws(InvalidConfigurationException::class, 'does not look like a DEMO key');

it('rejects swapped public and secret keys', function () {
    config()->set('credo.public_key', TestCase::TEST_SECRET_KEY);
    config()->set('credo.secret_key', TestCase::TEST_PUBLIC_KEY);

    Credo::verifyConfiguration();
})->throws(InvalidConfigurationException::class, 'public key');

it('can disable key validation', function () {
    config()->set('credo.mode', 'LIVE');
    config()->set('credo.validate_keys', false);

    expect(credoConfigurationIsValid())->toBeTrue();
});

it('allows prefixes to be overridden or blanked per role', function () {
    config()->set('credo.public_key', 'CUSTOM-PUB-KEY');
    config()->set('credo.key_prefixes.demo.public', 'CUSTOM-PUB');

    expect(credoConfigurationIsValid())->toBeTrue();

    config()->set('credo.key_prefixes.demo.secret', '');

    expect(credoConfigurationIsValid())->toBeTrue();
});

it('throws when keys are missing', function () {
    config()->set('credo.public_key', null);
    config()->set('credo.secret_key', null);

    Credo::verifyConfiguration();
})->throws(InvalidConfigurationException::class);

it('accepts an externally injected client sharing the manager', function () {
    $fakeFactory = (new Factory)->fake([
        'https://api.credodemo.com/transaction/initialize' => ['status' => 200, 'message' => 'ok', 'data' => []],
    ]);

    $manager = new CredoManager($this->app['config'], $fakeFactory);
    $client = new Client($manager);

    expect($manager->usingClient($client)->client())->toBe($client);
});
