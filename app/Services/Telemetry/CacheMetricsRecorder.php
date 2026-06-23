<?php

namespace App\Services\Telemetry;

use Illuminate\Support\Facades\Cache;

class CacheMetricsRecorder implements MetricsRecorder
{
    public function increment(string $metric, array $labels = []): void
    {
        $key = $this->seriesKey($metric, $labels, 'counter');

        Cache::increment($key);
        $this->rememberSeries($metric, $labels, 'counter');
    }

    public function observe(string $metric, float $value, array $labels = []): void
    {
        $this->rememberSeries($metric, $labels, 'histogram');

        Cache::increment($this->seriesKey($metric, $labels, 'histogram_count'));
        Cache::increment($this->seriesKey($metric, $labels, 'histogram_sum'), (int) round($value * 1_000_000));

        foreach (config('telemetry.histogram_buckets', []) as $bucket) {
            if ($value <= (float) $bucket) {
                Cache::increment($this->seriesKey($metric, [...$labels, 'le' => (string) $bucket], 'histogram_bucket'));
            }
        }

        Cache::increment($this->seriesKey($metric, [...$labels, 'le' => '+Inf'], 'histogram_bucket'));
    }

    /**
     * @return array<int, array{metric: string, labels: array<string, string>, type: string}>
     */
    public function series(): array
    {
        return Cache::get($this->registryKey(), []);
    }

    /**
     * @param  array<string, string>  $labels
     */
    public function value(string $metric, array $labels, string $type): int
    {
        return (int) Cache::get($this->seriesKey($metric, $labels, $type), 0);
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function rememberSeries(string $metric, array $labels, string $type): void
    {
        $series = $this->series();
        $entry = [
            'metric' => $metric,
            'labels' => $labels,
            'type' => $type,
        ];

        foreach ($series as $existing) {
            if ($existing === $entry) {
                return;
            }
        }

        $series[] = $entry;

        Cache::forever($this->registryKey(), $series);
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function seriesKey(string $metric, array $labels, string $type): string
    {
        ksort($labels);

        return 'telemetry:'.$type.':'.$metric.':'.sha1(json_encode($labels, JSON_THROW_ON_ERROR));
    }

    private function registryKey(): string
    {
        return 'telemetry:series';
    }
}
