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
DB_HOST=aws-1-eu-central-1.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.your-project-ref
DB_PASSWORD=your-supabase-database-password
DB_SSLMODE=require
```

Use the Session Pooler connection string for local migrations when the direct database host is IPv6-only. Copy the exact host, port, and username from **Project Settings > Database > Connection string > Session pooler**.

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
GET  /api/availability?date=YYYY-MM-DD
GET  /api/availability?date=YYYY-MM-DD&room_id={room_id}
GET  /api/availability?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
GET  /api/rooms/{room_id}/availability?date=YYYY-MM-DD
POST /api/reservations
```

Date ranges include both endpoints and may span up to 31 calendar days.
The optional `room_id` filter also works with ranges. The response contains one
entry per active room and date, ordered by date and then room name.

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

## Room schedules and closures

Apply migrations before deploying the matching frontend. Schedule management
requires admin authentication:

- `GET|PUT /api/admin/rooms/{slug}/schedule`
- `GET|POST /api/admin/room-blocks`
- `PUT|DELETE /api/admin/room-blocks/{id}`

Schedules use `opening_hours`, a seven-element array from Monday to Sunday.
Each entry is either `null` (closed) or `{ "open": "09:00", "close": "21:00" }`.
The current booking window remains 09:00–21:00, in `Europe/Chisinau` local time.
Existing reservations are preserved when hours change. New reservations and
rescheduling must fit the current hours. Blocks require `room_id` (slug), `date`,
`start_time`, `end_time`, and optional internal `notes`. They cannot overlap
active reservations or other blocks. Adjacent intervals are permitted.
Public availability includes closures without exposing internal notes.
All schedule, block, and booking writes lock the room inside a transaction.

Legacy browser-only blocks are not imported automatically: recreate them with
explicit dates in the admin interface. Deploy the backend migration/API first,
then the frontend. Do not use the old frontend to manage blocks after rollout.

## Tests and initial setup

Confirmed bookings send a localized confirmation email with a signed cancellation
link. The link expires when the booking starts (Europe/Chisinau). Opening the link
does not cancel: the customer must submit the confirmation form. Changes to the
recipient, room, or booking time invalidate old links and queue a fresh email for
confirmed reservations. Internal reservation notes are not included in the email.

The public frontend detects browser language preferences (Romanian, English, or
Russian, including regional variants) and sends `locale` with the booking.
Unsupported languages fall back to Romanian. API clients can send `locale` as
`ro`, `en`, or `ru`; without it the public endpoint uses `Accept-Language`.
The language is saved on the reservation and used for queued email subjects,
email content, and the cancellation page. Admin edits preserve it unless an
explicit supported `locale` is supplied. Apply the locale migration before
deploying the updated backend/frontend. This does not translate the booking UI.

For delivery, configure `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`,
`MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, and a verified `MAIL_FROM_ADDRESS`.
Set `APP_URL` to the public HTTPS backend URL so email links use the correct host.
The default `log` mailer writes messages locally and does not deliver email.
With `QUEUE_CONNECTION=database`, run `php artisan queue:work --tries=3` as a
persistent worker. Messages are dispatched after commit and retried on delivery
failure. On serverless hosting, provide an external worker/queue execution setup;
HTTP requests alone do not process the database queue. No SMTP credentials are
committed to the repository.

Database seeding creates rooms only; it does not create an administrator account.
On a fresh installation, create the first administrator at `/admin/register`
before making the application publicly accessible.

Reservation history is preserved when cancelled slots are booked again. Apply
the migrations before running this version. The active-slot index migration
targets PostgreSQL and SQLite. Reverting it after rebooking requires resolving
duplicate room/date/start combinations first; a rollback must not discard history.

```bash
composer test
```

## Workflow

- `main` stays stable.
- Each task starts from the latest `main`.
- Branch names use the task name directly, without prefixes.
- Each completed task is committed and pushed.
