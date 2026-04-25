# Changelog

All notable changes to IHRAUTO CRM are documented here.

## [Unreleased] - 2026-04-26 — SMS notifications (ENG-011)

"Your car is ready" SMS to the customer, sent from the work-order page
with one click. Audit-first: every send attempt — successful, failed, or
skipped — produces an immutable CommunicationLog row before any side
effect. Foundation for appointment reminders, TÜV alerts, and dunning
SMS in later sprints.

### Architecture

- New dependency: `twilio/sdk` (^8.11).
- `App\Services\SmsService` wraps `Twilio\Rest\Client` with: tenant /
  customer opt-in checks, E.164 phone normalization (Swiss / German /
  Austrian formats), audit-row-on-every-attempt.
- `App\Models\CommunicationLog` — append-only model. `deleting` /
  `updating` boot hooks throw to prevent silent scrub of the audit trail.
- New `communication_logs` table tracks tenant, customer, work-order,
  channel, template, body, status, provider id (Twilio SID), error code
  + message. Status lifecycle: `queued` / `delivered` / `failed` /
  `skipped` (with explanatory error code).
- Forward-compat for WhatsApp + email channels (the channel column +
  service interface allow it; only SMS implemented today).

### Configuration

- New env vars: `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`,
  `TWILIO_FROM_NUMBER`, `SMS_DEFAULT_REGION` (default `CH`).
- Tenant must opt in: `tenants.settings.sms.enabled = true`. Optional
  `tenants.settings.sms.from_number` overrides the global From.
- Customer can opt out: `customers.sms_opt_out = true` is honored before
  any send attempt; opt-out logs a skipped row with `error_code = opt_out`.

### HTTP

- `WorkOrderController::notifyCustomer` (POST `/work-orders/{wo}/notify`,
  throttle 30/min, auth required, view-policy authorized): sends the
  "your car is ready" template, returns the resulting log status as a
  flash message (`success` for queued, `error` for failed, `info` for
  skipped). Cross-tenant work orders return 404 via TenantScope.

### UI

- Work-order show page: green "Notify customer (SMS)" button rendered
  when (a) tenant has SMS enabled, (b) customer has a phone, (c) customer
  hasn't opted out. Renders as a disabled grey state (with explanatory
  tooltip) when the customer has opted out.

### Tests

- `SmsServiceTest` (17): E.164 normalization across 10 phone formats
  (CH/DE/AT, leading-0, +, 00, edge cases), guards (tenant disabled,
  customer opted out, no phone, missing creds), success/failure paths
  with mocked Twilio client, append-only contract on CommunicationLog.
- `WorkOrderNotifyCustomerTest` (5): happy-path log creation, skipped
  log on disabled tenant, auth required, cross-tenant 404, route throttle.

### Verification

- `php artisan test`: 552 passing (1824 assertions), all green.
- `./vendor/bin/pint --test`: clean on every changed file.

### Future work

- Twilio status webhooks → flip CommunicationLog from `queued` to
  `delivered` / `undelivered` based on real carrier delivery.
- WhatsApp Business API channel (next sprint — needs Twilio approval
  + sender registration).
- Auto-fire on work-order completion (currently manual only — admin
  clicks the button — to avoid surprise spam during integration testing).
- Per-tenant SMS settings UI (enable/disable, from-number) under
  Management → Settings.

---

## [Unreleased] - 2026-04-26 — Stripe billing (ENG-010)

Replaces the local-only mock subscription flow with real Stripe Checkout +
Customer Portal via Laravel Cashier. Plan changes, card updates,
cancellations, and dunning self-recovery now happen on Stripe's hosted UI;
the platform reacts via webhook.

### Architecture

