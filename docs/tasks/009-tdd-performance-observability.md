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
- /api/metrics exposes Prometheus text metrics.
- Docker Compose includes Prometheus and Grafana.
- k6 room toggle KPI script exists.

Next tasks:
1. Extract reservation create/update/cancel/delete behavior into service classes.
2. Instrument those services with MetricsRecorder.
3. Add metrics for reservation create/update/cancel/delete totals and duration histograms.
4. Extend Grafana dashboard and docs with the new query map.
5. Run the relevant feature tests and full composer test.
```
