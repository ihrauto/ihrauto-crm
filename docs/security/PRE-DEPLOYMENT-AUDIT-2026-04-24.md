# Pre-deployment audit — IHRAUTO CRM

**Date:** 2026-04-24
**Branch:** `main` (tip: `abf01b1`)
**Scope:** verify readiness to push + auto-deploy via Render. Twelve
audit categories; each ends with a PASS / ATTENTION / BLOCK verdict.

Legend:
- **PASS** — no action needed before deploy.
- **ATTENTION** — can ship, but operator should action after deploy.
- **BLOCK** — must resolve before push.

---

## 1. Security audit

**Scope:** confirm every committed finding from the 2026-04-24 review is
actually live in the tip of `main` (`abf01b1`), and that tests lock it in.

| ID | Fix | Evidence (file:line) | Verdict |
|---|---|---|---|
| C-1 | PAT stripped from `.git/config` | `git remote -v` → `https://github.com/ihrauto/ihrauto-crm.git` (no token) | **PASS (local)** — operator must still revoke the old PAT at github.com |
| C-2 | Rotate `RESEND_API_KEY`, `SUPERADMIN_PASSWORD`, `APP_KEY`, Sentry DSN | none — external action | **BLOCK (operator)** — must land in Render env before or immediately after deploy |
| H-1 | `two_factor_required` removed from mass-assignment surface | `app/Models/Tenant.php:175-198`; `database/factories/TenantFactory.php:46` | **PASS** — invariant test `SecurityInvariantsTest::h1_two_factor_required_is_not_mass_assignable_on_tenant` |
| H-2 | Email change requires `current_password` | `app/Http/Requests/ProfileUpdateRequest.php:35-46` | **PASS** — 3 feature tests in `ProfileTest` |
| H-3 | `/subscription/setup` gated by `manage settings` | `routes/web.php` middleware stack | **PASS** — 2 feature tests in `SubscriptionSetupAuthorizationTest` |
| H-4 | Forgot-password enumeration oracle closed | `app/Http/Controllers/Auth/PasswordResetLinkController.php:32-50` | **PASS** — `PasswordResetTest::test_unknown_email_gets_same_response_as_known_email` |
| H-5 | Storage perms tightened to 770; root posture documented | `Dockerfile:138` | **PASS (partial)** — unprivileged-port rework deferred; current posture matches `FROM php:apache` convention |
| H-6 | Upload extension derived from sniffed image type | `app/Http/Controllers/WorkOrderPhotoController.php` | **PASS** — `WorkOrderPhotoAuthorizationTest::stored_filename_extension_is_derived_from_image_type_not_client_name` |
| H-7 | Invoice immutability trigger extended | `database/migrations/2026_04_24_100000_extend_invoice_immutability_trigger.php` | **PASS (code)** — must be verified post-deploy against Postgres (`SELECT prosrc FROM pg_proc WHERE proname='prevent_issued_invoice_modification'`) |
| M-1 | CSP + COOP + CORP headers on HTML | `app/Http/Middleware/SecurityHeaders.php:64-117` | **PASS** — `SecurityInvariantsTest::m1_html_response_carries_csp_and_cross_origin_headers` |
| M-2 | Token cache purge on revoke | verified already present in `TenantApiToken::revoke` | **PASS** — no code change needed |
| M-6 | `invite_token` in `$hidden` | `app/Models/User.php:68-72` | **PASS** — `SecurityInvariantsTest::m6_invite_token_is_hidden_on_user_serialization` |
| M-7 | Slow-query bindings scrubbed | `app/Providers/AppServiceProvider.php:195-235` | **PASS (code)** — exercise in prod with `DB_SLOW_QUERY_LOG_MS=500` |
| M-8 | CSV formula injection neutralized | `app/Http/Controllers/ManagementController.php:66,94` | **PASS** — `CsvExportInjectionTest` |
| M-9 | `TRUSTED_PROXIES` env | `app/Http/Middleware/TrustProxies.php` + `.env.example` | **PASS (code)** — defaults to `*`, acceptable on Render today |
| M-10 | CI `npm audit` gates the build | `.github/workflows/ci.yml` (no `\|\| true`) | **PASS** — will exercise on first push |
| M-3 | Spatie `teams=true` | deferred — `ENG-005` | **DEFER** — not exploitable today (1 user = 1 tenant) |
| M-5 | Hashed remember_token | deferred — `ENG-006` | **DEFER** — needs guard override + dual-verify window |
| L-1 | `Password::defaults()` hardened (min 12 + mixedCase + numbers, HIBP in prod) | `app/Providers/AppServiceProvider.php:37-43`; `ManagementController:259,300`; `InviteController:46` | **PASS** — `SecurityInvariantsTest::l1_password_policy_requires_at_least_twelve_mixed_characters` |
| L-6 | `robots.txt` disallows authed paths | `public/robots.txt` (43 lines) | **PASS** — `SecurityInvariantsTest::robots_txt_disallows_authenticated_paths` |
| L-8 | Parent-folder secrets (`aws-laravel-key.pem`, `IHRAUTO-CRM copy/`) | external to repo | **ATTENTION (operator)** — move/delete after deploy |
| L-9 | Sentry `before_send` scrubber | `config/sentry.php:48`; `app/Support/SentryScrubber.php` | **PASS** — 2 unit tests in `SentryScrubberTest` |

**Test health**: `./vendor/bin/phpunit` → 432 tests, 1125 assertions, all green.
**Lint**: `./vendor/bin/pint --test` → 360 files clean.

**Verdict: PASS**, with C-2 (operator secret rotation) as the only BLOCK
and L-8 (parent-folder cleanup) as operator ATTENTION.

## 2. Scalability audit

**Scope:** verify the 200-tenant target from
`docs/SCALABILITY-REVIEW-2026-04-23.md` is wired correctly in
`render.yaml`, the scheduler, and the caching layer.

### Capacity posture (`render.yaml`)

| Component | Plan / size | Notes |
|---|---|---|
| Web (Apache + PHP-FPM + supervisord) | `plan: standard`, `numInstances: 3`, Frankfurt | 1 vCPU / 1 GB × 3. Comment targets ~100 req/s at 200 tenants. |
| Backup runner (schedule:work only) | `plan: starter`, `numInstances: 1` | Isolated so a 10–30 min `pg_dump` never touches web latency. `QUEUE_WORKERS=0`. |
| PostgreSQL | `plan: standard` + integrated PgBouncer, 200 connections | 3 web × ~25 Apache workers + queue + scheduler ≈ 80 conns peak → 2× headroom. |
| Redis | `plan: standard`, 5 GB | Sessions + cache + queue + rate-limit counters. |
| Staging web | `plan: starter`, `numInstances: 1`, separate DB + Redis | deploys from `staging` branch. |