- New dependency: `laravel/cashier` (^16.5).
- Tenant is the Cashier Billable (one paying customer per tenant; users
  are operators on that tenant's account). `Cashier::useCustomerModel(Tenant::class)`
  set in `AppServiceProvider::boot`.
- Subscriptions table uses `tenant_id` instead of Cashier's default
  `user_id` — published migration patched accordingly.
- Cashier's customer-columns migration repointed at `tenants` table and
  drops the `trial_ends_at` collision (we already own that column for
  the app-level trial used by `EnsureTenantTrialActive`).
- Plan → Stripe Price ID mapping in `config/services.php`
  (`services.stripe.prices.basic` / `.standard`). `custom` plan stays
  sales-led (no Stripe price).

### HTTP

- `BillingController`:
  - `index` — pricing page (existed; now reflects subscription state).
  - `checkout($plan)` — creates Stripe Checkout session via Cashier with
    success/cancel callbacks and `tenant_id`/`plan_key` metadata.
  - `success` — post-checkout placeholder; we don't trust the redirect
    as proof of payment, the webhook is authoritative.
  - `cancel` — friendly redirect back to pricing.
  - `portal` — `redirectToBillingPortal` so the tenant admin manages
    plan, card, invoices, cancellation in Stripe's hosted UI.
- `StripeWebhookController` (extends `Cashier\WebhookController`):
  - **Signature verification** via `Stripe\Webhook::constructEvent` —
    invalid sig → 403.
  - **503 if webhook secret missing** — refuse to silently process
    forged events when env was misconfigured.
  - **Idempotency** keyed on `event.id` in cache for 24h — Stripe
    redeliveries are no-ops.
  - Lifecycle handlers wire local Tenant state on top of Cashier's
    own subscription-row updates: `subscription.created/updated`
    set plan + is_active + subscription_ends_at; `.deleted` flips
    is_active off; `invoice.payment_failed` sets
    `tenant.settings.billing_status = past_due`;
    `invoice.payment_succeeded` clears the flag.
- `SubscriptionController::process` no longer a hard-coded mock — local
  env still flips a tenant onto a paid plan without contacting Stripe
  (so QA can test post-checkout app behavior without burning Stripe
  test sessions); every other env redirects to `BillingController::checkout`.

### Routes

- `POST /stripe/webhook` — public, no auth, no CSRF, no tenant middleware
  (verified by signature). Throttle 120/min for retry envelope. CSRF
  exemption added in `bootstrap/app.php`; tenant middleware exempted via
  `withoutMiddleware()` on the route.
- `GET /billing/checkout/{plan}` — auth + throttle 10/min.
- `GET /billing/success` — auth.
- `GET /billing/cancel` — auth.
- `GET /billing/portal` — auth + `permission:manage settings` + throttle 10/min.

### UI

- Past-due dunning banner in the layout (visible whenever
  `tenant.settings.billing_status === 'past_due'`). Inline rose-colored
  alert with single-click "Update billing" button that goes to the
  Customer Portal. Permission-gated to admins.
- Post-checkout success page with reassurance message ("we're
  confirming the payment") since the webhook is what authoritatively
  activates the account.

### Configuration

- New env vars in `.env.example`: `STRIPE_KEY`, `STRIPE_SECRET`,
  `STRIPE_WEBHOOK_SECRET`, `STRIPE_PRICE_BASIC`, `STRIPE_PRICE_STANDARD`,
  `STRIPE_TRIAL_DAYS`, `CASHIER_CURRENCY`, `CASHIER_CURRENCY_LOCALE`.

### Tests

- `StripeWebhookTest` (6): no signature → 403, invalid signature → 403,
  503 when secret missing, idempotency cache contract,
  `invoice.payment_failed` flips tenant to past_due,
  `invoice.payment_succeeded` clears the flag.
- `BillingControllerTest` (6): unauth redirect, sales-led plan
  redirect with banner, cancel redirect, portal-without-stripe-id
  redirect, manage-settings permission gate, throttle.

### Verification

- `php artisan test`: 530 passing (1759 assertions), all green.
- `./vendor/bin/pint --test`: clean on every changed file.

---

## [Unreleased] - 2026-04-26 — Audit follow-up (deferred items)

Follow-up to the same-day audit pass: closes the four items the previous
batch deferred as too invasive for an unsupervised run.

### Authorization

- New `PaymentPolicy`: payments are append-only — `update` / `delete` / `forceDelete` return `false` for everyone (the void flow uses reversing negative payments instead). `view` / `viewAny` require `access finance` and tenant membership.
- New `UserPolicy`: super-admin sees everything via `before()`. Tenant scope on view / update; **self-delete is refused** (a misclick used to lock an admin out of their own session, and if last-admin, the tenant). Delete additionally requires `delete records`.
- New `TenantPolicy`: super-admin via `before()`. Tenant admins can view + update (`manage settings`) only their own tenant. Lifecycle transitions (delete, suspend, forceDelete, create) return `false` even for in-tenant admins — those are super-admin / billing-flow only.
- All three policies registered in `AppServiceProvider::boot`.

### PII encryption (DATA-03 follow-up)

- `Tenant.phone`, `Tenant.address`, `Tenant.vat_number` and `User.phone` are now encrypted at rest (Laravel `encrypted` cast). New migration `2026_04_26_000000_extend_pii_encryption.php` widens those columns to TEXT on Postgres and re-encrypts existing rows in place.
- `Tenant.email` intentionally left cleartext — signup uniqueness check needs equality lookup which can't operate on encrypted ciphertext (different IV per write). A `tenant.email_hash` column + custom validator is the proper extension; queued separately because it touches every signup-related test.

### Mass-assignment hardening

- `BelongsToTenant` trait now enforces a runtime cross-tenant write guard. On `creating` in an HTTP context, refuses if the supplied `tenant_id` differs from the bound tenant context. On `updating`, treats `tenant_id` as immutable. Console / queue / test contexts are exempt — those are seeders and provisioning paths, not the attack surface. Closes the residual mass-assignment risk that the previous batch left open by deferring the fillable removal.

### CSP nonce infrastructure (ENG-008 step 1)

- `SecurityHeaders` middleware now generates a per-request CSP nonce (random_bytes + base64). The nonce is bound to the container as `csp.nonce` and injected into `script-src` / `style-src` directives. The previous `'unsafe-inline'` allowance is preserved for now so unmigrated inline scripts keep working — the nonce is **additive**.
- New `csp_nonce()` Blade helper exposes the value to templates. Applied to the Dashboard Studio panel inline script and to the layout's SweetAlert flash block as the migration template.
- Engineering board updated: ENG-008 step 1 marked done with the explicit migration roadmap for the remaining steps (nonce ~22 inline scripts, convert ~76 inline event handlers to Alpine, switch Alpine build to `@alpinejs/csp`, finally drop `'unsafe-inline'` and add `'strict-dynamic'`).

### Tests

- Added `NewPoliciesTest` (8 cases): payment update/delete blocked for everyone, payment view tenant-scoped, user policy denies self-delete, user policy tenant-scoped + permission-gated, tenant policy view self-only, tenant update requires `manage settings`, tenant lifecycle transitions blocked for in-tenant admin.
- Added `PiiEncryptionAtRestTest::tenant_top_level_contact_pii_is_encrypted_at_rest` and `user_phone_is_encrypted_at_rest`.
- Added `CrossTenantWriteGuardTest`: HTTP-context create with mismatched `tenant_id` throws, HTTP-context `tenant_id` reassignment throws.
- Added `CspNonceTest`: every HTML response carries a nonce in `script-src` / `style-src`, and the Studio panel's inline script carries the response nonce.

### Verification

- `php artisan test`: 519 passing (1728 assertions), all green.
- Pint clean on every changed file.

---

## [Unreleased] - 2026-04-26 — Whole-app audit remediation

Multi-agent code review covered models, controllers, middleware, services,
console commands, and frontend Blade/JS. The verified, low-risk findings
were fixed in this pass; the more invasive items (encrypt extra PII columns,
add missing policies for Payment/User/Tenant, drop CSP `unsafe-inline`,
migrate inline event handlers) are queued separately.

### Security

- **API error leakage**: `Api\Concerns\ApiResponse::apiError` previously emitted `details.exception` (raw `$e->getMessage()`) on every error response — leaking SQL fragments, file paths, and model-not-found IDs in production. `details` now only ships in debug builds.
- **WorkOrder access control**: `WorkOrderController::edit/show/bulkStatus` now run `$this->authorize(...)` so technicians can no longer read another technician's WO via deep-link or bulk-flip every WO in the tenant by hitting `/work-orders/bulk-status`.
- **Throttling**: added throttle middleware to previously-unthrottled mutation endpoints — `POST /work-orders/{wo}/photos` (20/min, was unlimited 5MB-read), `POST /tires-hotel` + `PUT/DELETE/{tire}` (30/min), `/tires-hotel/{tire}/generate-work-order` (10/min), `POST /auth/create-company` (5 / 30min — stops a hostile signed-in actor spawning unlimited tenants from the OAuth flow).
- **Subscription mock endpoint**: `SubscriptionController::process` now `abort_unless(app()->environment('local'))` defensively, in addition to the route-level local-only registration.
- **Tour-complete tenant write**: `/subscription/tour-complete` mutates `tenants.settings.has_seen_tour` for the whole tenant — added `permission:manage settings` middleware so a technician can no longer flip it.
- **Frontend XSS**: replaced six unsafe interpolation patterns in `finance/index.blade.php` (3×), `products-services/parts/table.blade.php`, `products-services/services/table.blade.php`, `layouts/app.blade.php` (SweetAlert flash) with `data-*` attributes / `@json` so customer names containing apostrophes or `</script>` no longer break the JS or create reflected-XSS sinks. Replaced two `innerHTML` template literals in `checkin.blade.php` and `tires-hotel.blade.php` with DOM-API construction so AJAX-search results from `/ajax/customers/search` and `/ajax/tires/search-by-registration` cannot execute injected markup.
- **Audit log redaction**: `Auditable` trait now scrubs every key in the model's `$hidden` array (`password`, `remember_token`, `invite_token`, etc.) from the `before/after` audit payload — bcrypt hashes no longer round-trip into `audit_logs.changes`.
- **Destructive console commands**: `crm:clean-demo-data` and `crm:purge-users` now refuse to run in production unless `APP_ALLOW_DESTRUCTIVE=1` is explicitly set — guard against ops typos that would otherwise wipe live data.

### Dashboard Studio (ENG-009 follow-up)

- **Toggle race**: `toggleWidget` now ignores clicks while a save is in flight. Two quick toggles used to capture inconsistent rollback snapshots, letting a transient server failure silently revert the user's most recent click.
- **SortableJS leak**: `initDashboardSortable` now destroys the previous Sortable instance before re-binding. Each fragment swap was orphaning instances that retained document/window listeners.
- **Mobile drag**: SortableJS now uses `delay: 200, delayOnTouchOnly: true, touchStartThreshold: 5` so a touch that starts on a widget can still scroll the page instead of immediately entering drag mode.
- **Concurrent-tab race**: `setEnabled` and `setOrder` now `refresh()` the user model and reload the tenant relation before mutating, so two tabs racing different writes don't trample each other's `enabled` / `order` state.
- **Plan-downgrade preservation**: `setEnabled` now merges previously-stored, currently-plan-locked keys back into the saved list so a re-save during a feature-flag downtime doesn't silently wipe them. Re-upgrading restores the user's original choice.
- **Order/enabled drift**: `setEnabled` prunes disabled keys from the saved drag-order, and `setOrder` intersects against the currently-enabled set. Stops the JSON column from accumulating stale keys forever.
- **Reset-then-reorder freeze**: `setOrder` no longer materializes role-default-derived `enabled` into stored on a no-preference user. Previously a single drag converted "no preference, use defaults" into "preference: this exact set", breaking future catalog additions for that user.
- **Recent-customers null safety**: defensive null-coalesce on `email`/`phone`/`name` so a row missing one of those keys doesn't trigger a Blade warning.
- **All-work-orders DB-in-view**: moved `WorkOrder::count()` from the Blade template into `DashboardService::getStats()` so the count flows through the same provider system + cache layer as every other widget.
- **9 new widgets shipped**: long-running jobs, total customers (with growth), today's check-ins, pending check-ins, monthly revenue (CHF), outstanding balance, overdue invoices, tires in storage, plus three list-style widgets (recent payments, recent invoices, recent customers).

### Business logic

- **Stock void idempotency**: `StockService::reverseForWorkOrder` is now idempotent. A retried void task or a second click no longer double-increments stock — a probe checks for an existing `void_reversal` movement and short-circuits.
- **Invoice rounding tolerance**: `InvoiceService::syncPaymentState` now uses a half-cent epsilon when comparing paid_amount to total. A 0.5-rappen arithmetic remainder no longer demotes a fully-paid invoice to PARTIAL.
- **Quote sequence transaction guard**: `QuoteService::generateQuoteNumber` now throws if called outside a transaction, mirroring the existing `InvoiceService` guard. `lockForUpdate` is a no-op without an enclosing transaction; the guard prevents future callers from accidentally racing on a non-transactional path.
- **Cross-tenant service lookup**: `InvoiceService::buildInvoiceItems` now scopes the service-name lookup by `$workOrder->tenant_id`. The TenantScope normally handles this, but in console / queue contexts it's silent — and across tenants two services may share a name, causing the wrong price to be billed.
- **Tire-storage tenant assertions**: `TireStorageService::getStatistics` and `calculateStorageUtilization` now refuse to run without a resolved tenant context. `createCustomer` and `createVehicle` now stamp `tenant_id` explicitly rather than relying on the BelongsToTenant trait's null-fallback.
- **Quote tenant cross-check**: `QuoteService::create` now refuses a customer whose tenant doesn't match the current tenant context.
- **Cache invalidation on payment**: `PaymentObserver::updateInvoiceBalance` now flushes the tenant-scoped `dashboard_stats_*` and `finance_*_*` caches so revenue / outstanding tiles refresh immediately instead of staying stale for the cache TTL.

### Tests

- Added `set_enabled_preserves_plan_locked_keys_across_redowngrade_resave`, `set_enabled_prunes_disabled_keys_from_saved_order`, `set_order_intersects_with_currently_enabled_keys`, `set_order_does_not_freeze_role_defaults_into_enabled` to lock in the Studio service fixes.
- Added `every_data_provider_resolves_to_a_real_method_on_dashboard_service` and `every_widget_module_is_in_the_known_module_set` to catch catalog typos at CI time.
- Added `dashboard_renders_with_every_widget_enabled_for_an_admin` smoke test.
- Added `user_cannot_mutate_another_users_dashboard_widgets` to lock in the studio's per-user write contract.
- Added `widgets_fragment_returns_only_the_grid_html`, `widgets_fragment_requires_authentication`, `widgets_fragment_renders_empty_state_when_nothing_enabled`.
- Added `test_reversal_is_idempotent` for `StockService`.
- Added `test_almost_paid_within_half_cent_is_treated_as_paid` for the invoice rounding fix.

### Verification

- `php artisan test`: 502 → 505 passing (1681 → 1686 assertions), all green.
- `./vendor/bin/pint --test`: clean on every changed file.

---

## [Unreleased] - 2026-04-25 — Dashboard Studio (per-user widget toggles)

### Features

- **ENG-009**: Dashboard Studio. New header-mounted "Customize" pill on `/dashboard` opens a per-user widget panel. Each row toggles a widget on/off; the dashboard re-renders with only the enabled widgets. Plan-locked widgets (e.g. Tire Hotel for tenants without the feature flag) appear with 🔒 and cannot be enabled. Widgets the user lacks role permission for are hidden entirely. Defaults are role-based (admin/manager/technician/receptionist); users who never open the panel see the role default. Once any toggle flips, the user's stored list takes over.

### Architecture

- New `App\Support\DashboardWidgetCatalog` is the single source of truth for widget identity, gating, defaults, partial path, and size. Adding a future widget = 1 catalog entry + 1 partial; controller, service, and routes don't change.
- New `App\Services\DashboardStudioService` filters the catalog by what the user can actually see (Spatie permission + module access + tenant feature flag), persists changes, and serves the renderer. Stale enabled keys (e.g. tenant downgraded) are silently filtered at render time without losing the preference, so re-upgrading restores them.
- `DashboardController` now computes data only for enabled widgets. Two widgets sharing a data provider trigger only one query.
- The 12 dashboard sections were extracted into `resources/views/dashboard/widgets/*.blade.php` partials with no visual change.

### Database

- New migration `2026_04_25_000000_add_dashboard_widgets_to_users.php` adds nullable JSON column `users.dashboard_widgets`. Mass-assignment is intentionally NOT enabled — writes go through `DashboardStudioService::setEnabled()` which validates against the catalog and caps the list at 50 keys.

### HTTP

- New routes under `/dashboard/studio`:
  - `GET /dashboard/studio` — JSON catalog + enabled keys.
  - `POST /dashboard/studio` — persist a list (throttle 30/min). Validates payload shape; silently drops unknown / disallowed keys.
  - `POST /dashboard/studio/reset` — clears stored preference, falls back to role defaults (throttle 10/min).

### Tests

- Added `DashboardWidgetCatalogTest`, `DashboardStudioServiceTest`, `DashboardStudioControllerTest`, `DashboardRenderTest`.

---

## [Unreleased] - 2026-04-24 — Security review remediation sprint

### Security fixes (Critical)

- **C-1**: stripped GitHub Personal Access Token from `.git/config` remote URL. Remote now uses unauthenticated HTTPS; operators re-authenticate via `gh auth login`, SSH, or a credential helper. Old PAT must be revoked at github.com/settings/tokens.

### Security fixes (High)

- **H-1**: removed unenforced `two_factor_required` column from `Tenant::$fillable` and `$casts` (and the factory). Column retained in DB for the eventual real 2FA feature; no code can set it via mass assignment today, so nothing misleads tenants into thinking 2FA is active.
- **H-2**: `ProfileUpdateRequest` now requires `current_password` whenever the submitted email differs from the authenticated user's email. Closes the session-only account-takeover chain (XSS/stolen cookie → change email → forgot-password → lockout). Profile edit form exposes the password field conditionally via Alpine.
- **H-3**: `/subscription/setup` now requires `permission:manage settings`. Previously any authenticated tenant user could rewrite the tenant's IBAN, bank, email, and tax rate — a vector to redirect invoice payouts.
- **H-4**: `PasswordResetLinkController::store` always returns the same success banner regardless of `INVALID_USER` / `RESET_LINK_SENT`. Account-enumeration oracle closed; failed statuses are logged so ops can still see mailer / throttle failures.
- **H-5**: storage + bootstrap/cache permissions tightened from `775` to `770`. Documented in the Dockerfile why supervisord and the Apache master still run as root (TCP/80 binding); all PHP user code already executes as `www-data`.
- **H-6**: `WorkOrderPhotoController` now derives the stored filename's extension from the sniffed image type (`IMAGETYPE_*`) instead of `$file->getClientOriginalExtension()`. Defeats double-extension / polyglot attacks (`shell.php.jpg` → `*.jpg`).
- **H-7**: new migration `2026_04_24_100000_extend_invoice_immutability_trigger` — Postgres trigger now also locks `discount_total`, `customer_id`, `vehicle_id` once an invoice is issued, matching the model's `IMMUTABLE_FIELDS` constant.

### Security fixes (Medium)

- **M-1**: `Content-Security-Policy`, `Cross-Origin-Opener-Policy: same-origin`, and `Cross-Origin-Resource-Policy: same-origin` added for HTML responses. CSP still carries `'unsafe-inline'` on script/style while Blade relies on inline Alpine handlers; the big wins (`object-src 'none'`, `form-action 'self'`, `frame-ancestors 'self'`, `base-uri 'self'`, `upgrade-insecure-requests` in prod) are enforced today.
- **M-6**: `invite_token` added to `User::$hidden` so it cannot leak via `toArray()`, `toJson()`, or debug dumps.
- **M-7**: slow-query logger scrubs string / object / array bindings (`str(<len>)`, `obj(<class>)`, `arr(<count>)`); numeric and boolean bindings are preserved since they are rarely PII. Keeps ops signal without pushing customer PII into log aggregators.
- **M-8**: `ManagementController::export` neutralises CSV formula injection — any customer-sourced cell beginning with `=`, `+`, `-`, `@`, tab, or CR is prefixed with a single quote so spreadsheets render it as literal text.
- **M-9**: `TrustProxies::$proxies` now reads from the `TRUSTED_PROXIES` env var (comma-separated IPs/CIDRs). Falls back to `*` only when unset; `.env.example` documents the flag.
- **M-10**: CI `npm audit --audit-level=moderate` no longer ends with `|| true`; moderate+ advisories now gate the build, matching the existing `composer audit` behaviour.

### Security fixes (Low / Info)

- **L-1**: password policy tightened to `Password::min(12)->mixedCase()->numbers()` via `Password::defaults(…)` in `AppServiceProvider::boot`. Production additionally enforces `->uncompromised()` (HIBP k-anonymity). `ManagementController::storeUser/updateUser` and `InviteController::setup` migrated off `min:8` onto the shared default.
- **L-6**: `robots.txt` disallows authenticated / administrative paths.
- **L-9**: `SentryScrubber::handle` wired as Sentry's `before_send`. Masks password / token / IBAN / phone / auth header fields in request data, headers, and cookies before transmission.

### Deferred

- **M-3** Spatie `teams=true` (team-scoped roles) — not exploitable today because users have exactly one `tenant_id`, but a fragility when multi-tenant user membership lands. Requires coordinated schema migration + data backfill + permission-cache flush; scheduled for a dedicated sprint.
- **M-5** hashed `remember_token` at rest — needs `SessionGuard::viaRemember` override plus a migration strategy for existing plain-text tokens (invalidate vs. dual-verify); scheduled separately.

### Blocked on operator action

- **C-2** rotate `RESEND_API_KEY`, `SUPERADMIN_PASSWORD`, consider rotating `APP_KEY` and Sentry DSN. Secrets sit in local `.env` only (not committed), but must be rotated because they were visible during development.
- **L-8** move `aws-laravel-key.pem` out of `~/Desktop/MONUNI 4/IHRAUTO/` into `~/.ssh/` or a secrets manager; delete the stale `IHRAUTO-CRM copy/` directory which likely contains a pre-rotation `.env`.

### Verification

- `./vendor/bin/phpunit` — 427 tests, 1093 assertions, all green.
- Existing tests using `password123` / `new-password` / raw `password` as the posted value were updated to match the hardened rule.
- New tests: `SubscriptionSetupAuthorizationTest`, `CsvExportInjectionTest`, `SentryScrubberTest`, plus additions to `ProfileTest`, `PasswordResetTest`, `WorkOrderPhotoAuthorizationTest`.

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
