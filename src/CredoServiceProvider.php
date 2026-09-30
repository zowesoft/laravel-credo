<?php

namespace ZoweSoft\LaravelCredo;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

class CredoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/credo.php', 'credo');

        $this->app->singleton(CredoManager::class, function ($app) {
            return new CredoManager(
                $app->make('config'),
                $app->make(Factory::class),
            );
        });

        $this->app->bind(Contracts\PaymentGateway::class, function ($app) {
            return $app->make(CredoManager::class);
        });

        $this->app->alias(CredoManager::class, 'credo');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/credo.php' => config_path('credo.php'),
            ], 'credo-config');
        }
    }
}
