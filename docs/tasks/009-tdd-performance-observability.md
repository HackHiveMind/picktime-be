# TDD Performance Observability

## Goal

Protect the room booking toggle with a one-second KPI and expose service-layer telemetry for Prometheus/Grafana.

## Completed Slice

- Added `AdminRoomService` for room create/update/toggle behavior.
- Added a `MetricsRecorder` abstraction.
- Added `NullMetricsRecorder` for disabled telemetry.
- Added `CacheMetricsRecorder` for Prometheus exposition.
- Instrumented room booking toggle:
  - `booking_room_toggle_total`
  - `booking_room_toggle_duration_seconds`
- Added `GET /api/metrics`.
- Added local Prometheus/Grafana Docker Compose stack.
- Added k6 script for the room toggle KPI.

## Tests

```bash
php artisan test tests/Feature/AdminRoomApiTest.php
php artisan test tests/Feature/TelemetryMetricsTest.php
php artisan test tests/Feature/AdminReservationApiTest.php
```

## Next Handover Prompt

```text
Continue the observability rollout for the Laravel booking backend.

Current state:
- AdminRoomService emits room toggle metrics.
- AdminReservationService emits admin reservation create/update/cancel/delete metrics.
- PublicBookingService emits public booking create metrics.
- API middleware emits request count, request duration, and API error metrics.
- /api/metrics exposes Prometheus text metrics.
- Docker Compose includes Prometheus and Grafana.
- k6 room toggle KPI script exists.

Next tasks:
1. Add CI wiring for optional k6 room toggle KPI runs.
2. Decide whether production metrics should remain cache-backed or move to a dedicated Prometheus client/storage adapter.
3. Add alert rules for room toggle p95 > 1s, API p95 > 1s, and reservation conflict spikes.
4. Add deployment notes for `TELEMETRY_ENABLED` and `TELEMETRY_METRICS_TOKEN`.
5. Run the relevant feature tests and full composer test.
```
