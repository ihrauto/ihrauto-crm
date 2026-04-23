# IHRAUTO CRM — Deep Engineering & Security Review

**Date:** 2026-04-23
**Scope:** Full codebase — 146 PHP files, 24 controllers, 24 models, 11 services, 64 migrations, 46 tests
**Stack:** Laravel 12 / PHP 8.2+ / PostgreSQL / Render.com / Spatie Permission / Sentry
**Reviewers:** Security, Code Quality, Business Logic, Database/Ops (4 parallel audits)

---

## EXECUTIVE SUMMARY

Overall: the codebase is **solid and above average** for a SaaS at this stage. Recent hardening work (invoice immutability triggers, rate limiting, backup sanitization, triple-gated auto-login) shows the team takes security seriously. But there are **5 critical gaps** that must be closed before scaling:

| # | Area | Risk |
|---|------|------|
| 1 | **Plan quota not enforced at controller layer** | Revenue loss — BASIC plan limits bypassable |
| 2 | **User model mass-assignment exposes `role`, `is_active`, `tenant_id`** | Privilege escalation if any future endpoint forgets validation |
| 3 | **InvoiceItem / QuoteItem / InvoiceSequence missing `BelongsToTenant`** | Cross-tenant read of financial line items |
| 4 | **No CI/CD pipeline** | Regressions ship to production undetected |
| 5 | **PostgreSQL `sslmode=prefer`** | TLS downgrade possible in production |

**Production-readiness score: 7.2 / 10** — can launch once the P0 items below are fixed.

---

## SEVERITY LEGEND

- **P0 / CRITICAL** — exploit or data loss possible; fix before next deploy
- **P1 / HIGH** — meaningful risk; fix this sprint
- **P2 / MEDIUM** — should fix, not urgent
- **P3 / LOW** — polish / hardening

---

## PART 1 — SECURITY

### P0 — Critical

**S-01. User model mass-assignment exposes sensitive fields**
- [app/Models/User.php:40-52](app/Models/User.php) — `$fillable` includes `tenant_id`, `role`, `is_active`, `email_verified_at`.
- Any new controller calling `User::create($request->all())` or `->update($request->all())` without explicit validation instantly grants privilege escalation (attacker sets `role=admin`) or tenant hijacking.
- **Fix:** Switch to `$guarded = ['tenant_id', 'role', 'is_active', 'email_verified_at']` and assign these fields explicitly in the controllers/services that should set them.

**S-02. `InvoiceItem`, `QuoteItem`, `InvoiceSequence` lack `BelongsToTenant`**
- [app/Models/InvoiceItem.php](app/Models/InvoiceItem.php), [app/Models/QuoteItem.php](app/Models/QuoteItem.php), [app/Models/InvoiceSequence.php](app/Models/InvoiceSequence.php)
- Direct model access (`InvoiceItem::find($id)`) bypasses tenant isolation. Parent invoice has it, children don't.
- **Fix:** Add the trait to all three. Add a regression test: `InvoiceItem::find($otherTenantItem->id)` must return `null`.

**S-03. Plan quota bypassable — checkin/work-order/customer create**
- [app/Http/Controllers/CheckinController.php:71-100](app/Http/Controllers/CheckinController.php), [app/Http/Controllers/CustomerController.php](app/Http/Controllers/CustomerController.php)
- `Tenant::canCreateWorkOrder()`, `canAddCustomer()`, `canAddVehicle()` exist in [app/Models/Tenant.php:329-457](app/Models/Tenant.php) but no controller calls them before create.
- BASIC plan 50 WO/month is a marketing promise, not an enforced limit. Direct revenue loss.
- **Fix:** Add a middleware `EnforcePlanQuota` or invoke the check inside each relevant service method. Return 402 Payment Required or 403 with upgrade link.

### P1 — High

**S-04. PostgreSQL `sslmode=prefer` in prod**
- [config/database.php:87](config/database.php)
- Allows silent downgrade to plaintext if TLS handshake fails. Render PostgreSQL is in Frankfurt — cleartext over the internet.
- **Fix:** `'sslmode' => env('DB_SSLMODE', 'require')`. Set `DB_SSLMODE=require` in [render.yaml](render.yaml).

**S-05. CORS fallback is wildcard**
- [config/cors.php:26](config/cors.php) — fallback to `env('APP_URL', '*')`. If `APP_URL` is unset, CORS opens to `*`.
- **Fix:** fail-closed in production: `'allowed_origins' => app()->environment('production') ? [env('APP_URL')] : ['*']`, and assert `APP_URL` is set in a bootstrap provider.

