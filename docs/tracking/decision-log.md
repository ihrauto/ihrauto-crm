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
