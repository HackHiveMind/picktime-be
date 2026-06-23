<?php

return [
    'enabled' => env('TELEMETRY_ENABLED', false),

    'driver' => env('TELEMETRY_DRIVER', 'cache'),

    'metrics_token' => env('TELEMETRY_METRICS_TOKEN'),

    'histogram_buckets' => [
        0.05,
        0.1,
        0.25,
        0.5,
        1.0,
        2.5,
        5.0,
    ],
];