**S-06. `SESSION_SECURE_COOKIE` defaults to false; `APP_DEBUG=true` in `.env.example`**
- [.env.example:4,41](.env.example)
- If someone deploys with `.env.example` as a template, sessions ride HTTP and stack traces leak.
- **Fix:** force `config/session.php` to `app()->environment('production') ? true : env(...)`. Add a CI check that refuses `APP_DEBUG=true` when `APP_ENV!=local`.

**S-07. Auto-login marker file check is a timing side-channel (dev-only risk)**
- [app/Http/Middleware/TenantMiddleware.php:65-82](app/Http/Middleware/TenantMiddleware.php)
- `file_exists()` on every request. Low exploitability, but move to boot-time cache.
- **Fix:** resolve in `AppServiceProvider::boot()` into `config('app.auto_login_verified')` once.

**S-08. Rate limiting gaps on auth-adjacent endpoints**
- Profile update / password change endpoints in [app/Http/Controllers/ProfileController.php](app/Http/Controllers/ProfileController.php) and [routes/auth.php](routes/auth.php) are not throttled. Login / password-reset / mechanic invite are (good).
- **Fix:** `->middleware('throttle:5,15')` on password and email change routes.

**S-09. API controllers rely solely on `TenantScope` — no defense-in-depth `authorize()`**
- [app/Http/Controllers/Api/CheckinController.php:18-50](app/Http/Controllers/Api/CheckinController.php)
- If the global scope is ever disabled or a route is refactored, one tenant's token could read another's data.
- **Fix:** inside each API action, explicitly `$this->authorize('view', $customer)` as a safety net.

**S-10. TenantApiToken lookup fragility**
- [app/Models/TenantApiToken.php](app/Models/TenantApiToken.php) — intentionally does not use `BelongsToTenant` (token is queried pre-auth). This is correct but fragile.
- **Fix:** add a loud inline comment: `// CRITICAL: lookup happens pre-tenant-context. Always include ->whereNull('revoked_at') and timing-safe compare.` Add a unit test proving revoked tokens are rejected.

### P2 — Medium

**S-11. Superadmin bypass of `TenantMiddleware` is broad**
- [app/Http/Middleware/TenantMiddleware.php:30-35](app/Http/Middleware/TenantMiddleware.php) — superadmins skip tenant scoping entirely.
- **Fix:** require explicit `withoutGlobalScopes()` in superadmin controllers; add a gate; audit every access.

**S-12. `TenantContext` fallback to `auth()->user()->tenant_id` doesn't verify active**
- [app/Support/TenantContext.php:37-40](app/Support/TenantContext.php) — suspended tenants keep access.
- **Fix:** after setting tenant context in middleware, assert `is_active && !trial_expired`.

**S-13. Generic `catch (\Exception $e)` leaks internals**
- [app/Http/Controllers/CheckinController.php:99-106](app/Http/Controllers/CheckinController.php), [app/Http/Controllers/TireHotelController.php:112-116](app/Http/Controllers/TireHotelController.php) — `$e->getMessage()` echoed to user.
- **Fix:** typed exceptions (`InsufficientStockException`) mapped to safe user messages in a central handler.

**S-14. Backup archive still contains customer PII unredacted**
- [app/Http/Controllers/ManagementController.php](app/Http/Controllers/ManagementController.php) — recent commit sanitized users but not customers.
- **Fix:** add optional redaction flag for customer email/phone in exports.

### P3 — Low

- **S-15.** License plate regex doesn't strip non-ASCII — [app/Support/LicensePlate.php:38](app/Support/LicensePlate.php). Fix: `preg_replace('/[^A-Za-z0-9]/u', '', $plate)`.
- **S-16.** Hardcoded permission strings everywhere — create `app/Enums/Permission.php` with constants.
- **S-17.** `Dockerfile` uses `php:8.4-apache` minor tag; pin patch for reproducibility.

---

## PART 2 — BUSINESS LOGIC & CORRECTNESS

### P0 — Critical

**B-01. Plan quota not enforced** — see S-03 above; primary business risk.

**B-02. Work-order completion is not atomic with stock + invoice**
- [app/Services/WorkOrderService.php:125-159](app/Services/WorkOrderService.php)
- Sequence: `processStockDeductions()` → mark complete → `createFromWorkOrder()`. If step 1 fails after partial deduction, WO stays incomplete with stock decremented. If step 3 fails, stock is deducted but no invoice.
- **Fix:** wrap the whole completion in `DB::transaction()`; validate stock availability **before** mutating state (pre-flight pass-1 already exists in `InvoiceService::processStockDeductions`, so re-order to: validate → begin txn → deduct → create invoice → mark complete → commit).

