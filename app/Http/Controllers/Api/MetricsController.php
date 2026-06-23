<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telemetry\CacheMetricsRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __invoke(Request $request, CacheMetricsRecorder $metrics): Response
    {
        $token = config('telemetry.metrics_token');

        if (filled($token) && ! hash_equals((string) $token, (string) $request->bearerToken())) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        return new Response($this->render($metrics), Response::HTTP_OK, [
            'Content-Type' => 'text/plain; version=0.0.4',
        ]);
    }

    private function render(CacheMetricsRecorder $metrics): string
    {
        $lines = [
            '# HELP booking_room_toggle_total Room booking toggle attempts.',
            '# TYPE booking_room_toggle_total counter',
            '# HELP booking_room_toggle_duration_seconds Room booking toggle duration.',
            '# TYPE booking_room_toggle_duration_seconds histogram',
            '# HELP booking_reservation_create_total Reservation create attempts.',
            '# TYPE booking_reservation_create_total counter',
            '# HELP booking_reservation_create_duration_seconds Reservation create duration.',
            '# TYPE booking_reservation_create_duration_seconds histogram',
            '# HELP booking_reservation_update_total Reservation update attempts.',
            '# TYPE booking_reservation_update_total counter',
            '# HELP booking_reservation_update_duration_seconds Reservation update duration.',
            '# TYPE booking_reservation_update_duration_seconds histogram',
            '# HELP booking_reservation_cancel_total Reservation cancel attempts.',
            '# TYPE booking_reservation_cancel_total counter',
            '# HELP booking_reservation_cancel_duration_seconds Reservation cancel duration.',
            '# TYPE booking_reservation_cancel_duration_seconds histogram',
            '# HELP booking_reservation_delete_total Reservation delete attempts.',
            '# TYPE booking_reservation_delete_total counter',
            '# HELP booking_reservation_delete_duration_seconds Reservation delete duration.',
            '# TYPE booking_reservation_delete_duration_seconds histogram',
            '# HELP booking_api_requests_total API request count.',
            '# TYPE booking_api_requests_total counter',
            '# HELP booking_api_request_duration_seconds API request duration.',
            '# TYPE booking_api_request_duration_seconds histogram',
            '# HELP booking_api_errors_total API error response count.',
            '# TYPE booking_api_errors_total counter',
        ];

        foreach ($metrics->series() as $series) {
            if ($series['type'] === 'counter') {
                $lines[] = $series['metric'].$this->labels($series['labels']).' '.$metrics->value(
                    $series['metric'],
                    $series['labels'],
                    'counter',
                );
            }

            if ($series['type'] === 'histogram') {
                foreach (config('telemetry.histogram_buckets', []) as $bucket) {
                    $labels = [...$series['labels'], 'le' => (string) $bucket];
                    $lines[] = $series['metric'].'_bucket'.$this->labels($labels).' '.$metrics->value(
                        $series['metric'],
                        $labels,
                        'histogram_bucket',
                    );
                }

                $infiniteLabels = [...$series['labels'], 'le' => '+Inf'];
                $lines[] = $series['metric'].'_bucket'.$this->labels($infiniteLabels).' '.$metrics->value(
                    $series['metric'],
                    $infiniteLabels,
                    'histogram_bucket',
                );
                $lines[] = $series['metric'].'_sum'.$this->labels($series['labels']).' '.($metrics->value(
                    $series['metric'],
                    $series['labels'],
                    'histogram_sum',
                ) / 1_000_000);
                $lines[] = $series['metric'].'_count'.$this->labels($series['labels']).' '.$metrics->value(
                    $series['metric'],
                    $series['labels'],
                    'histogram_count',
                );
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function labels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        ksort($labels);

        return '{'.collect($labels)
            ->map(fn (string $value, string $key): string => $key.'="'.addcslashes($value, "\\\"\n").'"')
            ->implode(',').'}';
    }
}
