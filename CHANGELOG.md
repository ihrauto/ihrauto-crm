# Changelog

All notable changes to IHRAUTO CRM are documented here.

## [Unreleased]

### Security
- Removed public setup, restore, cleanup, and debug HTTP backdoors.
- Locked API access to bearer-authenticated tenant tokens.
- Stopped trusting tenant identity from public request headers.
- Moved route access control to module and permission middleware boundaries.

### Multi-tenancy
- Unified tenant context resolution across middleware, scopes, model creation, and validation.
- Added hashed tenant API token storage and migration of legacy plaintext tenant keys.
- Tightened tenant-scoped validation for customer, vehicle, invoice, payment, appointment, and work-order flows.

### Billing
- Normalized invoice status handling to `draft`, `issued`, `partial`, `paid`, and `void`.
- Replaced invoice numbering based on record counts with a transaction-safe per-tenant sequence.
- Corrected finance search and dashboard queries to use canonical invoice state and `license_plate`.

### Lifecycle
- Unified tenant provisioning for registration and social-auth company creation.
- Replaced HTTP tenant deletion with transactional archive behavior in the admin UI.
- Added audited CLI commands for super-admin bootstrap, API token rotation, and irreversible tenant purge.

### Platform
- Corrected tenant trial/subscription day calculations.
- Reduced unnecessary tenant activity writes.
- Standardized Resend configuration on `RESEND_API_KEY`.
- Replaced stock framework docs with product-specific setup and security guidance.
- Rebuilt the public pricing page into a plan-aware product landing page with package-specific CTAs that flow directly into registration.
- Aligned the public pricing UX to the existing indigo/navy application palette used across guest and dashboard surfaces.

### Documentation
- Added an engineer-facing documentation set under `docs/` for architecture, workflows, code mapping, and operational process.
- Added a decision log and engineering board so future changes leave behind durable project context.
- Defined repository standards for documentation updates, changelog maintenance, and task tracking.
