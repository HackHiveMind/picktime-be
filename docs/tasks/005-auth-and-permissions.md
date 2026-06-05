# 005 - Auth and Permissions

## Branch

`auth-and-permissions`

## Goal

Protect admin endpoints while keeping public booking endpoints open.

## Scope

- Add admin authentication.
- Protect admin routes with middleware.
- Keep public availability and reservation creation routes accessible.
- Add a simple local admin user setup path for development.

## Acceptance Criteria

- Unauthenticated requests cannot access admin routes.
- Authenticated admin requests can use admin endpoints.
- Public booking endpoints remain accessible.
- Auth tests cover allowed and blocked access.
- The task is committed and pushed on the `auth-and-permissions` branch.

