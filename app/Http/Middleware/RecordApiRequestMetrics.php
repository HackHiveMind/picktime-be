<?php

namespace App\Http\Middleware;

use App\Services\Telemetry\MetricsRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordApiRequestMetrics
{
    public function __construct(
        private readonly MetricsRecorder $metrics,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/metrics')) {
            return $next($request);
        }

        $startedAt = microtime(true);
        $statusCode = 500;

        try {
            $response = $next($request);
            $statusCode = $response->getStatusCode();
            $this->appendServerTiming($request, $response, microtime(true) - $startedAt);

            return $response;
        } catch (\Throwable $exception) {
            throw $exception;
        } finally {
            $labels = [
                'method' => $request->method(),
                'route' => $this->routeLabel($request),
                'status_code' => (string) $statusCode,
            ];

            $this->metrics->increment('booking_api_requests_total', $labels);
            $this->metrics->observe(
                'booking_api_request_duration_seconds',
                microtime(true) - $startedAt,
                $labels,
            );

            if ($statusCode >= 400) {
                $this->metrics->increment('booking_api_errors_total', $labels);
            }
        }
    }

    private function routeLabel(Request $request): string
    {
        $route = $request->route();

        if ($route) {
            return '/'.ltrim($route->uri(), '/');
        }

        return '/'.ltrim($request->path(), '/');
    }

    private function appendServerTiming(Request $request, Response $response, float $totalDuration): void
    {
        if (! $this->shouldExposeTimingDetails($request)) {
            return;
        }

        $timings = [
            'total' => $totalDuration,
            'admin_auth' => $request->attributes->get('timing.admin_auth'),
            'toggle_service' => $request->attributes->get('timing.admin_room_toggle_service'),
            'toggle_db' => $request->attributes->get('timing.admin_room_toggle_db'),
        ];

        $header = collect($timings)
            ->filter(fn (mixed $duration): bool => is_float($duration) || is_int($duration))
            ->map(fn (float|int $duration, string $name): string => sprintf('%s;dur=%.2f', $name, $duration * 1000))
            ->implode(', ');

        if ($header !== '') {
            $response->headers->set('Server-Timing', $header);
            $response->headers->set('X-Booking-Timing', $header);
        }
    }

    private function shouldExposeTimingDetails(Request $request): bool
    {
        return in_array($request->method(), ['PATCH', 'PUT'], true)
            && $request->is('api/admin/rooms/*');
    }
}
