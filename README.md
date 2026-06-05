# Picktime Backend

Laravel API backend for the Picktime/iHUB booking platform.

## Requirements

- PHP 8.3 or newer
- Composer
- Node.js and npm
- SQLite for local development, or another Laravel-supported database

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

## Development

```bash
php artisan serve
```

The local API health endpoint is available at:

```text
GET /api/health
```

## Public API

```text
GET  /api/rooms
GET  /api/rooms/{room_id}/availability?date=YYYY-MM-DD
POST /api/reservations
```

Public reservations accept this JSON shape:

```json
{
  "room_id": "imeet",
  "date": "2026-06-10",
  "start_time": "09:00",
  "first_name": "Ana",
  "last_name": "Popescu",
  "email": "ana@example.com",
  "phone": "+373 600 00 000",
  "notes": "Project meeting"
}
```

## Tests

```bash
composer test
```

## Workflow

- `main` stays stable.
- Each task starts from the latest `main`.
- Branch names use the task name directly, without prefixes.
- Each completed task is committed and pushed.
