<?php

namespace Donmanueldev\NativephpCharts;

use Illuminate\Support\ServiceProvider;

class NativePHPChartsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nativephp-charts.php', 'nativephp-charts');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/nativephp-charts.php' => config_path('nativephp-charts.php'),
        ], 'nativephp-charts-config');
    }
}
