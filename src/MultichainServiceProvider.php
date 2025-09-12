<?php

namespace EskieGwapo\Multichain;

use Illuminate\Support\ServiceProvider;

class MultichainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/multichain.php', 'multichain');

        $this->app->singleton(Multichain::class, function ($app) {
            return new Multichain();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/multichain.php' => config_path('multichain.php'),
        ], 'config');
    }
}