# 004 - Admin API

## Branch

`admin-api`

## Goal

Expose admin API endpoints for calendar management.

## Scope

- Add endpoint for weekly reservation calendar data.
- Add endpoints to create, edit, cancel, and delete reservations.
- Support filtering by room and date range.
- Keep response shape friendly for the React admin calendar.

## Acceptance Criteria

- Admin calendar endpoint returns reservations grouped or filtered by date range.
- Admin mutation endpoints validate data and return consistent JSON.
- Reservation status changes are persisted.
- Feature tests cover admin calendar and mutation flows.
- The task is committed and pushed on the `admin-api` branch.

