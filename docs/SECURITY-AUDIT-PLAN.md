# IHRAUTO CRM — Full Security & Quality Audit + Remediation Plan

**Date:** April 10, 2026
**Auditors:** Senior Security Engineer, Tenant Isolation Specialist, Business Logic Engineer, Frontend/UX Engineer, Database Engineer
**Scope:** Complete codebase — security, tenant isolation, business logic, frontend, database
**Status:** Findings consolidated — remediation plan ready

---

## EXECUTIVE SUMMARY

**Total findings: 58**
- **CRITICAL:** 5
- **HIGH:** 13
- **MEDIUM:** 24
- **LOW:** 16

**Production verdict:** NOT READY. Fix all CRITICAL and HIGH findings before deployment. The app has solid architectural foundations (tenancy, authz, audit logging) but has specific implementation gaps that allow cross-tenant data access, financial inconsistencies, and minor UX bugs.

**Good news:** No SQL injection. No mass assignment. CSRF is universally applied. Password hashing is correct. Invoice immutability works for ORM updates. Authorization is broadly applied.

---

## SPRINT A — CRITICAL FIXES (Deploy Blockers)

These MUST be fixed before any production traffic. All are single-file changes, ~15–30 minutes each.

### A.1 — Finance analytics leaks data across tenants
**File:** `app/Services/FinanceService.php:106`
**Severity:** CRITICAL — Cross-tenant data exposure
**Finding:** `getTopServices()` uses `DB::table('invoice_items')->join('invoices'...)` without a tenant filter. Global scope does NOT apply to raw DB queries.
**Attack:** Any authenticated user sees every tenant's top services (revenue, line items, product names).
**Fix:**
```php
return DB::table('invoice_items')
    ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
    ->where('invoices.tenant_id', tenant_id())  // ADD THIS
    ->where('invoices.status', '!=', 'void')
    ...
```
Apply the same fix to `getTechnicianProductivity()` which joins `work_orders` + `users` + `invoices` (line 137).
**Verification:** Create 2 tenants, login as tenant A, call analytics, verify no tenant B data returned.

### A.2 — Invite token lookup skips tenant check
**File:** `app/Http/Controllers/Auth/InviteController.php:17, 39`
**Severity:** CRITICAL — Authentication bypass
**Finding:** `User::withoutGlobalScopes()->where('invite_token', $token)` matches across all tenants. If attacker learns a token, they can accept it from any tenant context.
**Fix:** Remove `withoutGlobalScopes()`. If unauthenticated acceptance is needed, keep the bypass but add `hash_equals()` for timing-safe comparison and log the operation.
**Additional:** Use `hash_equals($user->invite_token, $token)` instead of `==` to prevent timing attacks.

### A.3 — Google SSO finds users from wrong tenant
**File:** `app/Http/Controllers/Auth/SocialAuthController.php:38`
**Severity:** CRITICAL — Account takeover
**Finding:** `User::withoutGlobalScopes()->where('email', $googleUser->getEmail())->first()` returns the first matching user from ANY tenant. If two tenants have users with the same Google email, login as the wrong tenant's user.
**Attack:** Attacker in tenant B with same Google account as user in tenant A gets logged in as tenant A's user.
**Fix:** Use tenant context (subdomain/domain-resolved tenant before auth) to filter the lookup, OR require the user to select their tenant before SSO.

### A.4 — Stock can go negative
**File:** `app/Services/InvoiceService.php:316` (`processStockDeductions`)
**Severity:** CRITICAL — Data integrity
**Finding:** `$product->decrement('stock_quantity', $qty)` runs without checking available stock. Work orders for parts you don't have result in negative inventory.
**Fix:**
```php
if ($product->stock_quantity < $qty) {
    throw new \App\Exceptions\InsufficientStockException(
        "Insufficient stock for {$product->name}: have {$product->stock_quantity}, need {$qty}"
    );
}
$product->decrement('stock_quantity', $qty);
```
Create `InsufficientStockException` extending `\Exception`. Surface the error to the user clearly.

### A.5 — Payment idempotency silently skipped when key is null
**File:** `app/Http/Controllers/PaymentController.php:38-51`
**Severity:** CRITICAL — Duplicate charges
**Finding:** `if ($idempotencyKey) { check duplicate }` — if no key, duplicates are allowed. Browser back-button + resubmit = double payment.
**Fix:** Generate a deterministic fallback key when none supplied:
```php
$idempotencyKey = $validated['idempotency_key']
    ?? $validated['transaction_reference']
    ?? hash('sha256', "{$invoice->id}|{$validated['amount']}|{$validated['payment_date']}|" . auth()->id());
```
Store it. Reject duplicates.

---

## SPRINT B — HIGH PRIORITY (Fix this week)

### B.1 — TenantContext fallback allows stale tenant
**File:** `app/Support/TenantContext.php:20`
**Finding:** `return $this->current()?->id ?? auth()->user()?->tenant_id;` — silently falls back to user's tenant_id even when context was never set. Can mask middleware bugs.
**Fix:** Remove the fallback. If `current()` is null, return null. Middleware must set context explicitly.

