<?php

namespace ZoweSoft\LaravelCredo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

/**
 * Registers and bootstraps the Credo Laravel integration.
 *
 * - Binds {@see CredoManager} as a singleton under its class name and the
 *   `credo` alias, satisfying the {@see Contracts\PaymentGateway} interface.
 * - Publishes `config/credo.php` under the `credo-config` tag.
 */
class CredoServiceProvider extends ServiceProvider
{
    /**
     * Merge package config and bind the manager into the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/credo.php', 'credo');

        $this->app->singleton(CredoManager::class, function ($app) {
            return new CredoManager(
                $app->make('config'),
                Http::getFacadeRoot(),
                $app,
            );
        });

        $this->app->bind(Contracts\PaymentGateway::class, function ($app) {
            return $app->make(CredoManager::class);
        });

        $this->app->alias(CredoManager::class, 'credo');
    }

    /**
     * Publish the package configuration file when running in the console.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/credo.php' => config_path('credo.php'),
            ], 'credo-config');
        }
    }
}
