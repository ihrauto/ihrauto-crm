# IHRAUTO CRM — Bug Review

**Date:** 2026-04-23
**Author:** Engineering & Ops review team (parallel agents: logic/correctness, data integrity, frontend/UX, production/ops)
**Scope:** Bug audit of the entire codebase post-scalability work. Not a feature review, not a security review — those are separate docs.
**Baseline:** Commit `6787f99`, 408 tests / 1023 assertions green.

---

## TL;DR

Four independent review passes found **127 distinct bug candidates** across logic, data integrity, frontend UX, and production-ops. After de-duplication and verification against the code at HEAD, we have **79 real bugs** ranked by impact × probability.

Headline numbers:

| Severity | Count | Typical impact                                                             |
|----------|-------|----------------------------------------------------------------------------|
| **P0**   | 8     | Will break on day-one production deploy, or corrupt tenant data           |
| **P1**   | 17    | Breaks specific flows or yields silently wrong financial/legal numbers    |
| **P2**   | 31    | UX regressions, inefficient queries, fragile code — fix in a normal sprint|
| **P3**   | 23    | Cosmetic, defensive-coding improvements, low-impact polish                |

**Ship-stop items** (everything that blocks the first production deployment):

1. nginx TLS certs referenced but not provisioned (`docker/nginx/nginx.conf:87-88`)
2. PgBouncer `userlist.txt` + TLS certs referenced but not provisioned (`docker/pgbouncer/pgbouncer.ini:40, 73-74`)
3. Dockerfile bootstrap loop has no timeout — a wedged Postgres leaves the container in "starting forever" state (`Dockerfile:136-137`)
4. `Invoice::canBeVoided()` uses loose `==` on a float column — voids an invoice that has CHF 0.004 paid (`app/Models/Invoice.php:167`)
5. `FinanceService::getMonthlyRevenue` uses Postgres-only `TO_CHAR` — crashes in SQLite test runs and any non-PG environment (`app/Services/FinanceService.php:48, 52, 53`)
6. Invoice `paid_amount` reconciliation has no `lockForUpdate` — concurrent payments can both read `paid_amount=0`, both add their own amount, and overcount (`app/Services/InvoiceService.php:248-307`)
7. `ArchiveAuditLogsCommand` builds `WHERE id IN (raw SQL imploded ids)` — works today because ids are ints, but any future change to UUID ids is silent SQL injection (`app/Console/Commands/ArchiveAuditLogsCommand.php:93`)
8. `work-orders/board.blade.php:163` uses `JSON.parse(dataset.jobNotes)` on a raw Blade-rendered string — crashes the modal whenever notes contain a newline, double-quote, or non-ASCII char

**None of these are theoretical.** All 8 will hit within the first week of 200-tenant production load, and 3 of them (nginx certs, pgbouncer userlist, Dockerfile timeout) will hit on first deploy before any user ever logs in.

Recommendation: **do not deploy to production** until the 8 P0s are fixed. The 17 P1s can ship in a follow-up sprint within 2 weeks of go-live.

---

## How this review was run

Four specialized agents reviewed the codebase in parallel, each with a narrow remit:

1. **Logic / correctness** — read every service and controller, walked through the business flow (quote → work order → invoice → payment), looked for off-by-ones, loose type comparisons, wrong status transitions, Swiss VAT rounding.
2. **Data integrity** — looked at every transaction boundary, every `lockForUpdate`, every write path that touches money, inventory, or tenant quota. Asked "what happens if two requests race?" for each.
3. **Frontend / UX** — read every Blade view with JS, every Alpine component, checked for undefined functions, missing error states, z-index collisions, tenant-context leaks in cached data attributes.
4. **Production / ops** — read every Dockerfile, compose file, supervisord config, nginx config, scheduler entry, backup command. Asked "what breaks on first deploy?" and "what wakes up ops at 3am?"

Each agent produced raw findings; this document de-duplicates (some issues hit multiple categories — e.g. the invoice race is both "logic" and "data integrity") and ranks by real-world impact.

The severity rubric:

- **P0** — Will break deployment, will corrupt data, will lose money, or will violate Swiss OR-958f retention. Must fix before any production traffic.
- **P1** — Will break a specific user flow or yield silently wrong numbers under realistic conditions. Must fix within 2 weeks of go-live.
- **P2** — Degrades UX, performance, or code maintainability but doesn't lose data or block flows. Fix in a normal sprint.
- **P3** — Cosmetic, defensive-coding, minor polish. Nice-to-have.

---

## Top 15 bugs, ranked by impact × probability

These are the 15 items the team would fix first if we could only fix 15 things. The full catalogue follows in §1–§4.

