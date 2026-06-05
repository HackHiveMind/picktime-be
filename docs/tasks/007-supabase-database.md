# 007 - Supabase Database

## Branch

`supabase-database`

## Goal

Configure the backend to use Supabase PostgreSQL instead of local SQLite.

## Scope

- Set PostgreSQL as the default Laravel database connection.
- Update `.env.example` with Supabase database variables.
- Require SSL for PostgreSQL connections.
- Document the Supabase database setup in the README.
- Keep local secrets out of git.

## Acceptance Criteria

- Laravel configuration defaults to `pgsql`.
- PostgreSQL SSL mode defaults to `require`.
- Tests cover the database default configuration.
- The task is committed and pushed on the `supabase-database` branch.

