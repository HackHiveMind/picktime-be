<?php

namespace App\Providers;

use App\Services\Telemetry\CacheMetricsRecorder;
use App\Services\Telemetry\MetricsRecorder;
use App\Services\Telemetry\NullMetricsRecorder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('public-api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Reservations consume real slots => a much tighter cap.
        RateLimiter::for('reservations', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Brute-force on passwords: key on IP + email so a single NAT network is
        // not locked out when one account is attacked.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);
    }
}