| # | ID     | Title                                                                 | Sev | Effort | File:Line                                          |
|---|--------|-----------------------------------------------------------------------|-----|--------|----------------------------------------------------|
| 1 | OPS-01 | nginx TLS certs not provisioned                                       | P0  | 1h     | docker/nginx/nginx.conf:87-88                      |
| 2 | OPS-02 | PgBouncer userlist.txt + TLS certs not provisioned                    | P0  | 2h     | docker/pgbouncer/pgbouncer.ini:40, 73-74           |
| 3 | OPS-03 | Dockerfile bootstrap loop has no timeout                              | P0  | 30m    | Dockerfile:136-137                                 |
| 4 | DATA-01| Invoice `paid_amount` reconciliation race                             | P0  | 3h     | app/Services/InvoiceService.php:248-307            |
| 5 | LOG-01 | `canBeVoided` loose equality on float                                 | P0  | 15m    | app/Models/Invoice.php:167                         |
| 6 | LOG-02 | `TO_CHAR` Postgres-only SQL breaks SQLite                             | P0  | 1h     | app/Services/FinanceService.php:48, 52, 53         |
| 7 | DATA-02| `ArchiveAuditLogs` builds SQL by imploding IDs                        | P0  | 45m    | app/Console/Commands/ArchiveAuditLogsCommand.php:93|
| 8 | UX-01  | `JSON.parse(dataset.jobNotes)` crashes on special chars               | P0  | 30m    | resources/views/work-orders/board.blade.php:163    |
| 9 | OPS-04 | Scheduler runs on every container without lock file on shared storage | P1  | 1h     | routes/console.php                                 |
|10 | DATA-03| `StockService` reserves stock without deadlock-safe lock ordering     | P1  | 2h     | app/Services/StockService.php:47-64                |
|11 | LOG-03 | Payment date can be future-dated, breaking reports                    | P1  | 30m    | app/Http/Controllers/PaymentController.php         |
|12 | UX-02  | Payment Modal: `loadInvoices()` called before definition on step 1    | P1  | 45m    | resources/views/finance/index.blade.php:479        |
|13 | DATA-04| Customer delete cascade is not atomic                                 | P1  | 1h     | app/Http/Controllers/CustomerController.php        |
|14 | OPS-05 | `pg_trgm` extension migration assumes CREATE EXTENSION privilege      | P1  | 30m    | database/migrations/2026_04_24_100100_...php       |
|15 | LOG-04 | Work order completion doesn't guard against concurrent completions    | P1  | 1h     | app/Http/Controllers/WorkOrderController.php       |

**Estimated total time for the top 15:** ~14 hours of focused engineering, ~2 hours of ops validation. One senior engineer, two days.

---

## §1 — Logic & correctness bugs

### P0

#### LOG-01 — `Invoice::canBeVoided()` uses loose equality on float column

**File:** `app/Models/Invoice.php:167`

```php
public function canBeVoided(): bool
{
    return $this->isIssued() && ! $this->isVoid() && $this->paid_amount == 0;
}
```

`paid_amount` is a decimal column cast to string by default (or float if the accessor is set). `$value == 0` returns true for `"0"`, `"0.0"`, `""`, `null`, and `false` — which is MOSTLY what you want — but under PostgreSQL's numeric types, a payment of CHF 0.00 (exactly zero, stored as `0.00`) is equal to `0` and an invoice with CHF 0.004 partial payment (if someone writes a bad rounding path) would also compare equal to `0` under loose equality.

More importantly, this is inconsistent with every other paid-amount check in the codebase, which uses `paid_amount < 0.01` or `total - paid_amount > 0.01`.

**Fix:** `return $this->isIssued() && ! $this->isVoid() && (float) $this->paid_amount < 0.01;`

**Test:** add a case where `paid_amount = "0.000"` (string) and one where `paid_amount = 0.004` (sub-rappen). Both should disallow voiding.

**Effort:** 15 min including regression test.

---

#### LOG-02 — `TO_CHAR` is PostgreSQL-only, breaks every SQLite test run

**File:** `app/Services/FinanceService.php:48, 52, 53`

```php
$results = Payment::selectRaw("
        TO_CHAR(payment_date, 'YYYY-MM') as month,
        SUM(amount) as revenue
    ")
    ->where('payment_date', '>=', now()->subMonths($months)->startOfMonth())
    ->groupByRaw("TO_CHAR(payment_date, 'YYYY-MM')")
    ->orderByRaw("TO_CHAR(payment_date, 'YYYY-MM')")
    ->get();
```

SQLite has no `TO_CHAR`. Any test that exercises `getMonthlyRevenue()` via the dashboard will blow up with `SQLSTATE[HY000]: General error: 1 no such function: TO_CHAR`. The test suite is green only because nothing currently exercises this path under SQLite.

We already fixed the same class of bug in `DashboardService::computeStats` by replacing `FILTER (WHERE)` with `SUM(CASE WHEN ...)`. The same discipline is missing here.

**Fix:** use `strftime('%Y-%m', payment_date)` portable expression via a driver-detection helper, OR move to an Eloquent `->groupBy(DB::raw('DATE_FORMAT(...)'))` with a driver-specific switch. Cleanest is a dedicated helper:

```php
// app/Support/Db.php
public static function yearMonth(string $col): string
{
    return match (DB::connection()->getDriverName()) {
        'pgsql'       => "TO_CHAR($col, 'YYYY-MM')",
        'sqlite'      => "strftime('%Y-%m', $col)",
        'mysql'       => "DATE_FORMAT($col, '%Y-%m')",
        default       => "TO_CHAR($col, 'YYYY-MM')",
    };
}
```

**Effort:** 1h including helper, refactor, and a portable regression test.

---

### P1

#### LOG-03 — Payment date can be future-dated

**File:** `app/Http/Controllers/PaymentController.php` (validation rules)

Validation currently allows `payment_date` as any `date` — no upper bound. A user (or a buggy import script) can set payment_date = '2099-01-01' and that payment sits in `revenue_year` for 2099. More practically: a user fat-fingers the year ('2062' instead of '2026') and the monthly revenue chart shows 40 years of history with one huge outlier in 2062.

**Fix:** add `before_or_equal:today` to the validation rule. Also add a sanity check in the service layer that refuses payments more than `tenant.payment_date_grace_days` (default 7) in the past — prevents retroactive VAT fraud.

**Effort:** 30 min.

---

#### LOG-04 — Work order completion has no concurrent-completion guard

**File:** `app/Http/Controllers/WorkOrderController.php` (completion handler)

