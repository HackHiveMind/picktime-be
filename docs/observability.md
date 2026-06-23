# Observability

This backend exposes Prometheus-compatible metrics at:

```text
GET /api/metrics
```

Enable metrics locally with:

```env
TELEMETRY_ENABLED=true
TELEMETRY_DRIVER=cache
TELEMETRY_METRICS_TOKEN=
```

Set `TELEMETRY_METRICS_TOKEN` in shared or production environments. When it is set, Prometheus must scrape with a bearer token.

## Local Stack

Start Laravel:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Start Prometheus and Grafana:

```bash
docker compose up
```

Open:

```text
Prometheus: http://localhost:9090
Grafana:    http://localhost:3000
Login:      admin / admin
```

Prometheus scrapes `host.docker.internal:8000/api/metrics`.

## Metrics

```text
booking_room_toggle_total
booking_room_toggle_duration_seconds
booking_reservation_create_total
booking_reservation_create_duration_seconds
booking_reservation_update_total
booking_reservation_update_duration_seconds
booking_reservation_cancel_total
booking_reservation_cancel_duration_seconds
booking_reservation_delete_total
booking_reservation_delete_duration_seconds
```

Labels:

```text
service
operation
room_id
status
```

Do not add PII labels such as guest name, email, or phone.

## Grafana Query Map

Room toggle p95:

```promql
histogram_quantile(0.95, sum(rate(booking_room_toggle_duration_seconds_bucket[5m])) by (le))
```

Room toggle p99:

```promql
histogram_quantile(0.99, sum(rate(booking_room_toggle_duration_seconds_bucket[5m])) by (le))
```

Room toggles per minute:

```promql
sum(rate(booking_room_toggle_total[1m])) * 60
```

Room toggle error rate:

```promql
sum(rate(booking_room_toggle_total{status!="success"}[5m])) / sum(rate(booking_room_toggle_total[5m]))
```

Reservation create rate by service:

```promql
sum(rate(booking_reservation_create_total[5m])) by (service)
```

Reservation update rate by service:

```promql
sum(rate(booking_reservation_update_total[5m])) by (service)
```

Reservation cancel rate:

```promql
sum(rate(booking_reservation_cancel_total[5m])) by (service)
```

Reservation delete rate:

```promql
sum(rate(booking_reservation_delete_total[5m])) by (service)
```

Reservation create p95 latency by service:

```promql
histogram_quantile(0.95, sum(rate(booking_reservation_create_duration_seconds_bucket[5m])) by (le, service))
```

Reservation conflict rate:

```promql
sum(rate(booking_reservation_create_total{status="conflict"}[5m])) by (service)
```

## Performance KPI

Room booking toggle KPI:

```text
PATCH /api/admin/rooms/{room:slug}
p95 < 1000ms
```

Run the load test with k6:

```bash
BASE_URL=http://127.0.0.1:8000 ADMIN_TOKEN=... ROOM_ID=imeet npm run perf:room-toggle
```

The script fails when p95 is not under one second.
