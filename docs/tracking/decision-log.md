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

## 2026-04-24

### DEC-010: Password policy is centralised in `Password::defaults()`

- Status: accepted
- Context: registration, password reset, password update, admin user creation, and invite setup each carried their own password rule, ranging from `Password::defaults()` (8 chars + HIBP) to bare `min:8`. Tightening one did not tighten the others.
- Decision: `AppServiceProvider::boot` sets a single default via `Password::defaults(fn () => Password::min(12)->mixedCase()->numbers())`. Production additionally chains `->uncompromised()` (HIBP k-anonymity). All call sites use `Password::defaults()`; no site should carry a local rule.
- Consequence: future password-policy changes happen in one place. Tests that formerly posted `password123` or `new-password` had to be updated to policy-compliant values; factories keep `bcrypt('password')` because the factory path does not validate.

### DEC-011: Content-Security-Policy ships with `'unsafe-inline'` on script-src while Blade uses inline Alpine handlers

- Status: accepted
- Context: Blade templates rely on Alpine.js via inline attributes (`x-data`, `x-on:*`, `@click`, etc.). Under CSP, those inline attribute expressions are evaluated as inline script. A nonce-based CSP would require either a migration to bundled Alpine-component modules or per-request nonce injection into every inline expression — neither is a small change.
- Decision: ship a CSP today that keeps `'unsafe-inline'` on script-src and style-src but enforces every other win (`object-src 'none'`, `form-action 'self'`, `frame-ancestors 'self'`, `base-uri 'self'`, `upgrade-insecure-requests` in production, plus `Cross-Origin-Opener-Policy: same-origin` and `Cross-Origin-Resource-Policy: same-origin`). CSP is attached only to HTML responses.
- Consequence: we accept residual inline-XSS risk; Blade templating remains the primary XSS defence. Follow-up work (ENG-008) will migrate inline handlers to listener registration in bundled JS, then drop `'unsafe-inline'` and move script-src to `'strict-dynamic'` with per-request nonces.

### DEC-012: Invoice immutability is enforced at both the model layer AND the Postgres layer, and the two sets must match

- Status: accepted
- Context: the Invoice model's `IMMUTABLE_FIELDS` constant and the Postgres `prevent_issued_invoice_modification` trigger drifted — the trigger locked fewer columns than the model. Raw SQL or bulk-update bypasses of Eloquent could silently rewrite `discount_total`, `customer_id`, or `vehicle_id` on an issued invoice.
- Decision: every field in `IMMUTABLE_FIELDS` must also appear in the Postgres trigger's `IS DISTINCT FROM` check. Adding a column to one layer requires adding it to the other in the same PR.
- Consequence: the model guard protects SQLite test runs and most app-level writes; the trigger is the last line of defence against raw / bulk-update bypasses. Removing or narrowing either layer requires an explicit ADR update.

### DEC-013: `TRUSTED_PROXIES` replaces the hardcoded `'*'` proxy-trust list

- Status: accepted
- Context: `TrustProxies::$proxies = '*'` trusts any upstream's `X-Forwarded-*`, which is correct when the container sits strictly behind Render / Cloudflare / a dedicated LB but catastrophic if the container is ever exposed directly. Spoofed `X-Forwarded-For` defeats rate-limit IP keying; spoofed `X-Forwarded-Proto` defeats HTTPS enforcement.
- Decision: `TrustProxies` reads `TRUSTED_PROXIES` from the environment as a comma-separated list of IPs / CIDRs. Missing or `*` keeps the legacy trust-all behaviour so existing Render deployments keep working; `.env.example` now documents the variable.
- Consequence: production deployments SHOULD set `TRUSTED_PROXIES` to the edge's egress ranges. Self-hosted deployments without a trusted proxy MUST set it so the app doesn't honour spoofed headers from clients.