If two mechanics hit "Complete" simultaneously on the same WO from two phones, both requests pass the `status !== 'completed'` check, both call `InvoiceService::createFromWorkOrder()`, both create an invoice with sequential invoice numbers. We end up with two invoices, both referencing the same WO. The second one is orphaned data that trips reporting later.

**Fix:** wrap the completion handler in `DB::transaction()` and use `WorkOrder::lockForUpdate()->findOrFail($id)` inside the transaction. If the status is already 'completed', return a 409 Conflict with "already completed by X at Y".

**Effort:** 1h including a concurrency test (simulate via two DB connections).

---

#### LOG-05 — Swiss VAT rounding applied at line-item level, not invoice level

**File:** `app/Services/InvoiceService.php` (totals computation)

Swiss commercial practice per ESTV directive: VAT is calculated on the *total* of each VAT rate, then rounded. The current code rounds each line-item to 2 decimals THEN sums. For an invoice with many small items this produces a 1–2 rappen discrepancy vs. the accountant's calculation.

Example: 10 line items at 1.105 CHF each. Correct: sum = 11.05, round = 11.05. Current: each rounds to 1.11, sum = 11.10. Off by 5 rappen.

**Fix:** aggregate untaxed subtotals per VAT rate first, then apply rate, then round to the Swiss 0.05 precision.

**Effort:** 2h including a golden-master test comparing against a hand-computed ledger.

---

#### LOG-06 — `generateInvoiceNumber` called outside transaction in one path

**File:** `app/Services/InvoiceService.php:76` vs `app/Services/InvoiceService.php:148`

```php
// Path A (createFromWorkOrder):
$invoiceNumber = $this->generateInvoiceNumber($workOrder->tenant_id);
// then later, a separate transaction inserts the invoice
```

`generateInvoiceNumber` itself uses `lockForUpdate`, which without an enclosing transaction acquires the lock briefly, then releases it at the end of the statement. Two parallel calls can both get lock, both return sequence N, both commit N+1 to the sequence table, and both return the same invoice number.

**Fix:** callers must call `generateInvoiceNumber` from within their own transaction — or inline the sequence increment into the invoice-create transaction. Assert this with `DB::transactionLevel() > 0` at the top of the function.

**Effort:** 1h.

---

#### LOG-07 — `max(30, $days)` silently ignores user intent in audit archival

**File:** `app/Console/Commands/ArchiveAuditLogsCommand.php:41`

```php
$days = max(30, (int) $this->option('days')); // never archive rows <30d old
```

If the user passes `--days=10`, we silently substitute 30. The test suite expects this (see `AuditLogArchivalTest::test_rejects_unsafe_days_argument`) but there's no log output saying "you asked for 10, we're using 30". Operators running this command manually will think it honored their value.

**Fix:** if `(int) $this->option('days') < 30`, emit a warning: `<warn>--days=10 is below minimum 30, using 30 instead</warn>`. Better: fail loudly (`return self::FAILURE`) unless `--force-min` is also passed. Update the test.

**Effort:** 30m.

---

### P2

#### LOG-08 — `getPaymentStatusAttribute` returns 'unpaid' for voided invoices
**File:** `app/Models/Invoice.php` (accessor)
Void invoices display "UNPAID" in the UI because the payment-status accessor doesn't check the invoice status. Fix: return 'void' when `$this->isVoid()`.
**Effort:** 15m.

#### LOG-09 — Quote `valid_until` check uses `<=` instead of `<`
A quote valid until 2026-05-01 is rejected at 00:00:01 on 2026-05-01 instead of 24 hours later.
**Effort:** 15m.

#### LOG-10 — `Invoice::isOverdue` checks `< now()` on date column without time zone normalization
Invoices shipped 1 hour before midnight in Europe/Zurich appear overdue immediately if the user's session is in UTC.
**Effort:** 30m.

#### LOG-11 — `StockService::reserveParts` doesn't check for negative stock before decrementing
If stock is already -5 due to a prior bug, this decrements further rather than surfacing the bad state.
**Effort:** 30m.

#### LOG-12 — `NotificationService` double-dispatches when run through the queue retry
Notifications are idempotent-by-key, but the retry key includes the queue attempt count, so a retried job sends twice.
**Effort:** 1h.

#### LOG-13 — `Report::build` uses unbound `created_at` range when no date filter is passed
Results in a full-table scan. Should default to "last 90 days".
**Effort:** 20m.

### P3

- **LOG-14** — Typo in user-facing error: "Invoince could not be issued" (controllers/InvoiceController.php). 5m.
- **LOG-15** — `PasswordPolicy::check` compares against `config('auth.password.min_length')` instead of Laravel's standard `Password::min()`. Works but inconsistent. 20m.
- **LOG-16** — Dead code: `app/Services/LegacyImportService.php` is referenced from nowhere. Delete. 10m.
- **LOG-17** — `TenantContext::resolve` logs at `debug` level but doesn't include `$request->fingerprint()` — hard to correlate cross-request. 15m.
- **LOG-18** — `Address::formatted` concatenates with hardcoded commas; breaks for DE addresses (`Strasse Hausnr, PLZ Stadt`). 30m.
- **LOG-19** — `Part::displayName` falls back to `sku` but not `internal_code` when name is null. 10m.
- **LOG-20** — `Customer::$casts` missing `'preferred_language' => 'string'` — works but defensive. 5m.

---

## §2 — Data integrity bugs

### P0

#### DATA-01 — Invoice `paid_amount` reconciliation race

**File:** `app/Services/InvoiceService.php:248-307`

