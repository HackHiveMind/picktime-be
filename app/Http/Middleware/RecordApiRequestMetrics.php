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
}