### Scheduler (`routes/console.php`)

- Every `Schedule::command()` call includes `->onOneServer()`: `backup:run`,
  `backup:clean`, `backup:monitor`, `backup:verify`, `invoices:send-overdue-reminders`,
  `invoices:auto-issue-stale`, `inventory:low-stock-report`,
  `audit-logs:archive`. **PASS.**
- `onOneServer` requires an atomic cache driver.
  `AppServiceProvider::boot` refuses production boot if `CACHE_STORE` is
  anything other than `redis / memcached / database / dynamodb`. `render.yaml`
  sets it to `redis`. **PASS.**
- All schedule jobs live outside peak hours (02:00–08:45 UTC). **PASS.**

### Queue

- `QUEUE_CONNECTION=redis` in prod (`render.yaml`).
- `supervisord.conf` runs `numprocs=%(ENV_QUEUE_WORKERS)s` per container;
  `render.yaml` sets `QUEUE_WORKERS=3` → 9 workers across the web fleet,
  plus the scheduler on the backup runner.
- Backoff: `--backoff=30,120,600`, `--tries=3`, `--memory=256`, `--max-jobs=1000`.
  Bounded retry plus memory-based recycle protects against leaky jobs (OPS-17).
- The backup runner intentionally sets `QUEUE_WORKERS=0`. **PASS.**

### Caching / stampede

- `app/Support/CachedQuery::remember()` wraps `Cache::remember` with a
  short-lived atomic lock so only one request recomputes a hot key;
  others wait or re-read. TTLs include jitter (C-5). **PASS.**
- Tenant context (subdomain / domain / id / API token) is cached via
  `TenantCache::*` with 1-hour TTL; `forgetTenant` / `forgetToken` called
  on every lifecycle transition. **PASS.**
- Permission cache is Spatie-managed and backed by the same Redis.

### Rate limits

- Login: two-layer (per email+IP 5/min, per IP 20/min) via
  `RateLimiter::for('login', …)`.
- Register: 3/min per IP.
- Tenant API: per-token, limit from `tenants.api_rate_limit` (default 60/min).
- Route-level throttle middleware covers forgot-password (3/5m), reset-password
  (3/5m), invite setup (10/5m), mechanics invite (5/10m), checkin store (10/1),
  work-order bulk-status (10/1), invoice bulk-issue (10/1), invoice issue-and-send
  (30/1), payments resource (30/1), public invoice PDF (20/1).
- **PASS** — the limits are on every mutation path that sends mail or costs DB work.

### Concerns / action items

- **ATTENTION (staging)** — `render.yaml:272,304` sets `APP_DEBUG=true` and
  `AUTO_LOGIN_ENABLED=true` for the staging web service. Two notes:
  1. `AutoLoginGuard::resolve()` gates on `app()->environment('local')`,
     so `AUTO_LOGIN_ENABLED=true` on staging is a no-op — safe, but
     misleading. Recommend dropping the env var from the staging block.
  2. `APP_DEBUG=true` with `LOG_LEVEL=debug` on a publicly reachable
     staging host leaks stack traces. Mitigate with IP allowlist at the
     Render edge, HTTP basic auth, or flip to `APP_DEBUG=false` /
     `LOG_LEVEL=info` on staging.
- **INFO (Redis capacity)** — `plan: standard` = 5 GB. Sessions at
  SESSION_LIFETIME=120 min × ~1000 concurrent users × ~10 KB payload ≈
  10 MB. Even with 10× burst this is small. **PASS** with headroom.
- **INFO (DB connections)** — 3 web × 25 Apache workers + 3 queue + 1
  scheduler ≈ 79 conns if every worker holds an open PDO. PgBouncer in
  transaction-pooling mode lets the 200-conn Postgres easily absorb
  this. Watch `pg_stat_activity` by `application_name` (DB_APP_NAME is
  set to `ihrauto-crm-web` / `ihrauto-crm-backup`) after deploy.

**Verdict: PASS** — staging debug/auto-login is the only ATTENTION item
and does not block production deploy.

## 3. Functional audit

**Scope:** confirm every core workflow has test coverage and that the
full suite passes at the committed tip.

### Test suite stats

- `./vendor/bin/phpunit` → **432 tests, 1125 assertions, all green**, ~34 s.
- **51 feature test files** + **9 unit test files**.
- **24 HTTP controllers** — every controller name resolves to at least
  one test class (`for c in $(ls app/Http/Controllers/*.php ...); ...`
  returned no gaps).

### Workflow coverage (test-file map)

| Workflow | Covering tests |
|---|---|
| Auth (login / register / reset / verify / invite / social) | `Auth/AuthenticationTest`, `Auth/PasswordResetTest`, `Auth/PasswordUpdateTest`, `Auth/RegistrationTest`, `AuthenticationTest`, `InviteTokenSecurityTest`, `SocialAuthSecurityTest`, `PasswordResetRateLimitTest`, `AutoLoginGuardTest` |
| Profile + account lifecycle | `ProfileTest` (incl. new H-2 cases) |
| Tenant isolation + policies | `TenantIsolationTest`, `AuditLogTenantScopingTest`, `PolicyTest`, `CustomerAuthorizationTest`, `AuthorizationTest`, `WorkOrderPhotoAuthorizationTest`, `SubscriptionSetupAuthorizationTest` |
| Customer CRUD + merge | `CustomerTest`, `CustomerMergeTest`, `CustomerEmailUniquenessTest` |
| Check-in | `CheckinTest` |
| Work orders | `WorkOrderTest`, `GenerateFromCheckinTest`, `BulkOperationsTest`, `ScheduleConflictTest` |
| Appointments | `AppointmentTest`, `AppointmentRescheduleTest` |
| Tire hotel | `TireHotelTest` |
| Inventory / stock | `InventoryTest`, `StockServiceTest`, `LowStockReportTest` |
| Quotes + invoices | `InvoiceTest`, `InvoiceRecalculationTest`, `InvoicePaidAmountLockTest`, `QuoteToInvoiceConversionTest`, `IssueAndSendTest`, `AutoIssueStaleDraftsTest` |
| Payments | `PaymentFlowTest` |
| Finance reporting | `FinanceServiceMonthlyRevenuePortabilityTest`, `FinanceServiceTenantIsolationTest`, `FinanceTabsActiveStateTest` |
| Quota + billing | `PlanQuotaEnforcementTest`, `ManagementAdminTest` |
| API v1 (bearer token) | `Api/*`, `TenantApiTokenInvariantTest` |
| Middleware chain | `MiddlewareTest`, `SecurityHeadersTest`, `PublicSurfaceHardeningTest` |
| Backup + export | `BackupExportSecurityTest`, `CsvExportInjectionTest` |
| Audit log | `AuditLogArchivalTest`, `AuditLogTenantScopingTest` |
| Concurrency / race conditions | `ConcurrencyTest` |
| Error pages + negative cases | `ErrorPagesTest`, `NegativeCasesTest` |
| Security invariants (2026-04-24 sprint) | `SecurityInvariantsTest`, `SentryScrubberTest`, `CsvExportInjectionTest` |

