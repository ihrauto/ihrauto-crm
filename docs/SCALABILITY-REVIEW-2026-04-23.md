# IHRAUTO CRM — Scalability Review for 200 Tenants

**Date:** 2026-04-23
**Target:** 200 tenants × ~5 users each × ~30 req/s peak
**Reviewers:** 4 parallel specialist audits (DB, app layer, infra, hotspots)
**Status:** Report only — no code changes yet.

---

## EXECUTIVE SUMMARY

### Bottom line

**Current setup:** safe up to **~30–50 tenants**. Beyond that, multiple bottlenecks cascade.

**At 200 tenants today, expect:**
- Dashboard loads: **2–4 seconds** (target <500ms)
- Customer search: **500ms–2s** (target <200ms)
- DB connection exhaustion in 5–10 minutes of peak traffic → **500 errors**
- Duplicate scheduler runs if scaled horizontally → **data corruption**
- Backup competing with daily-reminder jobs → mail transport chokes

### Single biggest blocker

**Database connection exhaustion.** Postgres `basic-1gb` allows 100 connections. 200 tenants × Apache prefork workers (~25/container) × small read bursts = hundreds of concurrent connections needed. Without PgBouncer, the app will hit "no connection available" within minutes of real traffic.

### Can we reach 200 tenants with reasonable work?

**Yes — ~13 engineering hours for the critical fixes, plus ~$160/month additional infrastructure.** Detailed roadmap below.

---

## PART 1 — THE 5 CRITICAL BLOCKERS

These would break the app outright at 200 tenants. Fix before anything else.

### 🚨 BL-1 — DB connection pool exhaustion

- **Evidence:** `config/database.php:85-112` has no pool config. Apache mpm_prefork (Dockerfile line 125) runs ~25 workers; each opens a PG connection. 3 containers × 25 workers = 75 connections baseline. Under burst, easily 100+.
- **Plan:** `render.yaml` currently uses `basic-1gb` (100 max connections). Queue worker + scheduler + migrations consume another ~5–10.
- **Impact:** `PDOException: too many connections for role` → 500 errors for every new request.
- **Fix (4h):** Deploy **PgBouncer** sidecar (or switch to Render Postgres Professional which includes a pooler).
- **Cost:** +$35/month if we upgrade to `basic-2gb` (200 max connections); PgBouncer adds ~$5–7 if external.

### 🚨 BL-2 — Unindexed invoice status queries

- **Evidence:** [DashboardService.php:125-130](app/Services/DashboardService.php), [FinanceService.php:20-35](app/Services/FinanceService.php). Overdue-count query scans 400k invoices per page load.
- **Plan:** `invoices` has `(tenant_id, invoice_number)` unique, but no composite on `(tenant_id, status, due_date)`.
- **Impact:** Dashboard takes 2–3s at 200 tenants; opening Finance tab on a busy system can hold a DB worker for 1s each.
- **Fix (1h):** Migration adding `(tenant_id, status, due_date)` and `(tenant_id, issue_date)` composites.
- **Cost:** $0.

### 🚨 BL-3 — Customer/vehicle search uses LIKE without trigram indexes

- **Evidence:** [CustomerController.php:37-45](app/Http/Controllers/CustomerController.php), [Api/CustomerController.php:55-65](app/Http/Controllers/Api/CustomerController.php), [FinanceController.php:29-45](app/Http/Controllers/FinanceController.php).
- Uses `whereRaw('LOWER(name) LIKE ?', ['%...%'])` — forces full table scan.
- **Plan:** `pg_trgm` extension not enabled anywhere in migrations.
- **Impact:** At 100k customers, every search = 500ms–2s. Core workflow — check-in receptionist types plate number to find customer — will visibly lag.
- **Fix (2h):**
  ```sql
  CREATE EXTENSION IF NOT EXISTS pg_trgm;
  CREATE INDEX customers_name_trgm ON customers USING GIN (name gin_trgm_ops);
  -- plus vehicles.license_plate, invoices.invoice_number, products.sku, products.name
  ```
  Then rewrite search to use `%` operator or combined `LIKE` (trigram index accelerates both).