### P1 — High

**B-03. No double-booking prevention for service bays or technicians**
- [app/Http/Controllers/AppointmentController.php:161-173](app/Http/Controllers/AppointmentController.php) — checks vehicle conflict only. Two appointments can share the same bay or mechanic.
- **Fix:** add conflict queries for `service_bay_id` and `technician_id` overlapping time ranges. Laravel has `overlap` patterns using `whereBetween` + OR-pair.

**B-04. Trial expiry not enforced at request time**
- [app/Models/Tenant.php:493-504](app/Models/Tenant.php) — helpers exist; no middleware calls them.
- **Fix:** extend [app/Http/Middleware/EnsureTenantTrialActive.php](app/Http/Middleware/EnsureTenantTrialActive.php) to actually check `isTrialExpired()` and redirect to billing.

**B-05. `InvoiceItem.quantity` cast mismatch**
- [app/Models/InvoiceItem.php:19](app/Models/InvoiceItem.php) — cast `decimal:2` but migration `2026_04_10_160100` made it integer. PostgreSQL rejects fractional inserts; tests on SQLite silently accept.
- **Fix:** change cast to `'integer'`.

**B-06. Payment observer swallows sync errors**
- [app/Models/Payment.php:56-88](app/Models/Payment.php) — catches `\Throwable`, logs, doesn't rethrow. Invoice `paid_amount` can silently go stale.
- **Fix:** rethrow inside the observer (transaction will roll back), or queue a reconciliation job.

### P2 — Medium

- **B-07.** VAT recalculated from `items.total` not `(qty × unit_price)` — [app/Services/InvoiceService.php:277-283](app/Services/InvoiceService.php). Tamper-resistant in normal flow, but trust-the-client if API allows custom items. Fix: always recompute from unit_price × qty server-side.
- **B-08.** `WorkOrder.parts_used` is untyped JSON array — [app/Models/WorkOrder.php:42-45](app/Models/WorkOrder.php). Missing fields silently skipped. Fix: value-object class + validation.
- **B-09.** Customer email uniqueness — global unique dropped, but no tenant-scoped replacement — [database/migrations/2026_01_09_220000_...](database/migrations/2026_01_09_220000_drop_global_unique_email_from_customers.php). Two customers in one tenant can share email. Fix: add `unique(['tenant_id','email'])`.
- **B-10.** Negative stock possible on SQLite (no CHECK constraint). Fix: add `check('stock_quantity >= 0')` at migration level.
- **B-11.** Invoice voiding doesn't auto-issue refund for already-paid amount — operational gap, document + add UI warning.
- **B-12.** Appointment reschedule defaults duration to 60 min when end_time is null — edge case.

### P3 — Low

- **B-13.** `Product.min_stock_quantity` field exists but no low-stock alert built.
- **B-14.** No overdue-invoice reminder email.
- **B-15.** Quote → Invoice conversion route missing (model exists).
- **B-16.** Tire `storage_location` is freeform string, no FK to warehouse/section.

---

## PART 3 — CODE QUALITY & ARCHITECTURE

### P1 — High

**C-01. Fat controllers with business logic**
- [app/Http/Controllers/WorkOrderController.php:219-269](app/Http/Controllers/WorkOrderController.php) `generate()` builds tasks/parts from checkin services inline. Move to `WorkOrderService::generateFromCheckin()`.
- [app/Http/Controllers/PaymentController.php:25-33](app/Http/Controllers/PaymentController.php) uses inline `$request->validate()` for complex idempotency rules; extract `StorePaymentRequest`.

**C-02. Missing `$this->authorize()` in `TireHotelController::store()`**
- [app/Http/Controllers/TireHotelController.php:58-70](app/Http/Controllers/TireHotelController.php) — routing sub-action `storeSeasonChange()` has no policy check.
- **Fix:** authorize at top of `store()`.

**C-03. Expensive accessors triggering N+1**
- [app/Models/Customer.php:122-135](app/Models/Customer.php) — `getActiveVehiclesAttribute()`, `getActiveCheckinsAttribute()`, `getStoredTiresAttribute()` all query DB on every access.
- **Fix:** make them scopes/relations; eager-load in controllers (`->with('activeVehicles')`).

