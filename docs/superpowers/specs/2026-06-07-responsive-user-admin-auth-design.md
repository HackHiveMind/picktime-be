# Responsive User Interface and Admin Auth Design

## Context

The current Laravel app exposes public booking APIs and admin reservation APIs. The frontend entry point is still minimal, and the default Laravel welcome page is not a real booking interface. Admin reservation routes currently exist, but they are not protected by authentication.

## Goals

- Make the public user booking interface responsive and usable on mobile, tablet, and desktop.
- Add admin authentication with login, logout, and one-time registration for the first admin account.
- Protect admin API endpoints while keeping public booking endpoints open.
- Keep the implementation simple in Laravel, Blade, Tailwind, and standard Laravel session auth.

## Non-Goals

- No password reset flow in this iteration.
- No roles or multi-level permissions beyond "authenticated admin user".
- No public admin registration after the first user exists.
- No separate React app in this iteration.

## Public Booking UI

The root page will become the booking experience instead of a marketing or framework welcome screen. It will be mobile-first:

- On phones, content stacks in a single column with clear room/date/time selection and a simple booking form.
- On desktop, room/date selection and booking details can sit side by side for faster scanning.
- The interface will use existing public endpoints for rooms, availability, and reservation creation.
- Form errors and success states will be visible near the form and readable on small screens.

## Admin Auth Flow

The admin area will use Laravel session authentication:

- `GET /admin/register` shows registration only when no users exist.
- `POST /admin/register` creates the first admin user and logs them in.
- If any user exists, register requests are rejected or redirected to login.
- `GET /admin/login` shows the login form.
- `POST /admin/login` authenticates by email and password.
- `POST /admin/logout` ends the session.

This keeps setup easy for local and first deployment while avoiding an open public admin registration page.

## Protected Admin Area

Admin pages and APIs will require authentication:

- `/admin` and admin UI pages require `auth`.
- `/api/admin/reservations*` require `auth`.
- Public endpoints stay open:
  - `GET /api/rooms`
  - `GET /api/rooms/{room}/availability`
  - `POST /api/reservations`

Unauthenticated API requests should return JSON 401 when the request expects JSON. Browser requests to admin pages should redirect to `/admin/login`.

## Admin UI

The admin UI will start simple:

- Authenticated admins can view reservations.
- Existing create/edit/cancel/delete APIs remain available for the admin UI.
- The page uses compact controls on mobile and a wider table/list layout on desktop.
- A logout action appears in the admin header.

## Testing

Feature tests will cover:

- First admin registration succeeds.
- Second/admin registration after a user exists is blocked.
- Login succeeds with valid credentials and fails with invalid credentials.
- Logout ends the session.
- Unauthenticated admin API access is blocked.
- Authenticated admin API access works.
- Public booking endpoints remain accessible without login.

Frontend verification will include a Vite production build and browser checks at mobile and desktop widths.

## Implementation Notes

- Use Blade views for public booking, login, register, and admin pages.
- Keep JavaScript small and scoped to form interactions and API calls.
- Use Tailwind utilities and custom CSS only where it improves maintainability.
- Avoid adding large frontend dependencies for this iteration.