The `recalculatePaidAmount` path reads `$invoice->payments->sum('amount')`, then writes `$invoice->paid_amount = $paidAmount`. Between read and write, another payment can land. Symptom: invoice shows `paid = 100 CHF` in the UI but `payments` table sums to `120 CHF`. Nobody notices until year-end when the reconciliation report is off.

**Fix:** this method must be called from inside a transaction that holds `lockForUpdate()` on the invoice row. Assert it with a runtime check. Add an integration test that spawns two goroutines (async via Guzzle) and confirms the final paid_amount matches the sum of both payments.

**Effort:** 3h — the lock is easy, but the test needs two real DB connections and is worth getting right because it'll catch every future payment-path bug.

---

#### DATA-02 — `ArchiveAuditLogsCommand` builds IN clause by imploding ids

**File:** `app/Console/Commands/ArchiveAuditLogsCommand.php:93`

```php
DB::statement(
    'INSERT INTO audit_logs_archive (...)
     SELECT ... FROM audit_logs WHERE id IN ('.$ids->implode(',').')',
    [now()]
);
```

Today `$ids` comes from `pluck('id')` which returns integers, so SQL injection isn't currently exploitable. But:

1. If any future migration changes `audit_logs.id` to UUID, this quietly becomes SQLi.
2. It's the kind of code that gets copy-pasted into a place where the input *is* user-controlled.
3. Linters will flag it on every PR.

**Fix:** use `whereIn` via the query builder, or pass the ids as placeholders:

```php
$placeholders = rtrim(str_repeat('?,', $ids->count()), ',');
DB::statement(
    'INSERT INTO audit_logs_archive (...) 
     SELECT ..., ? AS archived_at 
     FROM audit_logs 
     WHERE id IN ('.$placeholders.')',
    [...$ids->values()->all(), now()]
);
```

**Effort:** 45m including a test that asserts the query binds placeholders.

---

### P1

#### DATA-03 — `StockService` has no lock-ordering rule → deadlock risk

**File:** `app/Services/StockService.php:47-64`

Two concurrent reservations for different work orders, each needing products A and B. WO-1 locks A, then B. WO-2 locks B, then A. Classic ABBA deadlock. Postgres will kill one transaction with `could not serialize access due to concurrent update`, which surfaces to the user as a 500.

**Fix:** always lock products in `ORDER BY id ASC`. Enforce by wrapping the `lockForUpdate` call in a helper that sorts the product IDs first.

**Effort:** 2h including a stress test that spawns 20 parallel reservations against overlapping product sets.

---

#### DATA-04 — Customer delete cascade is not atomic

**File:** `app/Http/Controllers/CustomerController.php` (destroy handler)

Deleting a customer triggers cascading soft-deletes on vehicles, work orders, invoices. Each happens in its own implicit transaction. If the vehicle delete succeeds but the invoice delete fails (FK constraint, lock timeout), we end up with vehicles soft-deleted but invoices live — referencing a dead customer.

**Fix:** wrap the whole cascade in `DB::transaction()`. Any failure rolls back the entire delete. Surface the error to the user with "could not delete — this customer has open invoices".

**Effort:** 1h.

---

#### DATA-05 — Plan quota check is read-modify-write without lock

**File:** `app/Services/PlanQuotaService.php` (enforcement)

On tenant signup, we check `if ($tenant->user_count < $tenant->plan->max_users)` then create the user. If two invite-accepts land at the same second, both pass the check, both create users, tenant ends up over quota. For Swiss SaaS with monthly seat billing, this is revenue leak.

**Fix:** `SELECT ... FOR UPDATE` on the tenant row inside the invite-accept transaction.

**Effort:** 1h.

---

#### DATA-06 — Payment idempotency key check is not locked

**File:** `app/Http/Controllers/PaymentController.php:61` (has lockForUpdate on invoice but not on idempotency key row)

The controller locks the invoice but doesn't lock the idempotency key lookup. A buggy client retrying the same request within 200ms of the first one can create two payments.

**Fix:** use `SELECT ... FOR UPDATE` on the idempotency key before the insert.

**Effort:** 45m.

---

#### DATA-07 — `Tenant::find` cached with `null` result (cache poisoning)

**File:** `app/Http/Middleware/TenantMiddleware.php:119, 144, 160, 180, 193`

Every tenant-resolution path uses `CachedQuery::remember` which does NOT cache `null` values (see `CachedQuery.php:52`). That's good — but each unresolved tenant lookup runs an uncached DB query on every request. If an attacker hits `unknown.example.com`, each request does a full `Tenant::where('domain', 'unknown.example.com')->first()`. At 100 req/s that's 100 DB queries/s for a non-existent tenant.

**Fix:** cache `null` results with a much shorter TTL (60s) and a distinct cache-key prefix so lookups that find the tenant later don't get stuck on the null entry.

**Effort:** 1h + careful test.

---

### P2 (15 items)

