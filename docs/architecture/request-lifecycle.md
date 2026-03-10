# Request Lifecycle

This document explains how a request moves through the system and where core responsibilities live.

## Web Request Lifecycle

### 1. Bootstrap

- `bootstrap/app.php` registers the web routes from `routes/web.php`.
- The web middleware stack includes sessions, auth state, CSRF, and `TenantMiddleware`.

### 2. Tenant Resolution

`TenantMiddleware` is the entrypoint for tenant context on web routes.

Resolution order:

1. Existing app-bound tenant context
2. Tenant route parameter
3. Subdomain
4. Custom domain
5. Session tenant
6. Authenticated user tenant

Special cases:

- `/` bypasses tenant resolution because it is public.
- Auth routes bypass tenant resolution where needed.
- Local developer routes under `/dev/*` bypass tenant enforcement.
- Super-admin users bypass tenant enforcement.

### 3. Tenant State Gates

Once a tenant is resolved:

1. Inactive tenants are blocked.
2. Expired tenants are blocked.
3. The active tenant is placed into `TenantContext`.
4. Session state is updated when a session exists.

### 4. Route Boundary

Routes in `routes/web.php` are grouped by:

- `auth`
- `verified`
- `trial`
- `tenant-activity`
- `module:<permission>`
- `permission:<permission>`
- `role:super-admin`

This means routing is the first authorization boundary, not the controller body.

### 5. Model Binding and Query Scope

Tenant-owned models use `BelongsToTenant`, which:

1. Applies `TenantScope`
2. Filters queries by the current tenant ID
3. Sets `tenant_id` automatically on create

As a result, implicit route model binding for tenant-owned models should resolve only records in the active tenant context.

### 6. Validation and Controller Orchestration

Controllers either:

- Use `FormRequest` classes such as `StoreCustomerRequest`, `StoreCheckinRequest`, and `UpdateWorkOrderRequest`
- Or perform inline validation using `TenantValidation` helpers

Controllers should orchestrate the request, not hold the full business workflow.

### 7. Service Layer

Domain services own multi-step workflows:

- `CheckinService`
- `InvoiceService`
- `TenantProvisioningService`
- `TenantLifecycleService`
- `DashboardService`
- `ReportingService`
- `TireStorageService`

### 8. Observers and Side Effects

Observers keep model side effects centralized:

- `CustomerObserver`
- `PaymentObserver`

Other side effects include:

- Audit log writes
- Event tracking
- Notification delivery
- stock movement changes

### 9. Response

Responses are returned as:

- Blade views for tenant web pages
- Redirects for mutations
- JSON for AJAX endpoints under `/ajax/*`

## API Request Lifecycle

### 1. Bootstrap

- `bootstrap/app.php` registers the API routes from `routes/api.php`.
- The API stack prepends `AuthenticateTenantApiToken`.

### 2. API Authentication

`AuthenticateTenantApiToken`:

1. Reads the bearer token from `Authorization`
2. Finds the active token by hashed value
3. Loads the related tenant
4. Places both into `TenantContext`
5. Writes token metadata onto the request for rate limiting and tracing

If authentication fails, the request stops with `401`.

### 3. Rate Limiting

`AppServiceProvider` defines the `tenant-api` rate limiter.

The limiter keys by:

1. token ID
2. token prefix
3. request IP as a final fallback

The per-minute limit comes from the tenant record when available.

### 4. Route Binding and Query Scope

Once tenant context exists, tenant-owned models resolve through the same `TenantScope` used by the web application. That keeps list queries and route model binding tenant-specific.

### 5. Legacy Compatibility

Legacy API routes remain available temporarily.

- They require the same bearer token.
- `AddLegacyApiDeprecationHeaders` adds deprecation metadata to responses.

## Write Flow

Mutating operations should follow this pattern:

1. Resolve tenant and authenticate caller
2. Authorize route access
3. Validate input with tenant-aware rules
4. Reload related records through tenant-scoped queries or binding
5. Execute business logic in a service or transaction
6. Let observers and model hooks handle local side effects
7. Return a view redirect or JSON response

## Failure Modes

Typical failure surfaces:

- Missing tenant context
- Inactive or expired tenant
- Missing or revoked tenant API token
- Cross-tenant IDs rejected by validation or route binding
- Permission or policy denial
- Domain exceptions such as invoice immutability

When debugging, engineers should identify the failing layer first instead of starting inside the controller.