### B.2 — ManagementController::downloadBackup leaks password hashes + tokens
**File:** `app/Http/Controllers/ManagementController.php:301-322`
**Finding:** Exports `$record->toJson()` for all models including `User` (password hashes) and `TenantApiToken` (hashed tokens).
**Fix:** Whitelist safe fields per model:
```php
$safeFields = [
    User::class => ['id', 'name', 'email', 'role', 'created_at'],
    Customer::class => ['id', 'name', 'email', 'phone', 'address'],
    // etc
];
echo json_encode($record->only($safeFields[$modelClass]));
```

### B.3 — AUTO_LOGIN_ENABLED bypasses authentication entirely
**File:** `app/Http/Middleware/TenantMiddleware.php:65-73`
**Finding:** When local + flag set, unauthenticated requests auto-login as first tenant user. If config accidentally flipped in production, any visitor becomes an admin.
**Fix:** Require BOTH local env AND a file-based flag (e.g., `storage/app/.auto_login_enabled`). Config-based flag is too easy to misconfigure.

### B.4 — Customer update missing authorize()
**File:** `app/Http/Controllers/CustomerController.php:62-68`
**Finding:** `public function update(UpdateCustomerRequest $request, Customer $customer)` — no `$this->authorize('update', $customer)`. Tenant scope protects via route-model binding, but defense in depth is missing.
**Fix:** Add `$this->authorize('update', $customer);` at the top. Same audit for: `destroy()`, `VehicleController::update/destroy`, `MechanicsController`.

### B.5 — Invoice immutability bypassed by bulk updates
**File:** `app/Models/Invoice.php:88-104` (boot method)
**Finding:** `$invoice->update(...)` is blocked, but `Invoice::where('id', $id)->update(...)` bypasses model events entirely.
**Fix:** Add a `BEFORE UPDATE` trigger via migration (PostgreSQL only):
```sql
CREATE OR REPLACE FUNCTION prevent_issued_invoice_update()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.locked_at IS NOT NULL AND NEW.status != 'void' THEN
        RAISE EXCEPTION 'Cannot modify issued invoice %', OLD.id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER invoice_immutability BEFORE UPDATE ON invoices
FOR EACH ROW EXECUTE FUNCTION prevent_issued_invoice_update();
```

### B.6 — Stock deduction race condition
**File:** `app/Services/InvoiceService.php:294-301`
**Finding:** Idempotency check uses `StockMovement::where(...)->exists()` without row locking. Two concurrent completes for the same work order both pass the check.
**Fix:** Wrap in transaction with `lockForUpdate` on the work_orders row, OR add a unique constraint `(reference_type, reference_id, type)` on `stock_movements` so the second insert fails.

### B.7 — WorkOrderPhotoController::destroy missing tenant check
**File:** `app/Http/Controllers/WorkOrderPhotoController.php:59-73`
**Finding:** Delete endpoint doesn't verify photo belongs to current tenant. Route-model binding helps but add explicit check.
**Fix:** Add `abort_unless($photo->tenant_id === tenant_id(), 403);` or create `WorkOrderPhotoPolicy`.

### B.8 — AuditLog missing BelongsToTenant trait
**File:** `app/Models/AuditLog.php`
**Finding:** `tenant_id` is in fillable but no `BelongsToTenant` trait. Queries don't auto-filter. Admins could see other tenants' audit logs if they ran an unscoped query.
**Fix:** `use App\Traits\BelongsToTenant;` in the class. Ensure legacy records have `tenant_id` populated (migration 100100 already backfills).

### B.9 — Undefined JavaScript functions in blade views
**Files:**
- `resources/views/work-orders/show.blade.php:39` — `submitCompletion()`
- `resources/views/checkin.blade.php:30` — `hideSuccessNotification()`
**Finding:** `onclick` handlers call functions that are either undefined or defined too late to be hoisted.
**Fix:** Move function definitions to a `<script>` block BEFORE the buttons that reference them, OR use Alpine.js `x-on:click` with inline handlers.

### B.10 — console.error left in production code
**File:** `resources/views/work-orders/create.blade.php:174`
**Fix:** Remove or gate with `@if(app()->environment('local'))`.

### B.11 — Password reset rate limit too loose
**File:** `routes/auth.php:27-39`
**Finding:** 5 requests/minute allows brute force.
**Fix:** `->middleware('throttle:3,5')` (3 per 5 minutes per IP) for both `password.email` and `password.store`.

### B.12 — Invite endpoint unlimited
**File:** `routes/web.php:120`
**Finding:** No rate limit on `/mechanics/{mechanic}/invite`. Can be abused to spam emails.
**Fix:** `->middleware('throttle:5,10')`.

### B.13 — Missing custom error pages (404, 403, 500, 503)
**File:** `resources/views/errors/` only has `tenant-*` pages
**Fix:** Create branded error pages. Laravel auto-detects `errors/404.blade.php` etc.

---

## SPRINT C — MEDIUM PRIORITY (Fix this sprint)

