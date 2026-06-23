<?php

namespace App\Services\Telemetry;

class NullMetricsRecorder implements MetricsRecorder
{
    public function increment(string $metric, array $labels = []): void
    {
        //
    }

    public function observe(string $metric, float $value, array $labels = []): void
    {
        //
    }
}
