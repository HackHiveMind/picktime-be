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

## Tests

```bash
composer test
```

## Workflow

- `main` stays stable.
- Each task starts from the latest `main`.
- Branch names use the task name directly, without prefixes.
- Each completed task is committed and pushed.