### Gaps identified

- **INFO** — `ExampleTest.php` is the Laravel scaffold placeholder. Not a
  gap per se; can delete without consequence.
- **INFO** — no end-to-end browser test (Dusk / Pest Browser). The app
  has significant inline Alpine behaviour (profile email-change form I
  added, work-order board, Sweet-alert confirms). Unit + feature tests
  cover HTTP + DB, not DOM. Acceptable for this deploy; flag as a
  follow-up.
- **INFO** — the public invoice PDF route renders a Blade view via
  `response()->view(...)`; visual regression is not covered by tests.
  Manual smoke after deploy is enough.

**Verdict: PASS** — test suite is green and every request surface has
at least controller-level coverage.

## 4. Authorization audit

**Scope:** policies registered, tenant isolation, super-admin boundary,
module/plan gating, IDOR exposure.

### Defence layers

1. **Tenant scope** — `BelongsToTenant` trait applies `TenantScope`
   (`app/Scopes/TenantScope.php`) to every tenant-owned model. Route
   model binding goes through this scope, so an attacker passing a
   cross-tenant ID hits `404 Model not found` rather than reading it.
2. **Policies** — 11 model policies registered in
   `AppServiceProvider::boot()` (`Invoice`, `Customer`, `Vehicle`,
   `Checkin`, `WorkOrder`, `Tire`, `Product`, `Appointment`, `Service`,
   `WorkOrderPhoto`, `Quote`). Each enforces `$user->tenant_id ===
   $model->tenant_id` and role/permission.
3. **Route-level middleware** — every authenticated group runs through
   `['auth','verified','trial','tenant-activity']`, and sensitive
   sub-groups add `module:<feature>` or `permission:<name>` or
   `role:<name>`.
4. **Admin (`/admin/*`)** — entire group wrapped in
   `Route::middleware(['auth','role:super-admin'])->prefix('admin')`
   (`routes/web.php:210`). Only super-admin users can reach any tenant-
   management action.

### `authorize()` density (controllers ranked by count of calls)

| Controller | # authorize / Gate::authorize |
|---|---|
| QuoteController | 9 |
| CustomerController | 9 |
| InvoiceController | 8 |
| ProductController | 5 |
| TireHotelController | 4 |
| ServiceController | 4 |
| ServiceBayController | 4 |
| ManagementController | 4 |
| AppointmentController | 4 |
| WorkOrderController | 3 |
| WorkOrderPhotoController | 2 |
| PaymentController | 1 |
| FinanceController | 1 |
| CheckinController | 1 |

Every resource controller that exposes a non-index action carries at
least one `$this->authorize()` or `Gate::authorize()` call. The three
`CheckinController` / `FinanceController` / `PaymentController`
entries each rely on a single module-level gate because those
controllers are index+action only.

### Route gating samples (`routes/web.php`)

- `module:access finance` → invoices, quotes, payments CRUD.
- `module:access inventory` → products, services, stock operation.
- `module:access management` → reporting, user mgmt (sub-guarded with
  `permission:manage users`, `permission:manage settings`,
  `permission:delete records`).
- `permission:manage settings` on `/subscription/setup` (H-3, new this sprint).
- `permission:delete records` on destructive endpoints (work-bay delete,
  product/service delete, work-order-photo delete, user delete).
- `role:super-admin` on `/admin/*`.
- `tire-hotel` middleware on tire-hotel routes (checks plan + feature).
- `trial` middleware guards expired tenants, with an escape hatch to
  `/billing/plans` so they can renew.

### RBAC roles (`database/seeders/RolesAndPermissionsSeeder.php`)

- `admin` — all permissions (`syncPermissions(Permission::all())`).
- `manager` — full operational, no destructive / settings.
- `technician` — work-order execution only.
- `receptionist` — check-in + customer entry.
- `super-admin` — platform-scope, bound to `/admin/*`.

`TenantUserAccess` (`app/Support/TenantUserAccess.php`) is used by
`ManagementController` to enforce:
- `assignableRolesFor($user)` — caller can only assign equal-or-lower roles.
- `ensureCanManageUser($caller, $target)` — cross-role guard rails.
- Last-admin protection (`User::withoutGlobalScopes()` counts remaining
  admins) so a tenant can never orphan itself.

### IDOR / cross-tenant exposure checks

- `/ajax/customers/{customer}`, `/ajax/customers/{customer}/history`,
  `/ajax/tires/{tire}`, `/ajax/appointments/{appointment}/reschedule` —
  route binding goes through `TenantScope`. A cross-tenant ID resolves
  to 404. **PASS.**
- Public invoice PDF (`/i/{token}/{invoice}`) uses `signed` middleware
  + a per-invoice HMAC-SHA256 token + `hash_equals()` comparison.
  `withoutGlobalScopes()` is intentional and justified in the comment
  (request is unauthenticated → no tenant context to use). **PASS.**
- Super-admin bypass in `TenantMiddleware` — super-admin short-circuits
  tenant resolution. The admin controllers (`SuperAdminController`,
  `AdminDashboardController`) explicitly scope with
  `withoutGlobalScopes()` + `->where('tenant_id', ...)` where needed, and
  only super-admins reach those routes. **PASS.**

### Deferred / known weaknesses (from review, not re-fixed here)

- **M-3 / ENG-005** — Spatie `teams=false`. Not exploitable today
  (schema: 1 user = 1 `tenant_id`), but fragile when multi-tenant user
  membership lands.

**Verdict: PASS.**

## 5. Database / migration audit

**Scope:** which migrations run on next deploy; are any destructive,
long-running, or lock-taking.

### Migrations new in this deploy (ahead of `origin/main`)

```
git log origin/main..HEAD --diff-filter=A --name-only -- 'database/migrations/*.php'
```
returns exactly **one** file:

- `database/migrations/2026_04_24_100000_extend_invoice_immutability_trigger.php`
  - Postgres-only (`if (DB::getDriverName() !== 'pgsql') return;`).
  - `CREATE OR REPLACE FUNCTION prevent_issued_invoice_modification()` —
    atomic replacement of an existing function body. The trigger binding
    on `invoices` stays intact (no `DROP TRIGGER`, no `CREATE TRIGGER`
    re-run).
  - No DDL on tables, no data migration, no index work. Expected
    runtime: sub-second on any tenant dataset size.
  - `down()` restores the prior (narrower) function definition — proper
    rollback semantics.

