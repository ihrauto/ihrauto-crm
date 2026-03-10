# IHRAUTO CRM

IHRAUTO CRM is a Laravel 12 multi-tenant workshop platform for automotive businesses. It combines customer management, check-in, tire storage, work orders, appointments, invoicing, payments, inventory, reporting, and super-admin tenant oversight in a single Blade monolith.

## Product Scope

- Public marketing and pricing surface at `/`
- Authenticated tenant workspace for workshop operations
- Bearer-token tenant API for machine-to-machine integrations
- Super-admin control plane under `/admin`
- Console runbooks for destructive or privileged operations

## Core Standards

- Web tenant context resolves from authenticated user, session, route, domain, or subdomain.
- Public API access requires `Authorization: Bearer <tenant-api-token>`.
- `users.email` is globally unique across the platform.
- `customers.email` is unique within a tenant, not globally.
- Developer-only tenant switching lives under `/dev/*` and should never be exposed outside local environments.
- Documentation, changelog, and task tracking are part of the definition of done for non-trivial changes.

## Documentation

Start with [docs/README.md](docs/README.md). The documentation set is organized for a multi-engineer team and includes:

- Architecture and request lifecycle
- Core business workflows
- Code ownership and component mapping
- Function inventory by class
- Documentation, changelog, and task-tracking standards
- Engineering board and decision log

## Local Setup

1. Copy `.env.example` to `.env`.
2. Configure database, mail, Resend, and Google OAuth credentials if those flows are used.
3. Install dependencies with `composer install` and `npm install`.
4. Generate the app key with `php artisan key:generate`.
5. Run migrations and seed roles with `php artisan migrate --seed`.
6. Start the app with `composer run dev`.

## Key Commands

- `php artisan ops:bootstrap-super-admin`
  Seeds roles, permissions, and the configured super-admin account.
- `php artisan tenant:rotate-api-token {tenant}`
  Revokes active API tokens for a tenant and prints a new token once.
- `php artisan tenant:purge {tenant}`
  Irreversibly deletes a tenant and its data after confirmation.
- `php artisan test`
  Runs the automated test suite against the self-contained SQLite test profile.

## Working Agreement

When engineers ship meaningful changes, they should update:

1. The relevant docs under `docs/`
2. `CHANGELOG.md`
3. `docs/tracking/engineering-board.md`
4. `docs/tracking/decision-log.md` when the change alters an architectural rule or operating model

## Security Notes

- Do not add setup, restore, cleanup, or debug HTTP endpoints for production use.
- Tenant API tokens are hashed at rest and should be rotated only through the CLI command.
- Legacy API paths still exist for compatibility, but they require the same bearer token as `/api/v1/*` and should be treated as deprecated.