**C-04. Translation infrastructure unused**
- [lang/en/crm.php](lang/en/crm.php) exists but views have zero `__()` usage. German/French prep never wired.
- **Fix:** Swiss market = DE/FR/IT — high business value. Sweep views and controllers for hardcoded strings.

**C-05. Tenant tax rate hardcoded**
- [config/crm.php:12](config/crm.php) — 8.1% fixed globally. Cross-canton differences and future rate changes require schema.
- **Fix:** `tenants.tax_rate` column, fall back to config default.

### P2 — Medium

- **C-06.** Technician availability checks duplicated across three controllers via trait — convert to custom validation rule `TechnicianAvailable`.
- **C-07.** Stock deduction logic uses raw locked queries in `InvoiceService` — split into dedicated `StockService` once it grows.
- **C-08.** `WorkOrderService::completeWorkOrder()` does four things — split into focused methods.
- **C-09.** Inconsistent API response shape — establish one trait with `apiOk()` / `apiError()`.
- **C-10.** Appointment colors hardcoded as hex in controller — move to enum/config.

### P3 — Low / Quick-wins

- Commit or discard deleted files: `cookies.txt`, `reproduce_issue.php`.
- Document `TenantApiToken` intentional scope bypass.
- Add `HEALTHCHECK` to [Dockerfile](Dockerfile).
- Add explicit middleware list to sensitive routes (don't rely on group inheritance).

---

## PART 4 — DATABASE & OPERATIONS

### P0 — Critical

**D-01. No CI/CD pipeline** — `.github/` empty.
- No tests on PR, no lint, no `composer audit`, no migration parse validation.
- **Fix:** add `.github/workflows/ci.yml` with jobs: `php artisan test`, `./vendor/bin/pint --test`, `composer audit`, `npm audit --audit-level=moderate`.

**D-02. Superadmin seeder defaults password to `'password'`**
- [database/seeders/SuperAdminSeeder.php:29](database/seeders/SuperAdminSeeder.php)
- If `SUPERADMIN_PASSWORD` env is missing in production, you ship with `password`.
- **Fix:** `throw` in seeder if env empty and `app()->environment('production')`.

### P1 — High

**D-03. Stock movements late primary-key migration** — [database/migrations/2026_01_19_160348_add_id_to_stock_movements_table.php](database/migrations/2026_01_19_160348_add_id_to_stock_movements_table.php). Audit all StockMovement queries.

**D-04. Missing FK indexes across many tables** — `payments.invoice_id`, `invoices.customer_id`, `work_orders.technician_id` etc. Add one migration that adds indexes where absent.

**D-05. Docker single-stage build** — [Dockerfile](Dockerfile) ships node_modules and dev dependencies. Image ≈1 GB.
- **Fix:** multi-stage: `FROM node:20 as frontend → npm ci && npm run build`, then `FROM php:8.4-apache` copying only `public/build` and production vendor dir.

**D-06. Failed jobs / queue retry not configured properly** — [config/queue.php:28-33](config/queue.php). Prod uses Redis but no exponential backoff, no failed-jobs alerting.

**D-07. Backup failure alerting absent** — [routes/console.php](routes/console.php) runs `backup:run` daily but nothing alerts on failure. Wire Sentry or Slack webhook.

**D-08. Sourcemaps enabled in prod** — [vite.config.js](vite.config.js) default. Leaks code structure. Set `build.sourcemap: false` for prod.

### P2 — Medium

- **D-09.** `render.yaml` uses `basic-1gb` Postgres — fine for launch, plan upgrade after first 10 tenants or slow queries.
- **D-10.** No `HEALTHCHECK` directive in Dockerfile (Render has the path, container doesn't).
- **D-11.** `ResetCRMData` command lacks `--dry-run` — other destructive commands have it.
- **D-12.** No OPcache tuning via `docker/php.ini` — performance left on table.
- **D-13.** Invoice immutability trigger is PostgreSQL-only — tests run against SQLite; asymmetric.
- **D-14.** Cache stampede protection not explicit — wrap expensive dashboard queries in `Cache::lock()`.

### P3 — Low

- **D-15.** No metrics endpoint (Prometheus). Low priority for current scale.
- **D-16.** Restore procedure untested — run a restore drill quarterly, document the date.
- **D-17.** RPO/RTO not documented in `docs/runbook.md`.

---

## PART 5 — TEST COVERAGE GAPS

Recent commits show good test additions (invoice, inventory, work order, customer). Main gaps:

1. **Plan quota enforcement** — no tests for `canCreateWorkOrder`, `canAddCustomer` limits.
2. **Appointment double-booking** — no bay/technician conflict tests.
3. **API check-in endpoints** — zero coverage on `Api/CheckinController`, `Api/CustomerController`.
4. **Customer email uniqueness (post-migration)** — not asserted.
5. **Trial expiry middleware** — once implemented, needs tests.
6. **Work-order completion atomicity** — simulate stock failure mid-transaction.
7. **Superadmin cross-tenant boundaries** — policy tests for Admin/SuperAdminController actions.
8. **Malformed `parts_used` JSON** — silent skip vs. reject.
9. **Negative stock race (concurrent deductions)** — `ConcurrencyTest` covers invoices; extend to stock.
10. **Policy unit tests** — many policies exist; direct unit tests are sparse.

---

## PART 6 — STRENGTHS (what the team got right)

- **Multi-tenant isolation is well-designed** — `BelongsToTenant` + `TenantScope` + `tenant_id()` helper is clear, testable, widely applied.
- **Invoice immutability** — model boot + PostgreSQL trigger = defense-in-depth.
- **Thread-safe invoice numbering** via `lockForUpdate()` with proper retry.
- **Payment idempotency** via deterministic `idempotency_key` — excellent.
- **Payments are fully immutable** (delete/forceDelete throw).
- **Concurrency tests** exist and cover invoice numbering, stock, payments, void reversal.
- **Audit logging** on all major models via `Auditable` trait.
- **Recent security posture is strong** — triple-gated auto-login, rate-limited invite/reset, CSRF enforced, no `{!! !!}` misuse spotted, SQL injection mitigated (parameterised binds).
- **Documentation culture** — `docs/` has runbooks, decision log, engineering board. Rare at this stage.
- **Sentry integration** configured correctly (no PII, no SQL bindings).
- **Destructive commands guarded** with `--force` + confirmation.

---

## PART 7 — PRIORITIZED ACTION PLAN

### Week 1 — P0 must-fix before next production deploy

1. **S-01** — Switch `User` model to `$guarded`. (~30 min)
2. **S-02** — Add `BelongsToTenant` to `InvoiceItem`, `QuoteItem`, `InvoiceSequence` + regression tests. (~2 hr)
3. **S-03 / B-01** — Enforce plan quotas in `CheckinController`, `CustomerController`, vehicle/product create paths. Middleware `EnforcePlanQuota`. (~4 hr)
4. **D-01** — Add GitHub Actions workflow: tests + Pint + composer audit. (~3 hr)
5. **D-02** — Superadmin seeder: fail hard if password env missing in prod. (~15 min)
6. **S-04** — Change `sslmode` default to `require`. Set `DB_SSLMODE=require` in `render.yaml`. (~10 min)

### Week 2 — P1 high-priority hardening

7. **B-02** — Refactor `WorkOrderService::completeWorkOrder` into one atomic transaction with pre-flight validation.
8. **B-03** — Add bay + technician conflict checks in appointment validation.
9. **B-04** — Wire trial expiry into `EnsureTenantTrialActive`.
10. **B-05** — Fix `InvoiceItem.quantity` cast to integer.
11. **B-06** — Payment observer: rethrow on sync failure.
12. **S-05 / S-06** — CORS + session + debug hardening.
13. **D-03 / D-04** — FK indexes migration + stock_movements audit.
14. **D-05** — Multi-stage Dockerfile.
15. **D-07** — Backup failure alerts.

### Week 3–4 — P2 refactoring & coverage

16. Translation rollout (`__()` sweep).
17. Extract fat-controller logic into services.
18. Per-tenant tax rate schema.
19. Missing tests (plan quota, double-booking, API scoping, email uniqueness).
20. N+1 cleanup — Customer accessors → scopes.
21. Exception handling sanitation.

### Ongoing — P3 polish

22. Permission constants enum, license plate regex, commit deleted files, HEALTHCHECK, OPcache tuning, RPO/RTO docs, restore drill cadence.

---

## PART 8 — RISK MATRIX SNAPSHOT

| Category                 | Critical | High | Medium | Low  |
|--------------------------|----------|------|--------|------|
| Security                 | 3        | 7    | 4      | 3    |
| Business Logic           | 2        | 4    | 6      | 4    |
| Code Quality             | 0        | 5    | 5      | 5+   |
| Database / Ops           | 2        | 6    | 6      | 3    |
| **Total**                | **7**    | **22** | **21** | **15+** |

**Estimated total remediation effort:** ~6–8 engineering weeks (one senior full-time) to reach a 9/10 production-readiness score.

---

*End of report.*