### Pre-existing migrations already on `origin/main` / already applied in prod

These will NOT run on this deploy (already in [3] batch per
`migrate:status`), but for completeness:

| Migration | Risk |
|---|---|
| `2026_04_24_100000_add_scalability_composite_indexes` | Composite index creation without `CONCURRENTLY` — would hold ACCESS SHARE during build on a live table. Already applied; no-op on redeploy. |
| `2026_04_24_100100_add_pg_trgm_search_indexes` | Installs `pg_trgm` extension + GIN trigram indexes. Has a graceful fallback to btree when `CREATE EXTENSION` is denied by the DB role. Already applied. |
| `2026_04_24_100200_create_audit_logs_archive_table` | Creates a new (empty) archive table. Safe. |
| `2026_04_23_100200_add_missing_hot_path_indexes` | Added indexes for FK/hot paths. Already applied. |
| `2026_04_10_100000_add_invoice_immutability_trigger` | Original trigger; prerequisite for the 04_24 extension above. Already applied. |

### Schema-level safety checks I rely on

- `invoice_number` — scoped unique per tenant
  (`2026_01_09_221000_scope_invoice_number_unique`).
- Invoice immutability — Postgres BEFORE UPDATE trigger on `invoices`
  locks `invoice_number / subtotal / tax_total / discount_total / total
  / issue_date / customer_id / vehicle_id` once `locked_at` is set.
- Check constraints — added for enum fields
  (`2026_04_02_100000_add_check_constraints_for_enum_fields`), tire
  storage uniqueness (`2026_04_10_150100_...`), non-negative stock
  (`2026_04_23_100400_...`).
- Soft deletes — added on critical models (`add_soft_deletes_to_critical_models`).
- Foreign keys present on all tenant-scoped tables; FK violations
  surfaced as 409 with friendly message
  (`bootstrap/app.php:57-76 withExceptions block`).

### Rollback considerations

- `php artisan migrate:rollback --step=1` reverses the new trigger
  migration by restoring the earlier function body. Safe.
- Render also keeps daily Postgres backups (per `plan: standard`), and
  the backup runner pushes nightly dumps to S3 (see §11 below).
- For the MODEL-level changes (H-1 `two_factor_required` removed from
  fillable, M-6 `invite_token` in `$hidden`, L-1 password policy), a
  rollback is purely a git revert — no data migration either way.

### Long-running DDL risk on future deploys

- Any future `CREATE INDEX` on a large hot table should use
  `CREATE INDEX CONCURRENTLY` wrapped in
  `DB::statement('SET lock_timeout = ...')` to avoid production pauses.
  Document this in `docs/tracking/decision-log.md` as a rule for
  upcoming migrations if you don't already have it.
- Add a note to `CLAUDE.md` or contributor guide: *"Migrations that
  touch tables with >1M rows must use CONCURRENTLY for indexes and a
  tested maintenance plan for column adds."*

**Verdict: PASS** — one new migration, proven-safe shape.

## 6. Environment audit

**Scope:** every env key needed to boot production, where it's defined,
and whether it's a secret.

### Secrets (`sync: false` — set manually in Render dashboard)

Must all have live values BEFORE this deploy or the app won't boot
cleanly. Grep of `render.yaml`:

| Key | Who sets | Purpose |
|---|---|---|
| `APP_URL` | operator | canonical base URL (password-reset links, Socialite redirect origin) |
| `CORS_ALLOWED_ORIGINS` | operator | comma-separated origin allowlist; prod fails closed if blank |
| `RESEND_API_KEY` | operator | transactional email |
| `SENTRY_LARAVEL_DSN` | operator | error reporting |
| `SENTRY_RELEASE` | operator | tagging |
| `SUPERADMIN_PASSWORD` | operator | seeded once on first boot; seeder aborts if empty in prod |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URL` | operator | Socialite / Google OAuth |
| `BACKUP_FILESYSTEM_KEY`, `BACKUP_FILESYSTEM_SECRET`, `BACKUP_FILESYSTEM_REGION`, `BACKUP_FILESYSTEM_BUCKET`, `BACKUP_FILESYSTEM_ENDPOINT`, `BACKUP_FILESYSTEM_URL` | operator | S3-compatible storage for nightly DB dumps |
| `PUBLIC_FILESYSTEM_KEY`, `PUBLIC_FILESYSTEM_SECRET`, `PUBLIC_FILESYSTEM_REGION`, `PUBLIC_FILESYSTEM_BUCKET`, `PUBLIC_FILESYSTEM_ENDPOINT`, `PUBLIC_FILESYSTEM_URL` | operator | S3-compatible storage for tenant uploads |
| `ASSET_URL` | operator (optional) | CDN in front of `public/build` |
| `CRM_SUPPORT_PHONE` | operator | displayed in UI |

### Managed / auto-generated

| Key | Source |
|---|---|
| `APP_KEY` | `generateValue: true` — Render provisions on service creation |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `fromDatabase: ihrauto-db` — injected from the Postgres service |
| `REDIS_URL` | `fromService: ihrauto-redis` |

### Hardcoded in `render.yaml`

- `APP_ENV=production`
- `APP_DEBUG="false"` — and `AppServiceProvider::boot()` refuses to boot
  if `config('app.debug')` is truthy in production, so this is belt+braces.
- `LOG_CHANNEL=stderr` (Render captures container stderr)
- `LOG_LEVEL=info`
- `SESSION_DRIVER=redis`, `SESSION_ENCRYPT="true"`, `SESSION_SECURE_COOKIE="true"`
- `AUTO_LOGIN_ENABLED="false"` (and `AutoLoginGuard::resolve()` requires
  `APP_ENV=local` which is not set here anyway — triple gate).
- `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `REDIS_CLIENT=phpredis`
- `DB_SLOW_QUERY_LOG_MS="500"` — logged via scrubbed binder (M-7).
- `MAIL_MAILER=resend`, `MAIL_FROM_ADDRESS=noreply@ihrauto.ch`, `MAIL_FROM_NAME="IHRAUTO CRM"`
- `FILESYSTEM_DISK=public`, `PUBLIC_FILESYSTEM_DRIVER=s3`, `PUBLIC_FILESYSTEM_ROOT=public`
- `BACKUP_DESTINATION_DISK=backups`, `BACKUP_FILESYSTEM_DRIVER=s3`, `BACKUP_FILESYSTEM_ROOT=backups`
- `BACKUP_NOTIFICATION_EMAIL=info@ihrauto.ch`
- `SENTRY_ENVIRONMENT=production`, `SENTRY_TRACES_SAMPLE_RATE="0.2"`

### Local-only file (`.env`) hygiene