- **Cost:** $0.

### 🚨 BL-4 — Scheduler will duplicate-fire on horizontal scaling

- **Evidence:** [routes/console.php:12-28](routes/console.php) has 5 `Schedule::command()` calls, **none** use `->onOneServer()`. [docker/supervisord.conf:32](docker/supervisord.conf) runs scheduler unconditionally per container.
- **Impact the moment you scale to 2+ app containers:**
  - `backup:run` fires 2× → corrupt/partial backups.
  - `invoices:send-overdue-reminders` fires 2× → customers get duplicate reminders.
  - `invoices:auto-issue-stale` fires 2× → race conditions inside the sequence lock (not data corruption thanks to DB constraints, but noisy errors).
- **Fix (2h):** Add `->onOneServer()` to every `Schedule::command()` + ensure Redis-backed lock is active. Laravel 12 + Redis supports this out of the box.
- **Cost:** $0.

### 🚨 BL-5 — Scheduled commands + notifications run inline instead of queued

- **Evidence:**
  - [SendOverdueInvoiceReminders.php:40-95](app/Console/Commands/SendOverdueInvoiceReminders.php) — `Notification::send()` called synchronously in a nested loop.
  - [LowStockReportCommand.php](app/Console/Commands/LowStockReportCommand.php), [AutoIssueStaleDraftsCommand.php](app/Console/Commands/AutoIssueStaleDraftsCommand.php) — same pattern.
  - **None of the notification classes** (`InvoiceOverdueNotification`, `InvoiceIssuedNotification`, `LowStockDigestNotification`, `MechanicInviteNotification`) implement `ShouldQueue`.
- **Impact:** 200 tenants × ~50 overdue invoices × 2 admins = **20 000 synchronous mail transmissions** during the 08:30 daily slot. Mail transport throughput becomes the bottleneck; scheduler process hangs for 30–60 min; subsequent scheduled jobs (low-stock, backup clean) run late or get skipped.
- **Fix (1h):** Add `implements ShouldQueue` to each `*Notification` class. The commands themselves already use `chunkById`, so once mail is async they return quickly.
- **Cost:** $0.

---

## PART 2 — THE 10 HIGH-IMPACT ISSUES

### H-1 — Dashboard runs 17 queries on cache miss

[DashboardService.php:33-143](app/Services/DashboardService.php) — 17 separate queries stitched together. Cache-hit masks this; cache stampede on boot or 5-min expiry surfaces it. Consolidate into 2–3 aggregated queries (most stats already group-by status/month; one raw `selectRaw()` with CASE WHENs covers everything). **Effort: 3h.**

### H-2 — Finance index loads all 4 tabs on every request

[FinanceController.php:60-90](app/Http/Controllers/FinanceController.php) runs 4 tab queries even though only one tab is visible. ~35 queries per page load. Add tab-aware branching: only run the query for the active tab. **Effort: 2h.**

### H-3 — Dashboard technician status has N+1 on vehicle

[DashboardService.php:289-295](app/Services/DashboardService.php) loads work orders with `with(['technician'])` but the downstream map accesses `$currentJob->vehicle->display_name` — vehicle isn't eager-loaded, 1 extra query per technician. Fix: add `'vehicle'` to `with([...])`. **Effort: 5m.**

### H-4 — Session store is `database`

[config/session.php:21](config/session.php) uses database sessions; at 1000 concurrent users the `sessions` table becomes a lock-contention hotspot (every request writes). Switch to Redis. **Effort: 1h.**

### H-5 — Audit-log table grows unbounded

`app/Traits/Auditable.php` writes 1 row per model create/update/delete. At 200 tenants × ~500 mutations/day = 100k rows/day = **36M rows/year**. No archival. Old audit queries start dragging on the whole table. **Fix: retention job — archive rows >2y old to cold storage, then delete.** **Effort: 3h.**

### H-6 — No request-scoped tenant cache

[TenantContext.php:39-42](app/Support/TenantContext.php) `id()` fallback triggers a user query if `current()` is null. Under high traffic, this runs per request per bound model. Memoize per-request. **Effort: 1h.**

