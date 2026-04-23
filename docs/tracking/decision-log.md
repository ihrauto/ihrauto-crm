# Decision Log

This file records architectural and operational decisions that future engineers will otherwise have to rediscover from commit history.

## 2026-03-10

### DEC-001: Public API Access Requires Bearer Tenant Tokens

- Status: accepted
- Context: tenant identity could not rely on unauthenticated request headers.
- Decision: all public API access is authenticated with `Authorization: Bearer <tenant-api-token>`.
- Consequence: token rotation becomes an operational concern, and legacy API routes remain compatibility-only.

### DEC-002: Canonical Invoice Status Is Limited To Five Stored States

- Status: accepted
- Context: billing logic had drifted across controllers, observers, filters, and factories.
- Decision: persisted invoice status is limited to `draft`, `issued`, `partial`, `paid`, and `void`.
- Consequence: states such as `overdue` and `unpaid` are presentation-level derivatives, not canonical stored states.

### DEC-003: Tenant Switching Is A Local Developer Tool Only

- Status: accepted
- Context: developer tooling should not exist on the public production surface.
- Decision: the public homepage is `/`, and tenant switching remains under `/dev/*` for local use only.
- Consequence: production environments rely on real auth and tenant resolution, not manual switching helpers.

### DEC-004: Email Uniqueness Rules Differ By Domain Entity

- Status: accepted
- Context: staff accounts and customer records have different identity and tenancy requirements.
- Decision: `users.email` remains globally unique; `customers.email` is unique only within a tenant.
- Consequence: import, onboarding, and validation logic must preserve that distinction.

## 2026-03-11

### DEC-005: Closed-Beta Billing Remains Manual Until Payment Gateway Launch

- Status: accepted
- Context: the production beta needs a real subscription recovery path before self-serve checkout is integrated.
- Decision: tenant-facing pricing now routes authenticated users to a manual-billing page, while the local-only checkout/process routes remain development mocks only.
- Consequence: super-admins need first-class plan and renewal-date controls, and expired tenants must always be able to reach the billing surface.

### DEC-006: Production Uploads, Backups, Cache, And Queues Must Be Managed Off-App

- Status: accepted
- Context: redeploy-safe production behavior cannot depend on container-local files or database-backed queues/cache alone.
- Decision: uploads and backup archives are configured for object storage, while cache/queue runtime is configured for managed Redis and the scheduler runs as a supervised production process.
- Consequence: production env configuration must provide object-storage credentials, backup destination settings, Redis connectivity, and scheduler/worker observability through stdout/stderr.

## 2026-03-12

### DEC-007: Tenant Role Management Is Frozen For Launch

- Status: accepted
- Context: tenant-facing user management was operating on global Spatie roles, which allowed privilege escalation and cross-tenant permission mutation.
- Decision: tenant role assignment is limited to the fixed tenant-safe set `admin`, `manager`, `technician`, and `receptionist`, with tenant permission editing removed from the production route surface.
- Consequence: `super-admin` remains platform-only, tenant managers may manage only technician/receptionist users, and role customization is deferred until a true tenant-scoped role model exists.

### DEC-008: Production Bootstraps Platform Access Only

- Status: accepted
- Context: generic startup seeding could create demo tenants, known-password users, and incomplete role state during production deploys.
- Decision: production boot runs migrations, `ops:bootstrap-super-admin`, storage linking, and Laravel cache warmup; demo tenant/catalog seeding moves to an explicit local-only seed path.
- Consequence: production deploys become deterministic and safe by default, while local/demo environments must opt into `LocalDemoSeeder` when sample tenant data is needed.

### DEC-009: Tenant Suspension And API Token Revocation Must Invalidate Cached Access Immediately

- Status: accepted
- Context: tenant resolution and bearer-token auth were both cached, allowing suspended tenants and revoked tokens to keep working until TTL expiry.
- Decision: tenant and tenant-token cache keys are centralized and explicitly invalidated on tenant lifecycle changes, billing changes, onboarding updates, token issue, token rotation, and token revocation.
- Consequence: every access-affecting workflow that touches tenant state or bearer tokens must call the shared cache invalidation path.