### Database integrity
- **C.1** `appointments.tenant_id` has no FK constraint → create migration
- **C.2** Missing FK indexes on `appointments.customer_id`, `appointments.vehicle_id`, `quote_items.quote_id`
- **C.3** Missing composite index `[tenant_id, start_time]` on appointments (slow calendar queries)
- **C.4** `invoices.invoice_number` is globally unique — verify intended; probably should be `unique(tenant_id, invoice_number)`
- **C.5** Soft-delete cascade mismatch — child tables have `cascadeOnDelete` but parents use SoftDeletes. Child records orphan when parent is soft-deleted.

### Business logic
- **C.6** Invoice balance rounding — `total - paid_amount` can be 0.01 CHF when should be 0. Add `round(..., 2)` in accessor.
- **C.7** Work order completion has no `started_at < completed_at` validation
- **C.8** Appointment conflict detection is not timezone-aware (assumes app default)
- **C.9** Plan limit checks use 60s cache — can exceed limit during the window
- **C.10** License plate normalization inconsistent between `CheckinService` and `StoreCheckinRequest`
- **C.11** Tire storage location assignment race condition (no DB-level unique constraint)
- **C.12** Phone-based dedup returns null for null phones — allows duplicate phoneless customers
- **C.13** Customer merge tool doesn't exist

### Frontend / accessibility
- **C.14** Missing `aria-describedby` on form errors
- **C.15** Icon-only buttons missing `aria-label` (close notification, back buttons)
- **C.16** Color-only status indicators on work order board (needs text alternative)
- **C.17** No loading state on submit buttons (double-submit risk)
- **C.18** Dead links `href="#"` in welcome, register, board views
- **C.19** Touch targets below 44px on some buttons (`work-orders/show.blade.php:202`)
- **C.20** CHF formatting doesn't use Swiss convention (`1'234.50`)
- **C.21** Tables don't collapse to card view on mobile (horizontal scroll only)
- **C.22** Hardcoded English strings (dates, month names in JS)

### Security
- **C.23** HSTS header missing — add via middleware in production
- **C.24** Dashboard system check exposes `information_schema` query (enumeration)

---

## SPRINT D — LOW PRIORITY (Code quality, future polish)

- **D.1** Vehicle year allows `date('Y') + 1` — tighten to current year
- **D.2** Invoice items allow decimal quantities — should be positive integer
- **D.3** Soft-deleted invoices appear in finance totals
- **D.4** Inline `style="width: 260px"` instead of Tailwind classes
- **D.5** Multiple button style variants across pages
- **D.6** Missing focus rings on some icon buttons
- **D.7** Hardcoded date format `date('M j, Y')` — not localized
- **D.8** Audit log exception handling silent
- **D.9** `checkins.service_bay` indexed but `tires.storage_location` not full-text searched
- **D.10** Inconsistent required field markers (`*` not always present)
- **D.11** Invoice `paid_amount` is denormalized (can drift from sum of payments)
- **D.12** Payment table is soft-deletable (probably should be immutable)
- **D.13** Quote items lack index
- **D.14** Many hardcoded month names in JS should come from i18n
- **D.15** Modal inline implementations don't use `x-components/modal`
- **D.16** Error messages for FK constraint violations not user-friendly

---

## EXECUTION STRATEGY

### Phase 1: Triage (Day 1 — today)
1. Review this document together
2. Confirm the 5 CRITICAL findings with tests/reproductions
3. Decide scope: fix all CRITICAL + HIGH before next deploy, or ship a partial?

### Phase 2: Critical fixes (Days 1–2)
- Sprint A (5 items) — one branch per fix or one branch for Sprint A, each step tested individually
- Each fix followed by `php artisan test` to verify no regressions
- Add a regression test for each

### Phase 3: High fixes (Days 2–4)
- Sprint B (13 items) — grouped logically:
  - B.1–B.4, B.7, B.8 together (tenant/auth hardening)
  - B.5, B.6 together (financial integrity)
  - B.9–B.10 together (frontend bugs)
  - B.11–B.12 together (rate limits)
  - B.13 standalone (error pages)

### Phase 4: Medium fixes (Week 2)
- Sprint C grouped by area (db / biz logic / frontend / security)

### Phase 5: Low priority / polish (Week 3+)
- Sprint D — quality-of-life improvements

### Verification for each fix
1. Unit/feature test added
2. `php artisan test` — all 244+ tests pass
3. Manual QA of the affected flow
4. Code review
5. Commit with clear message

---

## TRACKING

| Sprint | Items | Priority | Est. effort | Status |
|--------|-------|----------|-------------|--------|
| A | 5 | CRITICAL | 4–6 hours | Pending |
| B | 13 | HIGH | 2 days | Pending |
| C | 24 | MEDIUM | 1 week | Pending |
| D | 16 | LOW | Backlog | Pending |

---

## NOTES

- This audit supersedes the earlier `FULL-AUDIT-REPORT.md` and `IMPLEMENTATION-PLAN.md` for security/quality concerns. Those documents remain valid for architectural and sprint-level planning.
- Test count at audit start: 244 passing.
- All findings have file:line references for direct navigation.
- Each sprint should run tests before and after.