### H-7 — Cache stampede on per-tenant stat queries

Cache-remember with 300s TTL means every 5 minutes, ~200 tenants' cache keys expire at roughly the same wall-clock time (the cached-at timestamps aren't jittered). Simultaneous recomputes. `CachedQuery::remember` helper exists but isn't used everywhere. Apply it to remaining hot queries + add TTL jitter (`300 + rand(0, 60)`). **Effort: 2h.**

### H-8 — Queue worker is `numprocs=1`

[docker/supervisord.conf:18](docker/supervisord.conf) runs a single queue worker. With BL-5 fixed (notifications async), that single worker has to drain 20 000+ jobs/day + the normal request-driven queue load. Scale to 2–3 workers. **Effort: 30m.**

### H-9 — No slow-query logging in production

Render Postgres doesn't have `log_min_duration_statement` configured anywhere in `render.yaml`. Means we're blind to which query is slow. Enable log_min_duration_statement = 500ms (anything slower is captured). **Effort: 30m.**

### H-10 — Backup blocks overnight workload

[routes/console.php:12](routes/console.php): `backup:run` runs at 02:00 UTC. At 200 tenants with ~20 GB uncompressed DB, pg_dump takes 10–30 min. It doesn't block writes (uses `ACCESS SHARE` lock), but it burns 50%+ of DB CPU for that window. If a tenant from another timezone is working at 02:00 UTC, they see noticeable slowdown. Also: queue worker competes for the same DB during backup. **Fix: longer-term — offload backup to a dedicated small Render cron instance; short-term — keep the 02:00 UTC slot and add monitoring.** **Effort: 30m now, 4h later for dedicated backup runner.**

---

## PART 3 — THE 10 MEDIUM ISSUES (fix after criticals land)

| # | Issue | File:line | Effort |
|---|---|---|---|
| M-1 | Customer `destroy()` fires 7 separate `count()` dependency checks | [CustomerController.php:85-93](app/Http/Controllers/CustomerController.php) | 1h |
| M-2 | `TireStorageService::getStatistics()` runs 7 separate count/sum queries | [TireStorageService.php:15-26](app/Services/TireStorageService.php) | 2h |
| M-3 | `ReportingService::getPerformanceMetrics()` loops 7 days with 3 queries each | [ReportingService.php:85-103](app/Services/ReportingService.php) | 2h |
| M-4 | `FinanceService::getTopServices()` not wrapped in `CachedQuery` | [FinanceService.php:101](app/Services/FinanceService.php) | 15m |
| M-5 | PHP `memory_limit=256M` tight for busy containers | [docker/php.ini:14](docker/php.ini) | 15m |
| M-6 | `resolveTasksAndParts` fires per-service query in loop | [WorkOrderService.php](app/Services/WorkOrderService.php) | 1h |
| M-7 | Appointment `events()` endpoint not cached (FullCalendar polls it) | [AppointmentController.php:30-45](app/Http/Controllers/AppointmentController.php) | 2h |
| M-8 | Notifications lack `ShouldQueue` (already covered in BL-5) | various | — |
| M-9 | No structured JSON logging (debugging multi-tenant issues is painful) | [config/logging.php](config/logging.php) | 2h |
| M-10 | Rate limiter is static (60 req/min per token) — can't tier by plan | [AppServiceProvider.php:45](app/Providers/AppServiceProvider.php) | 1h |

---

## PART 4 — INFRASTRUCTURE PLAN FOR 200 TENANTS

### Current (staging at `ihrauto-crm-staging`)

| Component | Plan | Monthly cost |
|---|---|---|
| Web | 1× `starter` | $7 |
| Postgres | `basic-1gb` | $15 |
| Redis | `starter` | $7 |
| **Total** | | **$29** |

### Recommended for 200 tenants

