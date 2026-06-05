# 008 - Connect Supabase Database

## Branch

`connect-supabase-database`

## Goal

Connect the local Laravel backend to the Supabase PostgreSQL database and run the existing migrations.

## Scope

- Configure local `.env` with Supabase Session Pooler values.
- Keep Supabase secrets out of git.
- Run Laravel migrations against Supabase.
- Verify that required backend tables exist.
- Document the working pooler-based connection format.

## Acceptance Criteria

- `php artisan migrate --force` runs successfully against Supabase.
- `php artisan migrate:status` shows all existing migrations as `Ran`.
- Supabase contains the Laravel and booking tables.
- The task is committed and pushed on the `connect-supabase-database` branch without secrets.

