# Changelog

All notable changes to IHRAUTO CRM are documented here.

## [1.3.0] - 2026-04-02 — Code Quality & Security Sprint

### Security Fixes
- Fixed XSS vulnerability: replaced `addslashes()` with `Js::from()` + data attributes in work order board
- Removed `console.log()` from production code (board and tire hotel views)
- Added authorization to WorkOrderController, AppointmentController, PaymentController
- Reduced API token cache TTL from 5 minutes to 60 seconds

### Performance
- Cached DashboardService stats with 5-minute tenant-scoped TTL
- Consolidated work order status queries (4 queries → 1 aggregated)
- Consolidated checkin status queries (4 queries → 1 aggregated)
- Cached FinanceService overview stats with 5-minute TTL
- Fixed N+1 queries in FinanceController

### Code Quality
- Created WorkOrderService (status transitions, technician assignment, completion workflow)
- Extracted CheckinController business logic to CheckinService (work order creation, photo upload)
- Moved TireHotelController helpers to TireStorageService
- Extracted FinanceController overview to FinanceService
- Wired WorkOrderStatus enum to model, controller, and policy
- Added Scheduled and WaitingParts cases to WorkOrderStatus enum
- Made stock deductions idempotent (checks existing StockMovements)
- Added invoice item count validation (rejects zero-item invoices)

### Testing
- Added 54 new tests (188 → 242), all passing
- Added policy tests (22 tests covering 6 policies + 3 gates)
- Added middleware tests (10 tests)
- Added negative test cases (11 tests for cross-tenant, immutability, overpayment)
- Added concurrency tests (6 tests for duplicate invoice, stock, payment)
- Added CheckinService unit tests (5 tests)

### Accessibility
- Added skip-to-content link for keyboard navigation
- Added ARIA labels to all icon-only buttons in layout
- Added aria-haspopup/aria-expanded to dropdown menus
- Added role="dialog" and aria-modal to modal component
- Added aria-label to sidebar navigation

### Frontend
- Added design system tokens to Tailwind config (brand colors, status colors, shadows)

### Database
- Fixed 4 pending migrations for SQLite test compatibility
- Migrations ready for production PostgreSQL deployment

### Verification
- `php artisan test`: 242 tests, 650 assertions, all passing

---

## [1.2.0] - 2026-03-22 — Tenancy Hardening & Authorization

### Authorization & Access Control
- Added Gate::authorize to FinanceController, ServiceBayController
- Created ServicePolicy with full CRUD authorization
- Added authorization to ProductController, ServiceController, TireHotelController
- Added defense-in-depth abort_unless to API controllers

### Database Schema
- Created migration to fix tenant_id type mismatch (string → bigint) on products/services/stock_movements
- Created migration to add tenant_id to audit_logs with backfill
- Created migration to add 8 performance indexes
- Created migration to add service_bay FK to checkins

### Validation
- Created 8 FormRequest classes (Store/Update for Service, Product, Payment, Appointment)
- Improved CSV import validation with header checking and row-level error collection
- Standardized tenant_id() helper usage across codebase

### Enums
- Created WorkOrderStatus, CheckinStatus, PaymentMethod, InvoiceStatus backed enums

### Other
- Added rate limiters for login (5/min) and registration (3/min)
- Added customer deduplication by phone number in CheckinService
- Added getimagesize() content validation to photo uploads
- Added tenant_id to AuditLog model and Auditable trait

---

## [1.1.0] - 2026-03-12 — Production Launch Hardening
- Froze tenant-facing role assignment to the tenant-safe set `admin`, `manager`, `technician`, and `receptionist`, removed tenant role-permission editing from the launch surface, and centralized tenant user-management authorization so managers can no longer promote admins or touch platform users.
- Hardened mechanic account lifecycle:
  - mechanic routes now require `manage users`
  - new mechanic accounts are created inactive with invite tokens instead of a shared temporary password
  - invite acceptance activates the account and standard login now rejects inactive users
- Added shared tenant and tenant API token cache invalidation so suspension, activation, billing changes, archive, token issue, rotate, and revoke take effect immediately instead of waiting for cache TTL expiry.
- Fixed production route caching by removing duplicate tenant AJAX route names and replacing the remaining closure route with a controller action.
- Reworked payment idempotency to use a dedicated persisted `payments.idempotency_key`, with duplicate prevention when clients send either an explicit idempotency key or only a transaction reference.
- Made check-in creation atomic from the user’s perspective by rolling back the database transaction and uploaded files when before-photo storage fails.
- Aligned work-order update validation with supported controller states by allowing `scheduled` and `waiting_parts`.
- Added duplicate-email validation to tenant onboarding/company setup so existing tenant emails fail validation instead of throwing a database exception.
- Made management module toggles enforceable at runtime by wiring tenant feature flags into module access middleware, including disabling Tire Hotel even on eligible plans when the feature is turned off.
- Split seeding into a production-safe default path and an explicit local demo path:
  - `DatabaseSeeder` now seeds only roles and the super-admin bootstrap path
  - `LocalDemoSeeder` owns tenant/demo catalog setup
  - Docker boot now runs `migrate --force`, `ops:bootstrap-super-admin`, `storage:link`, and Laravel cache warmup without generic `db:seed`