| Component | Plan | Monthly cost | Why |
|---|---|---|---|
| Web | 3× `standard` (1 vCPU, 1 GB each) | $36 | 3× throughput, rolling-deploy-safe |
| Postgres | `basic-2gb` (200 max conns) + PgBouncer | $50 | Connection pool headroom |
| Redis | `standard` (5 GB) | $4.50 | Sessions + cache + rate-limiter |
| Backup runner | 1× `starter` (dedicated) | $7 | Isolate backup CPU load |
| S3 | AWS bucket | ~$1 | Backup storage + uploads |
| Sentry | Business plan | $99 | 100k events/month — your error budget will 10× |
| **Total** | | **~$197** |

### Key env / config changes required

1. `CACHE_STORE=redis` (already done)
2. `SESSION_DRIVER=redis` (**currently `database`** — must change)
3. `QUEUE_CONNECTION=redis` (already done)
4. PostgreSQL server-level `log_min_duration_statement = 500`
5. PgBouncer `pool_mode = transaction`, `default_pool_size = 20`

---

## PART 5 — ROADMAP (BATCHED FOR SENSIBLE DEPLOYS)

### Sprint A — "Survive 50 tenants" (1 day of work)

Must-do before adding more tenants.

1. **BL-2** Add invoice composite indexes (`(tenant_id, status, due_date)`, `(tenant_id, issue_date)`). Migration. 1h.
2. **BL-3** Enable `pg_trgm` + GIN indexes on customers.name, vehicles.license_plate, invoices.invoice_number, products.sku. Migration. 2h.
3. **BL-5** Add `implements ShouldQueue` to all 4 notification classes. 1h.
4. **H-3** Add `vehicle` to the `with([...])` in DashboardService::getTechnicianStatus. 5m.
5. **H-9** Enable slow-query log via render.yaml env var. 30m.

**Total: ~4.5 hours. Zero additional cost.** After this, the app is safe at 50–80 tenants.

### Sprint B — "Survive 150 tenants" (1–2 days of work + infra bump)

6. **BL-1** PgBouncer + upgrade to `basic-2gb`. 4h + $35/mo.
7. **BL-4** Add `->onOneServer()` to every Schedule::command. 2h + multi-instance scheduler testing.
8. **H-4** Switch `SESSION_DRIVER` to `redis`. 1h.
9. **H-1** Consolidate DashboardService::computeStats() to 2–3 aggregated queries. 3h.
10. **H-2** Branch FinanceController::index() to only run the active tab's query. 2h.
11. **H-8** Scale queue worker to `numprocs=3`. 30m.

**Total: ~13 hours + ~$35/mo.** After this, the app is stable at 150 tenants.

### Sprint C — "Scale to 200 + headroom for growth" (2–3 days)

12. Scale web to 3× `standard` (Render yaml change, test rolling deploy). 2h + $14/mo.
13. Upgrade Redis to `standard`. 15m − $2.50/mo.
14. Upgrade Sentry to Business. 30m + $99/mo.
15. **H-5** Audit-log retention job (archive → delete >2y). 3h.
16. **H-6** Memoize TenantContext::id() fallback. 1h.
17. **H-7** Apply CachedQuery to remaining hot queries + add TTL jitter. 2h.
18. **H-10** Dedicated backup runner instance. 4h + $7/mo.

**Total: ~13 hours + ~$118/mo.** After this, the app has comfortable headroom past 200.

### Sprint D — "Operational excellence" (ongoing, optional)

19. M-1..M-10 rolled into normal feature work. ~12h total.
20. Load test with k6 — establish baseline at 50, 100, 150, 200 tenants.
21. Capacity-planning spreadsheet for growth beyond 200.

---

## PART 6 — HORIZONTAL-SCALING READINESS CHECKLIST

Copy-paste ready for review:

**Done:**
- [x] Tenant-scoped cache keys everywhere (`DashboardService`, `FinanceService`, `TenantCache`)
- [x] Cache stampede protection helper (`CachedQuery`) ships
- [x] API token lookups cached (60s) with proper invalidation
- [x] Idempotency keys on payments + stock movements
- [x] `BelongsToTenant` trait on every tenant-owned model
- [x] Signed URLs for public invoice PDFs (no shared state needed)
- [x] Auto-login guard resolves at boot, not per-request (S-07)
- [x] Assets served from `public/build/` by Apache directly (no PHP round-trip)

