<?php

namespace ZoweSoft\LaravelCredo\Tests;

use Orchestra\Testbench\TestCase as Testbench;
use ZoweSoft\LaravelCredo\CredoManager;
use ZoweSoft\LaravelCredo\CredoServiceProvider;

abstract class TestCase extends Testbench
{
    protected const TEST_PUBLIC_KEY = '0PUB-TEST-KEY';

    protected const TEST_SECRET_KEY = '0PRI-TEST-KEY';

    protected const LIVE_PUBLIC_KEY = '1PUB-LIVE-KEY';

    protected const LIVE_SECRET_KEY = '1PRI-LIVE-KEY';

    protected function getPackageProviders($app): array
    {
        return [CredoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('credo.mode', 'DEMO');
        $app['config']->set('credo.public_key', self::TEST_PUBLIC_KEY);
        $app['config']->set('credo.secret_key', self::TEST_SECRET_KEY);
        $app['config']->set('credo.timeout', 30);
    }

    protected function credo(): CredoManager
    {
        return $this->app->make(CredoManager::class);
    }
}
