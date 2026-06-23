<?php

namespace App\Services\Telemetry;

interface MetricsRecorder
{
    /**
     * @param  array<string, string>  $labels
     */
    public function increment(string $metric, array $labels = []): void;

    /**
     * @param  array<string, string>  $labels
     */
    public function observe(string $metric, float $value, array $labels = []): void;
}
