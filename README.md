# Picktime Backend

Laravel API backend for the Picktime/iHUB booking platform.

## Requirements

- PHP 8.3 or newer
- Composer
- Node.js and npm
- Supabase PostgreSQL database

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

## Supabase Database

Set these values in `.env` from the Supabase project database settings:

```env
DB_CONNECTION=pgsql
DB_HOST=db.your-project-ref.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-supabase-database-password
DB_SSLMODE=require
```

Use the direct database connection for migrations. If the app is deployed in a serverless/runtime with many short connections, use the Supabase pooler host and port provided by Supabase for runtime traffic.

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