**Still required before scaling to N > 1 containers:**
- [ ] `SESSION_DRIVER=redis` (BL/H-4)
- [ ] `->onOneServer()` on every scheduled command (BL-4)
- [ ] `implements ShouldQueue` on every Notification class (BL-5)
- [ ] PgBouncer or equivalent connection pooler (BL-1)
- [ ] `config:cache` runs on every deploy (currently in Dockerfile CMD — verify it actually caches in prod)
- [ ] Slow-query log enabled (H-9)
- [ ] Rate-limiter backend = Redis (already the case via `tenant-api` limiter + `RateLimiter::for` in AppServiceProvider)

**Nice-to-have before 200 tenants:**
- [ ] Structured JSON logs with request IDs (M-9)
- [ ] Graceful-degradation fallback if Redis is down (M-14 in the full audit)
- [ ] APCu user cache for per-worker in-memory state

---

## PART 7 — CAPACITY ESTIMATES AT 200 TENANTS (YEAR 1)

| Resource | Estimate | Plan provides | Headroom |
|---|---|---|---|
| DB storage | ~100 GB | basic-2gb = 10 GB → must upgrade to basic-10gb later | ❌ |
| DB concurrent connections | ~60 peak | basic-2gb = 200 | ✅ 3× |
| Redis key space | ~100 MB | standard = 5 GB | ✅ 50× |
| Audit logs | 36M rows/year | Unbounded — will need archival | ⚠️ |
| Invoice items | 1.2M/year | Indexed with tenant_id FK — fine | ✅ |
| Stock movements | 2M/year | Indexed — fine | ✅ |
| Peak req/s | ~30–50 sustained, 100 burst | 3× standard containers ≈ 200 req/s | ✅ 4× |
| Daily emails | ~20k notifications | Async via queue + Resend | ✅ |
| Backup file | ~5 GB compressed | S3 retention 7/4/2 = ~100 GB | ✅ |

**Conclusion:** DB storage is the only resource that will force a plan upgrade within year 1. Everything else fits the recommended topology.

---

## DECISION POINTS FOR YOU

Before I start coding, please confirm:

1. **Sprint order.** Do you want A → B → C, or do you want to batch A+B together? A alone gets us to 50 tenants; A+B to 150.
2. **Infrastructure budget.** Monthly run-cost jumps from $29 → ~$197. Acceptable? If not, which items to cut (most painful to cut: PgBouncer, Sentry Business).
3. **Backup strategy.** Keep the simple daily on-main-container, or go straight to a dedicated $7 runner?
4. **Audit log retention.** Keep everything forever, or archive >2 years? (Swiss `OR 958f` legally requires 10 years for business correspondence — safest: archive to cold storage, don't delete).
5. **Mail provider.** Are we actually using Resend in production? The env sets it up but volume at 200 tenants matters for their pricing tier.

---

## FILES TO CHANGE (preview)

A quick map of what will be touched when I start work — so you can sanity-check there are no surprises.

**Sprint A:**
- New migrations: invoice indexes, pg_trgm + GIN indexes
- `app/Notifications/*.php` — 4 notification classes get `implements ShouldQueue`
- `app/Services/DashboardService.php` — 1-line with() fix
- `render.yaml` — add `DB_SLOW_QUERY_LOG=500` env or equivalent

**Sprint B:**
- `render.yaml` — Postgres plan + PgBouncer sidecar config
- `routes/console.php` — add `->onOneServer()` to 5 lines
- `config/session.php` / `.env` — `SESSION_DRIVER=redis`
- `app/Services/DashboardService.php` — rewrite computeStats()
- `app/Http/Controllers/FinanceController.php` — rewrite index() branching
- `docker/supervisord.conf` — `numprocs=3` on queue worker

**Sprint C:**
- `render.yaml` — web instances, Redis plan
- New command `app/Console/Commands/ArchiveAuditLogsCommand.php`
- `app/Support/TenantContext.php` — memoized fallback
- `app/Support/CachedQuery.php` — TTL jitter; applied to more call sites
- New migration: archive-audit-logs table

---

*End of report. Awaiting your approval before changes start.*