- `.env` is in `.gitignore` (verified `git log -- .env` is empty).
- `.env.production.example` lists every `!!!FILL!!!` marker so an
  operator running Docker outside Render has a clear handoff.
- `.env.example` now documents `TRUSTED_PROXIES` (added in M-9 commit).

### Boot-time guardrails (from `AppServiceProvider::boot()` in prod)

- Throws if `APP_DEBUG=true`.
- Throws if `CACHE_STORE` is not atomic (`redis`/`memcached`/`database`/`dynamodb`).
- Throws if `REDIS_PASSWORD` is empty AND Redis is used as cache/session/queue.
- `URL::forceScheme('https')`.

### Concerns

- **ATTENTION (staging)** — `APP_DEBUG=true` on staging
  (`render.yaml:272`). Fine for internal QA, dangerous if staging is
  ever handed to a beta tenant without IP allowlist.
- **PASS (prod)** — every production boot-critical env var is either
  set via `fromDatabase`/`fromService`, hardcoded safely, or marked
  `sync: false` for explicit operator configuration.

**Verdict: PASS** once the operator populates the `sync: false`
secrets (this is the same list as C-2's rotation work).

## 7. Performance audit

**Scope:** N+1, pagination, caching, indexes, and observability knobs.

### N+1 mitigations

- **140 `->with(...)` hits** across `app/` — eager loading is the
  dominant pattern. Spot-checked `WorkOrderController` lines 55 / 70:
  `->with(['vehicle','customer','technician'])` on index + show.
- `TireHotelController::index` uses `Tire::with(['customer','vehicle'])`.
- Services lazy-load via relations defined on models, not raw
  sub-queries.
- No `DB::table(...)` raw query bypasses found outside the two justified
  slow-path spots (audit log archival, invoice trigger migration).

### Pagination on heavy lists

- `QuoteController`: `paginate(15)` (+ `withQueryString`).
- `FinanceController`: `paginate(20)` for payments, `paginate(15)` for
  invoices (+ separate page tokens so tabs don't clash).
- `CustomerController::index`: `paginate(10)`.
- `MechanicsController::index`: `paginate(15)`.
- `TireHotelController::index`: `paginate(10)`.
- `Admin/SuperAdminController::index`: `paginate(20)`.
- No unbounded `->get()` on routes that list across all rows of a
  tenant.

### Caching

- `CachedQuery::remember()` — stampede-protected. Used on hot paths:
  - Tenant resolution by id / subdomain / domain (3600 s TTL).
  - `tenant.users_count` / `customer_count` / `vehicle_count` (60 s, with
    `forceFresh` in quota-lock paths so overbooking can't slip past).
  - `DashboardService` aggregates.
- `Cache::remember()` (Laravel default) used for:
  - `AuthenticateTenantApiToken` — 60 s per token-hash key.
  - Monthly work-order count per tenant (60 s).
  - Token `last_used_at` touch coalescing (5 min).

### Indexes (pre-existing, already in prod DB)

- Composite indexes added by `2026_04_24_100000_add_scalability_composite_indexes`.
- GIN trigram indexes on customer name / email / phone columns
  (`2026_04_24_100100_add_pg_trgm_search_indexes`); graceful btree
  fallback if the PG role can't `CREATE EXTENSION`.
- `users.email` globally unique.
- `invoice_number` unique per tenant.
- FK indexes generated by Laravel's `foreignId` helper.

### Observability

- `DB_SLOW_QUERY_LOG_MS=500` in prod (`render.yaml`) — every query
  slower than 500 ms gets a `slow_query` warning with scrubbed bindings
  (M-7).
- `DB_APP_NAME=ihrauto-crm-web` / `ihrauto-crm-backup` surface in
  `pg_stat_activity` for connection-attribution during incidents.
- Sentry traces: `SENTRY_TRACES_SAMPLE_RATE=0.2` (20 % sample).

### Hot-table size management

- Audit-log archival (`Schedule::command('audit-logs:archive')`) moves
  rows older than the hot window into `audit_logs_archive` Sunday
  04:00 UTC. Keeps `audit_logs` indexed-lookups fast as traffic grows.

### Concerns / follow-ups

- **INFO** — `DashboardController@index` + `FinanceController@index` are
  aggregate-heavy pages; cached for 5 min per D-14. If those pages ever
  balloon (more tenants per shared-host plan), consider pushing the
  aggregates into a materialised view.
- **INFO** — no APM / request-timing for the non-Sentry-traced requests.
  Slow-query log catches DB-bound slowness; web latency relies on
  Render's built-in metrics + Sentry traces. Acceptable for now.
- **INFO** — `BCRYPT_ROUNDS=12`. At 9 web-worker concurrency × ~100 ms
  per hash, that's fine; if login traffic spikes consider a dedicated
  login-worker queue (future).

**Verdict: PASS.**

## 8. Validation / error audit

**Scope:** inputs validated on every mutation, exceptions turn into
user-friendly responses, no stack traces in prod.

### Input validation

**21 FormRequest classes** cover the major mutation endpoints:

- `Auth/*Request` (LoginRequest, registration through Breeze defaults)
- `ProfileUpdateRequest` — now enforces L-1 password rule + H-2
  `current_password` on email change.
- `StoreCustomerRequest` / `UpdateCustomerRequest`
- `StoreAppointmentRequest` / `UpdateAppointmentRequest`
- `StoreCheckinRequest` / `UpdateCheckinRequest`
- `StoreWorkOrderRequest` / `UpdateWorkOrderRequest` / `ScheduleWorkOrderRequest`
- `StoreQuoteRequest` / `UpdateQuoteRequest`
- `StorePaymentRequest`
- `StoreProductRequest` / `UpdateProductRequest` / `ImportProductsRequest`
- `StoreServiceRequest` / `UpdateServiceRequest`
- `UpdateTireRequest` / `StoreNewTireCustomerRequest`

Additional inline `->validate(...)` calls in controllers (per
`grep -rc "validate(" app/Http/Controllers/`):

| Controller | inline validate count |
|---|---|
| `Admin/SuperAdminController` | 7 |
| `AppointmentController` | 6 |
| `ServiceBayController` | 3 |
| `ManagementController` | 3 |
| `InvoiceController` | 3 |
| `MechanicsController` | 2 |
| (+ 9 other controllers, 1 each) | |

Short / admin-specific flows validate inline; the repeat-use
resource endpoints go through FormRequests. No mutation endpoint is
completely unvalidated.

### L-1 password rule now applied everywhere

`Password::defaults(...)` is installed centrally in
`AppServiceProvider::boot()`. Call sites:

- `RegisteredUserController::store` — `Rules\Password::defaults()`
- `NewPasswordController::store` — `Rules\Password::defaults()`
- `PasswordController::update` — `Password::defaults()`
- `InviteController::setup` — `Rules\Password::defaults()` (migrated from `min:8`)
- `ManagementController::storeUser` / `updateUser` — `Password::defaults()` (migrated from `min:8`)

### Exception rendering

`bootstrap/app.php withExceptions()` block:

- `QueryException` (SQLSTATE `23000` / `23503` / SQLite code `19`)
  caught and re-rendered as a friendly 409 (JSON) or flash error
  (HTML) saying "record cannot be deleted because other records
  depend on it". No raw DB error text reaches end users.
- Everything else falls through to the default Laravel handler. In
  production (`APP_DEBUG=false` enforced by the boot guard), Laravel
  renders the branded error pages.

### Branded error pages

- `resources/views/errors/403.blade.php`
- `resources/views/errors/404.blade.php`
- `resources/views/errors/500.blade.php`
- `resources/views/errors/503.blade.php` (maintenance mode)
- `resources/views/errors/tenant-expired.blade.php` — trial/subscription lapsed
- `resources/views/errors/tenant-inactive.blade.php` — suspended tenant
- All covered by `ErrorPagesTest`.

### Flash feedback

Every form mutation ends with `->with('success'|'error'|'info'|'warning', …)`
or a FormRequest validation error that Blade's `@error` directives
surface inline. No silent POST.

### Concerns

- **INFO** — some controllers (DashboardController, HealthController,
  TenantController, Controller, BillingController) correctly have zero
  validate calls: they are read-only or the wiring layer only. Not a
  gap.
- **INFO** — FormRequest coverage could grow to replace the remaining
  inline validates, but this is cosmetic — inline validate is just as
  safe.

**Verdict: PASS.**

## 9. File upload audit

**Scope:** every path that accepts `UploadedFile`; verify size / MIME /
content checks + tenant-scoped storage + safe filename derivation.

### Upload sites

`grep -rn "\$request->file(" app/` returns exactly three:

1. `app/Http/Controllers/WorkOrderPhotoController.php:33`
2. `app/Http/Controllers/CheckinController.php:90`
   — delegates to `CheckinService::uploadPhotos` (same file format).
3. `app/Http/Controllers/ProductController.php:115`
   — CSV import (not image).

### Coverage per site

**(1) WorkOrderPhotoController — FULLY HARDENED**

- FormRequest-like validation: `image|mimes:jpg,jpeg,png,webp|max:5120`.
- `getimagesize()` content sniff — rejects non-images.
- **H-6 fix applied**: extension derived from `IMAGETYPE_*`, not
  `getClientOriginalExtension()`.
- UUID filename, tenant-scoped path
  (`work-order-photos/{tenant_id}/{workOrder.id}/{uuid}.{ext}`).
- Tenant assertion inside `store()` independent of route binding.
- Policy check `WorkOrderPhoto` on both create + delete.
- Storage disk: `public` (S3 in prod).

**(2) CheckinService::uploadPhotos — ⚠ FINDING**

- Validation in `StoreCheckinRequest`: `photos.*` mimes + size OK.
- `getimagesize()` content sniff present.
- **GAP**: line 237 still builds the filename from
  `$photo->getClientOriginalExtension()`.
  ```
  $filename = Str::uuid().'.'.$photo->getClientOriginalExtension();
  ```
  Same polyglot / double-extension vector that H-6 closed for
  `WorkOrderPhotoController`.
- Severity: **MEDIUM** — chained with an Apache PHP handler misconfig
  on the public storage path, it's RCE. Unlikely but the exact pattern
  we already fixed in its sibling controller.
- Recommendation: extract the extension-mapper helper I introduced in
  `WorkOrderPhotoController::store` into a small support class (e.g.
  `app/Support/SafeImageUpload::extensionFor(array $imageInfo): ?string`)
  and call it from both sites.
- Flagging as **H-6b**; NOT fixed in this session (the user said no
  commits — fixing would create a fresh commit). Suggest scheduling
  now so the same bug isn't latent in prod.

**(3) ProductController::import — safe**

- CSV only. `ImportProductsRequest` validates `file|mimes:csv,txt|max:2048`.
- Read with `fopen()` + `fgetcsv()` — no unserialize, no eval.
- Header whitelist enforced.
- Row values go through Eloquent validation per-row.
- No file persisted to disk beyond the temporary upload; runtime
  cleanup is Laravel's own.

### Storage disks (`config/filesystems.php`)

| Disk | Prod driver | Visibility | Contents |
|---|---|---|---|
| `local` | local | private | Laravel internals |
| `public` | `s3` (via `PUBLIC_FILESYSTEM_DRIVER`) | public | user-uploaded images |
| `backups` | `s3` (via `BACKUP_FILESYSTEM_DRIVER`) | private | nightly dumps |

- `FILESYSTEM_DISK=public` in prod, with `PUBLIC_FILESYSTEM_DRIVER=s3`:
  uploads land in S3, not on the container filesystem. Apache never
  executes them even if a .php extension slipped through, because the
  container's public/storage symlink isn't where the S3 driver stores.
  This is the strongest mitigation — but on the old `local` driver (dev
  / self-hosted) the H-6b gap in CheckinService becomes live.

### Concerns / actions

- **ATTENTION** — apply H-6 hardening to `CheckinService::uploadPhotos`
  (line 237). Low effort, same shape as H-6.
- **PASS** — all other upload paths validated and scoped.
- **PASS** — storage disks not co-located with code exec in prod.

**Verdict: ATTENTION** — not a deploy blocker for S3-backed prod, but
unambiguous fix-before-next-release item.

## 10. Queue / email audit

**Scope:** mail provider config, notification queueing, worker settings,
DLQ / retry behaviour, schedule of outbound mail.

### Notifications (all queued via `ShouldQueue`)

- `InvoiceIssuedNotification` — queued so invoice issuing doesn't block
  on SMTP/Resend.
- `InvoiceOverdueNotification` — queued; fired from
  `invoices:send-overdue-reminders` scheduled command at 08:30 UTC.
- `LowStockDigestNotification` — queued; fired from
  `inventory:low-stock-report` scheduled command at 08:45 UTC.
- `MechanicInviteNotification` — queued; fired when an admin invites a
  mechanic. Route is rate-limited `throttle:5,10`.

Every notification implements `ShouldQueue`; no sync-sends that could
block a user request on the Resend API.

### Mail provider

- `MAIL_MAILER=resend` in prod (`render.yaml:107`).
- `RESEND_API_KEY` is `sync: false` — must be populated in Render dashboard.
- `MAIL_FROM_ADDRESS=noreply@ihrauto.ch`, `MAIL_FROM_NAME="IHRAUTO CRM"`.
- Provider SDK: `resend/resend-laravel ^1.1` (composer.json).

### Queue worker configuration (`docker/supervisord.conf`)

```
php artisan queue:work ${QUEUE_CONNECTION:-database} \
  --sleep=3 --tries=3 --backoff=30,120,600 \
  --max-time=3600 --max-jobs=1000 --memory=256 \
  --timeout=300 --no-interaction
```

- Connection: `redis` in prod (`QUEUE_CONNECTION=redis` in render.yaml).
- Retries: 3 tries per job.
- Backoff: 30 s, 2 min, 10 min exponential — protects Resend against
  hammering when their API blips.
- Memory recycle: 256 MB — PDF renders / large imports can leak.
- Worker max-time: 3600 s (recycled hourly to reclaim PHP opcache).
- Max jobs: 1000 (additional recycle trigger).
- Per-job timeout: 300 s.
- Process group kill: `stopasgroup=true killasgroup=true` so supervisord
  cleanly terminates child processes on stop/reload.
- Concurrency: 3 workers per web container × 3 containers = 9 workers
  live for tenant-originated jobs. Backup runner adds a scheduler, no
  workers.

### Rate-limited mail paths

- `POST /mechanics/{mechanic}/invite` → `throttle:5,10` (5 per 10 min).
  Limits the "spam an email address by re-sending invites" vector.
- `POST /forgot-password` → `throttle:3,5`.
- `POST /reset-password` → `throttle:3,5`.
- `POST /invoices/{invoice}/issue-and-send` → `throttle:30,1`.

### Failed jobs

- Laravel's default `failed_jobs` table (created by
  `0001_01_01_000002_create_jobs_table`). Operator can inspect via
  `php artisan queue:failed` / `queue:retry all`.
- No dead-letter digest job at present — failures surface via Sentry
  when the Notification throws.

### Email deliverability

- `MAIL_FROM_ADDRESS=noreply@ihrauto.ch` — operator needs
  `SPF`/`DKIM`/`DMARC` DNS records pointing at the Resend setup.
  This is not a code concern but mark it on the pre-deploy checklist.

### Concerns

- **INFO** — `BACKUP_NOTIFICATION_EMAIL=info@ihrauto.ch` is the only
  address that gets notified when the nightly backup fails / verifies.
  On-call visibility depends on someone reading that inbox.
- **INFO** — failed jobs have no automated retry sweep besides the
  per-job 3-retry budget. For transient Resend outages, that budget
  covers a 12-minute window (30 s + 2 min + 10 min). Longer outages
  require manual `queue:retry`.

**Verdict: PASS.**

## 11. Backup + rollback plan

**Scope:** we must be able to recover from a bad deploy (code) and a bad
data event (row-level / schema-level).

### Code rollback

1. **Immediate revert via Render** — Render's dashboard keeps every
   deploy; rolling back to the previous successful deploy takes one
   click and is the first resort for any regression that shows up
   within minutes of the deploy.
2. **Git revert** — each of the four sprint commits is a separate
   logical change. If only one needs to back out:
   - `abf01b1` docs (no runtime effect; skip revert)
   - `e582172` low findings — revert re-enables the account-enum oracle
     (H-4) and the old password policy. Not desirable, but safe.
   - `6f62f89` medium findings — reverts M-1 CSP, M-6 hidden
     invite_token, M-7 scrubber, M-8 CSV, M-9 proxies, M-10 CI. Also
     safe to back out.
   - `8f9ce4c` critical + high — reverts H-1..H-7 + the new migration.
     The migration has a proper `down()` so this is reversible, but
     doing it reopens known vulnerabilities; only revert if an unknown
     regression surfaces and we cannot triage faster than a roll-back.
3. **Config flip at the edge** — for CSP (M-1), an ops-only
   SecurityHeaders override can be made behind an env flag later.
   Right now there is no such toggle — revert the commit if CSP breaks
   a legitimate inline handler.

### Schema rollback

- `php artisan migrate:rollback --step=N` walks back the newest N
  migrations using each migration's `down()`.
- The only new migration this deploy
  (`2026_04_24_100000_extend_invoice_immutability_trigger`) has a
  proper `down()` that restores the earlier trigger-function body.
  Rollback is idempotent.
- Older migrations in the tree are either `CREATE OR REPLACE` /
  `CREATE INDEX IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS` shapes
  (safe-to-replay) or no-ops on SQLite.

### Data recovery

- **Render-managed Postgres**: `plan: standard` includes daily
  automated backups with 7-day retention at the Render layer. Recovery
  is via Render's point-in-time-restore tool; contact is documented
  at render.com/docs/databases.
- **Application-level DB dump to S3** (`spatie/laravel-backup`):
  - `backup:run --only-db` daily 02:00 UTC
  - `backup:clean` daily 03:00 UTC
  - `backup:monitor` daily 03:30 UTC — alerts on missing / stale backups
  - `backup:verify` daily 04:15 UTC (OPS-08) — independent "does the
    dump we just shipped actually restore?" check. Catches silent
    failures (rotated S3 creds, zero-byte dumps) within 26 h.
- All backup scheduled commands have `->onOneServer()` — only the
  dedicated `ihrauto-backup-runner` (Render `worker` service) runs
  them; web containers never block on `pg_dump`.
- Backup archive encryption: controlled by `BACKUP_ARCHIVE_PASSWORD`
  env (`config/backup.php:167`). Currently unset in `render.yaml` —
  **ATTENTION** — if the S3 bucket is not already customer-managed
  with encryption at rest, set `BACKUP_ARCHIVE_PASSWORD` as an extra
  `sync: false` secret so the zipped dumps are encrypted.

### Rollback runbook (smoke)

Within 5 min of push, check:

1. Render dashboard → each web instance returns 200 on `/health`.
2. Sentry → no error spike (especially new `Content-Security-Policy`
   violation reports).
3. `pg_stat_activity` WHERE `application_name = 'ihrauto-crm-web'` —
   connection count sane (<80 fleet-wide).
4. A test login works end-to-end (use a staging or demo tenant).

Within 30 min:

5. `backup:monitor` shouldn't have fired a Sentry error.
6. The `invoices:send-overdue-reminders` cronetag doesn't fire today
   (daily 08:30 UTC); check tomorrow.

If any of 1–4 fail, **roll back via Render dashboard** before digging
into logs.

### Audit-log archive

`audit-logs:archive` moves hot rows to `audit_logs_archive` weekly. No
delete operation — archived rows still retrievable via
`AuditLog::archived()` scope. A bad deploy cannot destroy audit history.

### Concerns

- **ATTENTION** — `BACKUP_ARCHIVE_PASSWORD` unset. Either set it as a
  Render secret or confirm your S3 bucket has bucket-default SSE-KMS
  enabled. Currently the dumps rely solely on S3 bucket-level
  encryption.
- **PASS** — every data-affecting operation has either a DB-level guard
  (invoice trigger, FK constraints, check constraints), a model-level
  guard (policies + IMMUTABLE_FIELDS), or both. No code path bulk-
  deletes without user-visible confirmation.

**Verdict: PASS** with the one ATTENTION on archive encryption.

## 12. Final deployment test

**Scope:** confirm the branch is in a deployable state before
`git push origin main` triggers Render.

### Gate checks (run at `abf01b1`)

| Check | Command | Result |
|---|---|---|
| Test suite | `./vendor/bin/phpunit` | **432 tests, 1125 assertions, 0 failures** |
| Lint | `./vendor/bin/pint --test` | **360 files clean** |
| Project `package.json` valid | `node -e "JSON.parse(...)"` | **OK** |
| Composer constraints | `composer validate --strict` (CI step) | runs in CI |
| Composer audit | `composer audit --abandoned=ignore` (CI step) | runs in CI |
| npm audit (moderate+) | `npm audit --audit-level=moderate` (CI step, now gating) | runs in CI |
| Migration-status on this box | `php artisan migrate:status` | **1 pending** (`2026_04_24_100000_extend_invoice_immutability_trigger`) — will apply on first deploy container boot |
| Commits ahead of `origin/main` | `git log origin/main..HEAD --oneline \| wc -l` | **4** (the review-remediation sprint) |
| Working tree clean | `git status --porcelain` | clean (the audit file itself is untracked per operator's instruction "do not commit or push") |

### Pre-push checklist (operator)

- [ ] Revoke the old GitHub PAT (`ghp_mdht…`) at github.com/settings/tokens.
- [ ] Re-auth git via `gh auth login` (Keychain) or add SSH key and switch
      the remote to `git@github.com:ihrauto/ihrauto-crm.git`.
- [ ] In the Render dashboard, populate or verify every `sync: false`
      secret (see §6): `APP_URL`, `CORS_ALLOWED_ORIGINS`, **new
      `RESEND_API_KEY`**, `SENTRY_LARAVEL_DSN`, `SENTRY_RELEASE`,
      **new `SUPERADMIN_PASSWORD`**, `GOOGLE_*`,
      `BACKUP_FILESYSTEM_*`, `PUBLIC_FILESYSTEM_*`, optional
      `ASSET_URL`, optional `CRM_SUPPORT_PHONE`.
- [ ] Decide whether to set `BACKUP_ARCHIVE_PASSWORD` (§11
      ATTENTION) — recommended.
- [ ] Decide whether to set `TRUSTED_PROXIES` (M-9) to Render's edge
      CIDRs — optional, safer than `*`.
- [ ] Tag the commit before pushing for clean rollback reference:
      `git tag -a v1.4.0-sec-review-2026-04-24 -m "Security review remediation sprint"`
      (the tag push is `git push origin v1.4.0-…`).

### Push + monitor sequence

```bash
# 1. Pre-flight
cd "/Users/kushtrim/Desktop/MONUNI 4/IHRAUTO/IHRAUTO-CRM"
git log origin/main..HEAD --oneline        # should show the 4 sprint commits
git status --porcelain                     # should be empty

# 2. Push
git push origin main

# 3. Watch CI (GitHub Actions)
gh run watch --exit-status                 # fails if tests / audits fail

# 4. Watch Render
# - Dashboard: ihrauto-crm service, 3 instances, healthCheckPath=/up
# - Each instance: wait for "Live" status
# - One instance at a time picks up the migration (Dockerfile CMD
#   runs `php artisan migrate --force` before Apache)

# 5. Post-deploy smoke
curl -sSfI "$APP_URL/up"                   # expect 200
curl -sSfI "$APP_URL/"                     # expect 200 + CSP header
# In the browser:
# - log in, visit /dashboard, visit /management, open one invoice,
#   download CSV export, upload a work-order photo.

# 6. Sentry check (first 30 min)
# - No spike in new error groups.
# - No Content-Security-Policy-Violation storm (M-1).
# - No RESET_LINK_SENT->INVALID_USER diffs (H-4 trace channel:
#   look for 'password_reset_request_suppressed' log events).
```

### What to do if something goes wrong

1. **Migration failure** — the new trigger is `CREATE OR REPLACE`, so
   it can't fail on "already exists". If it fails on permissions,
   `ihrauto-db` user needs `CREATE TRIGGER` on `invoices` (it does
   by default as db owner). Check `pg_stat_activity` for locks.
2. **CSP breaking a legitimate page** — Render rollback to previous
   deploy. File an issue, then either adjust the CSP in
   `SecurityHeaders` or migrate the offending inline handler to bundled
   JS in a follow-up.
3. **Password policy breaking existing session flows** — does not
   happen; the policy only applies to *new* submissions (register,
   reset, invite, admin-creates-user, admin-resets-user).
4. **Forgot-password "nothing happens" reports** — expected UX; users
   now get the same banner whether or not the email is registered. If
   users complain that they didn't get the email, check Sentry or
   `password_reset_request_suppressed` log for the underlying status.
5. **If post-deploy Sentry shows the new Sentry Scrubber masked
   something ops actually needed to see** — the scrubber mask is
   explicit (`app/Support/SentryScrubber.php`); adjust the
   `SENSITIVE_KEYS` list, re-deploy.

### Outstanding (non-blocking) items recorded elsewhere

| Item | Location | Priority |
|---|---|---|
| C-2 live secret rotations (Resend, superadmin, APP_KEY, Sentry DSN) | §6, `ENG-007` on engineering-board | **do before announcing launch** |
| L-8 parent-folder cleanup | §Security audit — outside repo | low, operator-only |
| H-6b apply same fix to `CheckinService::uploadPhotos` | §9 | medium, next sprint |
| ENG-005 Spatie `teams=true` | engineering-board | planned sprint |
| ENG-006 hashed remember tokens | engineering-board | planned sprint |
| Staging `APP_DEBUG=true` / `AUTO_LOGIN_ENABLED=true` env vars | §2, §6 | low, operator-only |
| `BACKUP_ARCHIVE_PASSWORD` | §11 | medium, nice-to-have before launch |

---

## Verdict

**Ready to push `origin main` → Render deploy** once the operator:
1. Has working git auth (PAT revoked + `gh auth login` or SSH).
2. Has populated the Render `sync: false` secrets, including the
   **rotated Resend key and the new super-admin password (C-2)**.

The one in-repo ATTENTION (H-6b in `CheckinService`) does not block the
deploy — the exploitable path requires `PUBLIC_FILESYSTEM_DRIVER=local`,
which production overrides to `s3`. It does need to land in the next
sprint.

**Overall audit verdict: PASS with noted ATTENTION items.**


---