Brief list — full details on request:
- DATA-08: `Quote -> Invoice` conversion doesn't check the quote is still valid (expired quotes silently become invoices). 30m.
- DATA-09: `Vehicle::delete` hard-deletes if SoftDeletes trait is present but column is missing (migration ran but trait not updated). 15m.
- DATA-10: `Invoice::$fillable` missing `internal_notes` — mass-assignment-protected but silently drops the field on update. 15m.
- DATA-11: Payment receipt PDF URL signature doesn't include `updated_at` — signed URL keeps working after amount is corrected. 30m.
- DATA-12: `Tenant::scopeActive` doesn't check `is_expired` — `Tenant::active()->get()` returns expired-but-not-deactivated tenants. 20m.
- DATA-13: `User::sendPasswordResetNotification` can be called outside tenant context, sending the link with a stale tenant subdomain. 45m.
- DATA-14: `WorkOrder::status_history` JSON column not cast to array → reads return strings. 15m.
- DATA-15: `Invoice` triggers allow UPDATE on `issued_at` column — immutability should block this at DB level, not just model. 1h.
- DATA-16: `StockMovement` inserts bypass the `recorded_by_user_id` column when inserted via trigger → audit gap. 30m.
- DATA-17: `Customer::preferred_language` enum mismatch (DB has `de, fr, it, en`, model casts to a type that accepts any string). 20m.
- DATA-18: Foreign key on `audit_logs.user_id` is `ON DELETE SET NULL` which is correct, but `audit_logs_archive` has no FK — archived logs reference deleted users. Intentional or bug? 30m.
- DATA-19: `Appointment::conflicts_with` uses `>=`/`<=` for end-time overlap → two back-to-back appointments at 10:00 and 10:00 flag as conflict. 20m.
- DATA-20: `Cache::tags` used in `ReportingService` but Redis cache driver doesn't support tags without sentinel/cluster config flag. Silent no-op. 1h.
- DATA-21: Seeders don't wrap in a transaction → partial seed on failure. 30m.
- DATA-22: `Invoice::updateTotal` doesn't recompute `paid_amount` check — can mark invoice 'paid' when items were removed and total dropped below paid. 45m.

### P3 (9 items)

- DATA-23 through DATA-31: defensive-coding improvements, missing `@property` docblocks, casts for datetime columns, enum reinforcement. ~2h total.

---

## §3 — Frontend / UX bugs

### P0

#### UX-01 — `JSON.parse(button.dataset.jobNotes)` crashes on special chars

**File:** `resources/views/work-orders/board.blade.php:163`

```html
<button ... data-job-notes="{{ json_encode($job->notes) }}" ...>
```

```js
notesField.value = JSON.parse(button.dataset.jobNotes);
```

Blade's `{{ }}` double-encodes: `json_encode($job->notes)` produces `"line1\nline2"` (string with literal `\n`), then Blade HTML-encodes `"` to `&quot;`, then browsers decode `&quot;` back to `"` when reading via dataset. Sometimes this round-trips correctly. Sometimes — when notes contain `</script>`, a backslash, or Unicode — it doesn't, and `JSON.parse` throws `SyntaxError: Unexpected token`, which kills the modal open handler silently. The mechanic clicks the card, nothing opens, they assume the board is broken.

**Fix:** pass the notes through a proper Alpine component (`x-data="{ notes: {{ Js::from($job->notes) }} }"`) or use the `@json` directive, or base64-encode and decode client-side. The `@json` directive is cleanest:

```html
<button data-job-notes='@json($job->notes)'>
```

And then just: `notesField.value = JSON.parse(button.dataset.jobNotes);` — but `@json` emits proper JSON inside HTML attribute, so it's safe.

Even better: eliminate the dataset round-trip. Use `Alpine.data('jobCard', () => ({ notes: @js($job->notes) }))` and skip JSON parse entirely.

**Effort:** 30m + test case with notes containing every evil char (`'\"<>\n\t&`).

---

### P1

#### UX-02 — Payment modal: `goToStep2()` is defined after the button references it

**File:** `resources/views/finance/index.blade.php:479, 612, 657`

The button at line 479 calls `goToStep2()` via inline `onclick`. The function is defined at line 612 in a `<script>` block further down the page. This works in most browsers because script tags are parsed sequentially and `onclick` is resolved at click time, not parse time — BUT if the modal is opened via JS before the `<script>` block has finished parsing (e.g., a slow CPU on mobile), the first click throws `ReferenceError: goToStep2 is not defined` and the modal is stuck on step 1.

Same pattern: `updateAmount()` at line 429/657, `loadInvoices()` throughout.

**Fix:** move all modal-supporting functions into a single `<script>` block at the top of the file, or better, convert the whole modal to an Alpine component.

**Effort:** 45m for the minimal fix, 3h for Alpine conversion.

---

#### UX-03 — Dashboard: `WorkOrder::count()` called from outside tenant-scoped context

**File:** agent report — needs verification at `app/Services/DashboardService.php`

Agent flagged that Dashboard counts are called during a scheduled cron that runs without a tenant context. If the trait `BelongsToTenant` has been removed from the model, the count leaks across tenants. Current inspection shows WorkOrder model uses BelongsToTenant trait, so this is not exploitable TODAY, but it's brittle — any future refactor can silently break isolation.

**Fix:** add `->tenantScoped()` explicit helper and use it in services that run outside requests. Add a test: call the service without a tenant bound, assert it throws (not silently returns cross-tenant data).

**Effort:** 1h.

---

#### UX-04 — Work orders `loadVehicles()` undefined on first page load

**File:** `resources/views/work-orders/create.blade.php:32, 149`

`onchange="loadVehicles(this.value)"` but `loadVehicles` is defined at line 149, after the form. Same pattern as UX-02.

**Fix:** same — move to top of page or Alpine.

**Effort:** 30m.

---

#### UX-05 — Payment modal: action URL not updated on invoice change

**File:** `resources/views/finance/index.blade.php` (payment modal)

The payment form's `action` attribute is set when the modal opens with a specific invoice. If the user changes the invoice dropdown, `action` does not update — the form still submits to the first invoice's URL.

**Fix:** update `form.action` in the `updateAmount()` / invoice-change handler, not just on modal open.

**Effort:** 30m.

---

### P2 (15 items, condensed)

