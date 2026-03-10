# Architecture Overview

## System Summary

IHRAUTO CRM is a Laravel 12 monolith for automotive workshop operations. It is multi-tenant at the row level, server-rendered with Blade, and organized around workshop workflows instead of isolated microservices.

The application serves five surfaces:

1. Public marketing and pricing pages
2. Authenticated tenant web application
3. Tenant API for machine-to-machine access
4. Super-admin control plane
5. Console-only operational commands

## Technology Stack

| Layer | Implementation |
| --- | --- |
| Backend | PHP, Laravel 12 |
| Frontend | Blade templates, Tailwind, Vite |
| Persistence | PostgreSQL in normal environments, SQLite in tests |
| Auth | Laravel auth flows plus Google OAuth |
| Authorization | Spatie roles and permissions, gates, policies, route middleware |
| Multi-tenancy | Custom row-level tenancy using middleware, global scope, and helper functions |
| Reporting | Query-driven service classes |
| Billing | Invoices, payments, observers, and service-layer transitions |
| Ops | Artisan commands for privileged maintenance tasks |

## Architectural Shape

The app is intentionally monolithic. The main architectural split is by responsibility rather than by deployable unit:

- `routes/` defines the HTTP surface and access boundaries.
- `app/Http/Controllers/` owns request orchestration and responses.
- `app/Services/` owns domain workflows and reusable business logic.
- `app/Models/` owns persistence, relationships, scopes, and simple domain behavior.
- `app/Http/Middleware/` owns tenant resolution, access boundaries, and request guards.
- `app/Policies/` and permissions own record-level and module-level authorization.
- `app/Console/Commands/` owns privileged operational runbooks.

## Domain Map

The system revolves around a workshop operating pipeline:

`Tenant -> Users -> Customers -> Vehicles -> Check-ins / Appointments / Tire Storage -> Work Orders -> Invoices -> Payments -> Reports`

Supporting platform capabilities:

- Tenant provisioning and lifecycle management
- Role and permission management
- Inventory and service catalog management
- Audit and event tracking
- Super-admin tenant oversight

## Multi-Tenancy Model

The tenancy model is row-level, not database-per-tenant in normal operation.

- Current tenant context is stored in `App\Support\TenantContext`.
- Web requests resolve tenant context through `TenantMiddleware`.
- API requests resolve tenant context through `AuthenticateTenantApiToken`.
- Tenant-owned models use `BelongsToTenant` plus `TenantScope`.
- Validation uses `TenantValidation` for tenant-scoped `exists` and `unique` rules.

This means tenant isolation depends on several layers working together:

1. Tenant resolution
2. Tenant-aware route access
3. Tenant-scoped model queries
4. Tenant-scoped validation rules
5. Authorization checks

## Security Model

The current operating model assumes:

- Public API access is not anonymous.
- Bearer tenant API tokens are the only supported public API credential.
- Super-admin actions are limited to authenticated users with the `super-admin` role.
- Route groups enforce module-level permissions at the boundary.
- Policies provide record-level authorization.
- Destructive platform operations move to artisan commands rather than ad hoc HTTP endpoints.

## External Interfaces

### Web

- `/` is the public pricing page.
- `/dashboard`, `/customers`, `/work-orders`, `/finance`, and similar routes are tenant workspace routes.
- `/admin/*` is the super-admin surface.
- `/dev/*` is local-only tooling.

### API

- `/api/v1/*` is the current tenant API.
- Legacy API paths remain temporarily for compatibility and include deprecation headers.
- Authentication is `Authorization: Bearer <tenant-api-token>`.

### Console

The console is the right place for irreversible or privileged platform operations:

- Super-admin bootstrap
- Tenant API token rotation
- Tenant purge
- Demo data and maintenance routines

## Design Constraints

These constraints define how future work should fit into the system:

- Preserve tenant isolation first. New queries, validation rules, and model binding must stay tenant-aware.
- Keep route-level module permissions as the first authorization boundary.
- Keep controllers thin enough that multi-step business transitions live in services.
- Keep invoice status transitions canonical and centralized.
- Keep privileged maintenance behavior out of public HTTP routes.

## Operational Hotspots

These areas deserve extra care when modified:

- `TenantMiddleware`, `TenantContext`, `TenantScope`, and `BelongsToTenant`
- Billing classes around invoices, payments, and work-order completion
- Provisioning and tenant lifecycle services
- Role and permission route groups
- API token auth and rate limiting
