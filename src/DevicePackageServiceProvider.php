<?php

namespace iProtek\Device;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate; 

class DevicePackageServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register package services
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        //DEFINE GATES

        //DEFINE ROLES BASE ON XRAC

        // Bootstrap package services
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'iprotek_device');

        $this->mergeConfigFrom(
            __DIR__ . '/../config/iprotek.php', 'iprotek_device'
        );
    }
}