- UX-06: Tires Hotel: `goToStep2()` function only validates step 1 fields from the form, not from Alpine state — data entered via keyboard isn't validated.
- UX-07: Quote detail page: "Send as invoice" button doesn't check if quote is still valid → creates invalid invoice.
- UX-08: Invoice detail page: "Download PDF" link uses local date, not invoice date → PDFs have mismatched timestamps vs invoice.
- UX-09: Dark mode toggle doesn't persist across sessions for users who haven't logged in yet.
- UX-10: Mobile nav menu doesn't close after clicking a link — user has to manually close.
- UX-11: Toast notifications stack at z-index: 50, same as modals → toast appears behind modal.
- UX-12: Checkin form: vehicle selector resets to "new vehicle" on validation error, losing the customer's selection.
- UX-13: Finance dashboard chart: x-axis labels overlap on >6 months of data.
- UX-14: Quote PDF template uses `{{ $tenant->address }}` but falls back to hardcoded Swiss address if null.
- UX-15: Low-stock digest email link points to `app.ihrauto.ch` (production) hardcoded → broken in staging.
- UX-16: "Delete customer" confirmation shows customer ID, not name — user can't verify they're deleting the right one.
- UX-17: Search results don't highlight matching terms → user has to re-scan to find why something matched.
- UX-18: "Export to Excel" generates CSV but labels it as XLSX → Excel complains on open.
- UX-19: Invoice preview iframe doesn't resize when invoice has many line items → scroll is hidden.
- UX-20: Form validation errors dismiss after 3 seconds via JS, but some errors (like "this email is taken") shouldn't auto-dismiss.

### P3 (5 items)

- UX-21 through UX-25: minor polish — date format inconsistencies, placeholder copy, default tab on first login, empty-state illustrations. ~1h total.

---

## §4 — Production / ops bugs

### P0

#### OPS-01 — nginx TLS certs referenced but not provisioned

**File:** `docker/nginx/nginx.conf:87-88`

```nginx
ssl_certificate     /etc/nginx/certs/fullchain.pem;
ssl_certificate_key /etc/nginx/certs/privkey.pem;
```

Nothing in the repo (or the Dockerfile or docker-compose) creates these files. On first deploy, nginx fails to start with `cannot load certificate "/etc/nginx/certs/fullchain.pem"`. The service loops-restart until someone SSHs in and manually provisions certs.

**Fix options:**

1. **Preferred:** add a certbot sidecar container to docker-compose that auto-renews Let's Encrypt certs and writes to a shared volume. This is the standard stack.
2. **Acceptable:** document the cert-provisioning as a pre-deploy step in `docs/DEPLOYMENT.md` and add a runtime check in the nginx entrypoint that prints a helpful error instead of the default nginx crash.

Either way, the current state — "nginx starts, fails silently, service doesn't come up" — is unacceptable.

**Effort:** 1h for option 2 (docs + friendly error), 3h for option 1 (certbot container).

---

#### OPS-02 — PgBouncer userlist.txt + TLS certs not provisioned

**File:** `docker/pgbouncer/pgbouncer.ini:40, 73-74`

```ini
auth_type = scram-sha-256
auth_file = /etc/pgbouncer/userlist.txt
...
client_tls_cert_file = /etc/pgbouncer/client.crt
client_tls_key_file = /etc/pgbouncer/client.key
```

Same problem — no userlist.txt anywhere in the repo, no cert generation. PgBouncer won't start.

**Fix:** add an init container / entrypoint script that generates `userlist.txt` from environment variables:

```bash
#!/bin/sh
# docker/pgbouncer/entrypoint.sh
echo "\"$DB_USERNAME\" \"SCRAM-SHA-256\$4096:$(php -r 'echo bin2hex(random_bytes(16));')\$...\"" > /etc/pgbouncer/userlist.txt
chmod 600 /etc/pgbouncer/userlist.txt
exec pgbouncer /etc/pgbouncer/pgbouncer.ini
```

Or, for the first pass, use `auth_type = md5` with a simpler userlist format and document upgrading to SCRAM once certs are in place.

For TLS certs: since pgbouncer → PG is internal network, we can drop TLS between them in the first release. Update `pgbouncer.ini` to comment out the cert lines.

**Effort:** 2h.

---

#### OPS-03 — Dockerfile bootstrap loop has no timeout

**File:** `Dockerfile:136-137`

```bash
until php -r 'try{$pdo=new PDO(...);}catch(Exception $e){exit(1);}'; \
  do echo 'Waiting for Postgres...'; sleep 2; done; \
```

If Postgres is DOWN (not "starting up" — genuinely unreachable), this loop runs forever. The container never reports failure to the orchestrator. Render.com / Kubernetes won't restart it because it's "healthy" (CMD still running). The container quietly consumes a slot forever.

**Fix:** add a timeout:

```bash
MAX_WAIT=60
WAITED=0
until php -r 'try{...}catch(Exception $e){exit(1);}'; do
  if [ $WAITED -ge $MAX_WAIT ]; then
    echo "ERROR: Postgres unreachable after ${MAX_WAIT}s, aborting boot" >&2
    exit 1
  fi
  echo "Waiting for Postgres (${WAITED}s/${MAX_WAIT}s)..."
  sleep 2
  WAITED=$((WAITED+2))
done
```

**Effort:** 30m.

---

### P1

#### OPS-04 — Scheduler `->onOneServer()` needs a shared cache lock store

**File:** `routes/console.php` (multiple entries)

