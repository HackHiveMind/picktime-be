<?php

namespace App\Providers;

use App\Services\Telemetry\CacheMetricsRecorder;
use App\Services\Telemetry\MetricsRecorder;
use App\Services\Telemetry\NullMetricsRecorder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CacheMetricsRecorder::class);

        $this->app->bind(MetricsRecorder::class, function ($app): MetricsRecorder {
            if (! config('telemetry.enabled')) {
                return $app->make(NullMetricsRecorder::class);
            }

            return $app->make(CacheMetricsRecorder::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