- Updated the production image to install Redis support and PostgreSQL client tooling required by the declared Render runtime.
- Expanded regression coverage for launch blockers:
  - inactive invite/login handling
  - tenant-user role matrix and last-admin protection
  - immediate tenant/token revocation
  - payment idempotency
  - check-in upload rollback
  - module-disable enforcement
  - work-order status transitions
- Verification: `php artisan test` (`178` tests, `544` assertions), `php artisan route:cache`, `npm run build`, `npx eslint resources/js/**/*.js`, and `./vendor/bin/pint --test` all pass.

### Production Beta Readiness (2026-03-11)
- Replaced the placeholder pricing route with an authenticated manual-billing page at `billing.pricing` and routed expired-tenant recovery to that real production page.
- Added super-admin manual billing controls so a tenant can be converted from trial to paid and given a specific renewal date without database or CLI edits.
- Removed published placeholder management surfaces from the production route map (`notifications`, `analytics`, placeholder `pricing` entrypoint) and surfaced the real billing page from management.
- Clarified platform-global `users.email` uniqueness in registration, login, and tenant user-management UX and validation messages.
- Blocked customer deletion when linked operational or financial records still exist, with a dependency summary returned to the UI.
- Tightened work-order staff listings to active users only.
- Corrected check-in creation so busy-technician rejection happens before any check-in is persisted and the auto-created work order stores its summary in a real persisted column.
- Added a cloud-ready runtime path for uploads and backups:
  - `public` disk remains env-driven and can target S3-compatible object storage.
  - backup archives now target the configurable `backups` disk by default.
  - dashboard system-health backup/storage checks now understand cloud-backed disks.
- Updated container/runtime ops for production:
  - added `schedule:work` under supervisord
  - moved queue-worker and scheduler logs to stdout/stderr
  - linked storage during container boot
  - documented paid Render, managed Postgres, managed Redis, and object-storage env requirements in `render.yaml` and `.env.example`
- Added feature coverage for previously under-tested production surfaces:
  - expanded `CheckinTest`
  - added `TireHotelTest`
  - added `ManagementAdminTest`
- Total test count: 163 tests, 490 assertions, all passing.

### Production Hardening (2026-03-11)
- Verified `.env` has never been committed to Git history; secrets are safe.
- Removed stray debug files (`reproduce_issue.php`, `seed_bom.php`, `cookies.txt`, `database/database.sqlite`).
- Added stray file patterns to `.gitignore`.
- Fixed `.env.example`: corrected DB to PostgreSQL, added Google OAuth placeholders, set `APP_NAME`, added `SENTRY_TRACES_SAMPLE_RATE` and `AUTO_LOGIN_ENABLED`.
- Updated `composer.json` project identity from `laravel/laravel` to `ihrauto/crm`.
- Replaced hardcoded `DashboardService::getSystemStatus()` with real runtime checks (DB ping, disk usage, cache read/write, backup file age).
- Added `supervisord` to Dockerfile for Apache + queue worker.
- Added production caching (`config:cache`, `route:cache`, `view:cache`) to Docker boot sequence.
- Added `auto_login_enabled` config key in `config/app.php`; auto-login now requires both `APP_ENV=local` AND `AUTO_LOGIN_ENABLED=true`.
- Added production guidance comment for `SESSION_ENCRYPT=true` in `.env.example`.
- Annotated `SubscriptionController` with TODO/FIXME markers for payment gateway integration.
- Fixed onboarding fallback redirect from dev-only route to production-safe `home` route.
- Added `PaymentFlowTest` (8 tests: full/partial payment, overpayment rejection, draft/void prevention, idempotency, void service).
- Added `AppointmentTest` (8 tests: CRUD, status update, model accessors, tenant isolation).
- Created `flash-message.blade.php` and `stat-card.blade.php` reusable Blade components.
- Total test count: 136 tests, 356 assertions, all passing.

### Production Hardening Phase 2 (2026-03-11)
- Decomposed `checkin.blade.php` and `tires-hotel.blade.php` — extracted `section-separator`, `photo-upload`, `technician-select` components. Replaced ~140 lines of duplicated markup.
- Removed unused `spatie/laravel-multitenancy` package from Composer dependencies.
- Optimized `config/backup.php`: narrowed file include to `storage/app`, enabled gzip compression, added `log` channel for failure/unhealthy alerts.
- Published and configured `config/cors.php` with env-driven allowed origins (`CORS_ALLOWED_ORIGINS`), restricted methods/headers, 24h preflight cache, credentials support.
- Updated `render.yaml`: added all missing env vars (session, CORS, backup, OAuth, Sentry), documented free tier limitations, recommended "starter" plan for production.
- Added `CORS_ALLOWED_ORIGINS` to `.env.example`.

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