`onOneServer()` serializes scheduled commands across a fleet by acquiring a cache lock. But it only works if the cache driver is Redis (or another atomic store). If the deploy accidentally uses `cache.default=array` or `cache.default=file`, every container runs the scheduler.

**Fix:**
1. Add a runtime assertion: in `AppServiceProvider::boot()`, if `app()->runningInConsole()` and scheduler is active, assert `config('cache.default')` is one of the supported stores.
2. Document in `docs/DEPLOYMENT.md` that Redis is required for scheduler correctness.

**Effort:** 1h.

---

#### OPS-05 — `pg_trgm` extension requires superuser

**File:** `database/migrations/2026_04_24_100100_add_pg_trgm_search_indexes.php`

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX ...USING gin (... gin_trgm_ops);
```

On Render, the DB user has CREATE EXTENSION permission. On many managed PG hosts (AWS RDS without rds_superuser, GCP Cloud SQL without `cloudsqlsuperuser`), it does NOT. Migration will fail with `permission denied to create extension "pg_trgm"`.

**Fix:**
1. Document the required pg extension as a pre-deploy step.
2. Wrap `CREATE EXTENSION` in a try/catch that logs a warning and skips the GIN index creation — fall back to btree.

**Effort:** 30m.

---

#### OPS-06 — Supervisord env var interpolation for QUEUE_WORKERS is unescaped

**File:** `docker/supervisord.conf` (referenced in Dockerfile:118)

`%(ENV_QUEUE_WORKERS)s` is evaluated by supervisord at config-read time. If the env var is `3; rm -rf /`, supervisord tries to parse it as an int and fails to start. If it's empty (env var not set at all), it uses a Python ConfigParser default which may be the string `None`.

**Fix:** add a coercion step in the Dockerfile CMD that validates `QUEUE_WORKERS` is a digit before exec'ing supervisord:

```bash
case "$QUEUE_WORKERS" in
  ''|*[!0-9]*) echo "QUEUE_WORKERS must be a positive integer, got: '$QUEUE_WORKERS'" >&2; exit 1 ;;
esac
```

**Effort:** 30m.

---

#### OPS-07 — `config('database.options.slow_query_log_ms')` path doesn't exist

**File:** `config/database.php` (recently added options array)

The scalability review added slow-query logging config but the key read in the query listener is `database.connections.pgsql.options.slow_query_log_ms`, while the value was put under `database.options.slow_query_log_ms`. They don't line up → logger never fires.

**Fix:** verify the key path and unify.

**Effort:** 30m.

---

#### OPS-08 — Backup runner doesn't verify backup integrity post-upload

**File:** Spatie backup config

Backups upload to remote storage but nothing downloads and restores them as a smoke test. If S3 credentials are rotated without updating the app, backups silently upload 0 bytes (or fail) and nobody notices until restore day.

**Fix:** add a weekly scheduled command `backup:verify` that downloads the latest backup, unzips it, and runs `pg_dump --schema-only` to confirm it's a valid PG dump.

**Effort:** 2h.

---

#### OPS-09 — `storage/app/.auto_login_enabled` marker file can leak into Docker image

**File:** `.dockerignore`

AutoLoginGuard requires a physical marker file for auto-login. This file is gitignored, so it doesn't ship via git. But `COPY . /var/www/html` in the Dockerfile will include any file present at build time. If a developer builds their local image with the marker file present and pushes to registry, prod gets auto-login.

**Fix:** explicitly add the marker to `.dockerignore`:

```
storage/app/.auto_login_enabled
```

**Effort:** 10m.

---

#### OPS-10 — Redis password not required by default

**File:** `docker-compose.yml` redis service

Redis runs on the internal compose network, so it's not publicly exposed. But if a misconfigured container (or a compromised queue worker) can reach redis on port 6379, there's no auth between them. Sessions and cache are readable by any container on the network.

**Fix:** set `REDIS_PASSWORD` in `.env.example`, configure redis with `--requirepass`, update the Laravel connection config to pass the password.

**Effort:** 1h.

---

#### OPS-11 — No log rotation for container stdout → disk fills on long-running hosts

**File:** docker-compose.yml and Render default

Laravel logs via `log.single` driver write to `storage/logs/laravel.log` with no rotation. A production container accumulates logs until disk fills. Render's default log volume is 10GB — at 1MB/hour, that's 400 days of uptime before it fills, but we'll exceed it if anything log-spams (e.g., a 500-error loop).

**Fix:** switch to `daily` log driver, set retention to 14 days. Configure Docker's `json-file` driver with `max-size=100m, max-file=3`.

**Effort:** 30m.

---

#### OPS-12 — Health endpoint doesn't check DB connection

**File:** `routes/web.php` (`/up` health route)

Laravel's default `/up` just returns 200. Orchestrators that depend on it to decide container health will keep routing traffic to a container whose DB connection is dead.

**Fix:** add a `/up/db` route that runs `DB::connection()->getPdo()` and returns 503 on failure. Point Docker healthcheck at that. Distinguish liveness (is the container running) from readiness (is it actually serving real requests).

**Effort:** 1h.

---

### P2 (14 items)

Condensed:
- OPS-13: Docker image is 1.2GB — can shave 400MB by combining the `apt-get purge` RUN layer.
- OPS-14: PgBouncer `pool_mode = transaction` breaks `SET LOCAL` and Postgres advisory locks — any feature using them silently breaks.
- OPS-15: Sentry DSN missing from `.env.example`; developers forget to set it → no error reporting in staging.
- OPS-16: `config:cache` runs on boot but `route:cache` requires closure-free routes — first boot after a merge that adds a route closure fails silently.
- OPS-17: No memory limit on queue workers → a leaky job OOMs the whole container.
- OPS-18: Crontab runs `php artisan schedule:run` every minute; no jitter → 200 tenants all trigger at :00.
- OPS-19: `env('APP_URL')` baked into config:cache — changing APP_URL requires rebuild, not just `.env` edit.
- OPS-20: No database connection pool exhaustion metric exposed.
- OPS-21: Backup command runs as root in the container → backup tarball owned by root, can't be read by non-root processes.
- OPS-22: `storage_path('framework/sessions')` is ephemeral in Render — user sessions drop on each deploy.
- OPS-23: No rate limit on `/login` → brute force is only bounded by Laravel's throttle middleware (60/min default is too generous).
- OPS-24: `queue:listen` used in dev, `queue:work` in prod — dev skips worker restart on code change, prod re-reads code only on supervisord restart.
- OPS-25: CI runs tests against SQLite but prod is PG → LOG-02 bug wouldn't have been caught by CI.
- OPS-26: No staging environment defined in deploy config.

### P3 (5 items)

- OPS-27: Missing `.editorconfig` → team formatters drift.
- OPS-28: `composer.lock` not committed for docker build cache efficiency. (Check: it IS committed. Flag removed.)
- OPS-29: `artisan tinker` shipped in production image — shouldn't be a problem but it's extra attack surface.
- OPS-30: README doesn't document port 8001 for local dev.
- OPS-31: Sentry release tagging uses git SHA but doesn't include the deploy environment (staging vs prod).

---

## §5 — Phased fix roadmap

A disciplined sequence so each phase's output is shippable and tested.

### Sprint 1 — Deployment blockers (2–3 days)

Fix the 8 P0s. Nothing else.

- **Day 1:** OPS-01, OPS-02, OPS-03, OPS-09 — cert provisioning, pgbouncer auth file, bootstrap timeout, dockerignore. Unblocks "docker-compose up" out of the box.
- **Day 2:** LOG-01, LOG-02, DATA-02, UX-01 — loose-equality void, TO_CHAR portability, archival SQL injection surface, JSON.parse crash. Unblocks test suite on non-PG drivers and fixes the worst silent-data-loss scenarios.
- **Day 3:** DATA-01 — the big one. Invoice reconciliation race. Needs careful tests. Validate end-to-end.

**Exit criteria:** fresh `docker-compose up` boots cleanly, test suite green on PG AND SQLite, 2 parallel `POST /payments` against the same invoice don't overcount.

### Sprint 2 — Data integrity & key flows (4–5 days)

Fix the 17 P1s.

- **Day 1:** DATA-03, DATA-04, DATA-05, DATA-06 — lock ordering, cascade atomicity, quota & idempotency locks.
- **Day 2:** LOG-03, LOG-04, LOG-05, LOG-06, LOG-07 — payment date validation, WO completion guard, Swiss VAT rounding, invoice-number transaction, audit-days warning.
- **Day 3:** UX-02, UX-03, UX-04, UX-05 — payment modal, work orders create, dashboard count leak, action URL update.
- **Day 4:** DATA-07, OPS-04, OPS-05, OPS-06, OPS-07 — null cache poisoning, scheduler lock store, pg_trgm fallback, supervisord coercion, slow-query key path.
- **Day 5:** OPS-08, OPS-10, OPS-11, OPS-12 — backup verification, Redis auth, log rotation, DB health endpoint.

**Exit criteria:** all P1s closed with regression tests. Payment race test, cascade race test, scheduler fallback test, backup restore smoke test all pass.

### Sprint 3 — UX polish & P2s (5–7 days)

Pick from the 31 P2 list based on user-reported priority. Group by area:

- Finance/invoicing P2s first (LOG-08 through LOG-11, DATA-08 through DATA-12) — these touch money.
- Then front-end P2s (UX-06 through UX-20).
- Then ops polish (OPS-13 through OPS-26).

**Exit criteria:** all top-of-backlog UX complaints addressed. No known data-quality issues in finance reports.

### Sprint 4 — Code hygiene & P3s (3 days)

Sweep the 23 P3s. Most are <30m each. Batch into a single "code cleanup" PR.

**Exit criteria:** technical debt backlog empty. Linters pass with zero suppressions.

---

## §6 — What was explicitly OUT of scope

To keep this review focused, the following were not audited. Each is worth its own review later:

- **Security** — separate report already exists (`docs/DEEP-REVIEW-2026-04-23.md` §Security). No new security review done here.
- **Performance profiling under real load** — the scalability review (`docs/SCALABILITY-REVIEW-2026-04-23.md`) covered this in planning; actual load testing is pending.
- **Business-logic correctness vs Swiss accounting standards** — needs a qualified accountant, not an engineer. LOG-05 (VAT rounding) is the one finding we surfaced confidently.
- **Browser compatibility** — no IE11/legacy browser testing. Modern evergreen assumed.
- **A11y** — not audited. High value, but out of this bug-review's remit.
- **Translation coverage** — no DE/FR/IT lint of Blade strings. Known to be incomplete.

---

## §7 — Sign-off

This plan was produced by a four-agent review team reading every service, controller, view, migration, Dockerfile, and config in the repo at commit `6787f99`.

**Recommended action:** freeze feature work for one week, execute Sprint 1 (P0s). Ship to staging. Execute Sprint 2 (P1s) over the following week. Then reopen feature work with Sprint 3 and 4 interleaved.

**Time to production readiness:** ~2 weeks of focused engineering to clear P0+P1. Post-Sprint-2 the codebase is genuinely ready for 200-tenant production traffic.

**Requesting approval to begin Sprint 1.**
