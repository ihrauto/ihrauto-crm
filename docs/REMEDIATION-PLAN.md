# IHRAUTO CRM — Detailed Remediation Execution Plan

**Created:** April 10, 2026
**Based on:** `docs/SECURITY-AUDIT-PLAN.md` (58 findings)
**Execution method:** One step at a time, test after each, commit after each sprint

---

## How this plan works

- Each step is atomic: one concern, one file (mostly), one test
- Every step has: **What / Where / Code / Test / Verify**
- Sprints are grouped by risk and theme
- Stop after each step to run tests — no batching
- Each sprint ends with a commit

**Test budget:** Current baseline is 244 tests passing. After this plan, target is ~280+.

---

## SPRINT A — CRITICAL FIXES (Deploy Blockers)

**Goal:** Close the 5 critical vulnerabilities. Each is a small targeted change.
**Duration estimate:** 4–6 hours
**Status criterion:** All 5 fixes merged + new regression tests passing.

---

### STEP A.1 — Fix cross-tenant leak in FinanceService analytics

**File:** `app/Services/FinanceService.php`

**What:** Two analytics methods use raw `DB::table()` joins that bypass the tenant global scope.

**Lines affected:**
- `getTopServices()` — currently line ~106
- `getTechnicianProductivity()` — currently line ~130

**Code change:**

```php
// BEFORE:
return DB::table('invoice_items')
    ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
    ->where('invoices.status', '!=', 'void')
    ->selectRaw('invoice_items.description as name, SUM(invoice_items.total) as revenue, COUNT(*) as count')
    ...

// AFTER:
return DB::table('invoice_items')
    ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
    ->where('invoices.tenant_id', tenant_id())  // <-- ADDED
    ->where('invoices.status', '!=', 'void')
    ->selectRaw('invoice_items.description as name, SUM(invoice_items.total) as revenue, COUNT(*) as count')
    ...
```

Apply the same `->where('tenant_id', tenant_id())` to the base table in `getTechnicianProductivity()` (filter on `work_orders.tenant_id` and `invoices.tenant_id`).

**New test:** `tests/Feature/FinanceServiceTenantIsolationTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Services\FinanceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceServiceTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function top_services_does_not_leak_across_tenants(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        // Tenant A: 1 invoice with "Oil Change" totaling 100
        $invoiceA = Invoice::factory()->for($tenantA)->create(['status' => 'paid']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoiceA->id,
            'description' => 'Oil Change',
            'total' => 100,
        ]);

        // Tenant B: 1 invoice with "Tire Rotation" totaling 500
        $invoiceB = Invoice::factory()->for($tenantB)->create(['status' => 'paid']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoiceB->id,
            'description' => 'Tire Rotation',
            'total' => 500,
        ]);

        // Act as Tenant A
        tenant()->set($tenantA);

        $service = new FinanceService();
        $result = $service->getTopServices();

        // Should only see Tenant A's service
        $names = collect($result)->pluck('name')->toArray();
        $this->assertContains('Oil Change', $names);
        $this->assertNotContains('Tire Rotation', $names);
    }

    /** @test */
    public function technician_productivity_does_not_leak_across_tenants(): void
    {
        // similar setup with two tenants' work orders
        // assert only current tenant's technicians appear
    }
}
```

**Verify:**
1. `php artisan test --filter=FinanceServiceTenantIsolationTest` — new tests pass
2. `php artisan test` — all 244+ tests pass
3. Manual: login as two different tenants, load finance analytics, confirm no cross-tenant data

**Commit message:** `fix(finance): add tenant_id filter to raw analytics queries (CRITICAL)`

---

### STEP A.2 — Fix invite token cross-tenant lookup + timing attack

**File:** `app/Http/Controllers/Auth/InviteController.php`

**What:** Two locations (lines 17, 39) use `withoutGlobalScopes()` to look up invite tokens without checking tenant context, AND use `==` comparison which is vulnerable to timing attacks.

**Code change:**

```php
// BEFORE (line 17):
$user = User::withoutGlobalScopes()
    ->where('invite_token', $token)
    ->where('invite_expires_at', '>', now())
    ->first();

// AFTER:
// Scope-bypass is required here because user is not authenticated yet.
// But add tenant resolution from the current request (subdomain/domain).
$user = User::withoutGlobalScopes()
    ->where('invite_token', '!=', null)  // prefilter, avoids timing on null tokens
    ->where('invite_expires_at', '>', now())
    ->get()
    ->first(fn ($candidate) => hash_equals((string) $candidate->invite_token, (string) $token));

if (! $user) {
    abort(404, 'Invalid or expired invite.');
}
```

This uses `hash_equals()` for timing-safe comparison. We still scan multiple rows but only by primary key, and only rows with unexpired tokens (usually <50 at any time).

**Alternative (preferred if performance matters):** Hash the token like API tokens:

```php
// In migration: change invite_token column to store SHA256 hash
// In InviteController:
$tokenHash = hash('sha256', $token);
$user = User::withoutGlobalScopes()
    ->where('invite_token', $tokenHash)
    ->where('invite_expires_at', '>', now())
    ->first();
```

For this plan, we'll use the simpler `hash_equals()` approach. Separate migration later can add hashing.

**Apply same fix to line 39 (the `store()` / `accept()` method).**

**New test:** `tests/Feature/InviteTokenSecurityTest.php`

```php
/** @test */
public function invalid_invite_token_returns_404(): void
{
    $response = $this->get(route('invite.show', ['token' => 'invalid-token']));
    $response->assertNotFound();
}

/** @test */
public function expired_invite_token_rejected(): void
{
    $user = User::factory()->create([
        'invite_token' => 'valid-token',
        'invite_expires_at' => now()->subDay(),
    ]);

    $response = $this->get(route('invite.show', ['token' => 'valid-token']));
    $response->assertNotFound();
}

/** @test */
public function valid_invite_token_shows_form(): void
{
    $user = User::factory()->create([
        'invite_token' => 'valid-token',
        'invite_expires_at' => now()->addDay(),
        'is_active' => false,
    ]);

    $response = $this->get(route('invite.show', ['token' => 'valid-token']));
    $response->assertOk();
}
```

**Verify:**
1. New tests pass
2. `php artisan test` — all green
3. Manual: click an invite link from email, verify still works

**Commit message:** `fix(auth): use hash_equals for invite token comparison (CRITICAL)`

---

### STEP A.3 — Fix Google SSO cross-tenant user lookup

**File:** `app/Http/Controllers/Auth/SocialAuthController.php`

**What:** `User::withoutGlobalScopes()->where('email', $googleUser->getEmail())->first()` returns the FIRST matching user across all tenants. Multiple tenants can have users with the same Google email.

**Code change:**

Step 1: Resolve the tenant from the request BEFORE looking up the user.

```php
public function callback()
{
    try {
        $googleUser = Socialite::driver('google')->user();
    } catch (\Exception $e) {
        return redirect()->route('login')->with('error', 'Google authentication failed.');
    }

    // RESOLVE TENANT FROM REQUEST CONTEXT (subdomain, domain, or session)
    $tenant = app(\App\Support\TenantContext::class)->current()
        ?? $this->resolveTenantFromRequest(request());

    if (! $tenant) {
        return redirect()->route('login')
            ->with('error', 'Unable to determine company. Please access via your company URL.');
    }

    // Scope the lookup to the resolved tenant
    $existingUser = User::withoutGlobalScopes()
        ->where('email', $googleUser->getEmail())
        ->where('tenant_id', $tenant->id)  // <-- CRITICAL
        ->first();

    if ($existingUser) {
        Auth::login($existingUser, true);
        return redirect()->intended(route('dashboard'));
    }

    // New user flow — redirect to company creation
    return redirect()->route('register.company', [
        'email' => $googleUser->getEmail(),
        'name' => $googleUser->getName(),
    ]);
}

protected function resolveTenantFromRequest($request): ?\App\Models\Tenant
{
    // Reuse TenantMiddleware's resolution chain if possible
    // For now, check subdomain
    $host = $request->getHost();
    return \App\Models\Tenant::where('subdomain', explode('.', $host)[0])->first()
        ?? \App\Models\Tenant::where('domain', $host)->first();
}
```

**New test:** `tests/Feature/SocialAuthTenantIsolationTest.php`

```php
/** @test */
public function google_sso_does_not_log_in_user_from_different_tenant(): void
{
    $tenantA = Tenant::factory()->create(['subdomain' => 'companya']);
    $tenantB = Tenant::factory()->create(['subdomain' => 'companyb']);

    $userA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'email' => 'john@gmail.com',
    ]);
    $userB = User::factory()->create([
        'tenant_id' => $tenantB->id,
        'email' => 'john@gmail.com',  // Same email, different tenant
    ]);

    // Mock Google auth for tenant A context
    // Assert that when accessing via companya subdomain, userA is logged in (not userB)

    // This test requires mocking Socialite + request host header
}
```

**Verify:**
1. New test passes
2. `php artisan test` — all green
3. Manual: create two tenants with same Google email, verify SSO only logs into correct tenant

**Commit message:** `fix(auth): scope Google SSO lookup to current tenant (CRITICAL)`

---

### STEP A.4 — Prevent negative stock deductions

**Files:**
- `app/Services/InvoiceService.php`
- New: `app/Exceptions/InsufficientStockException.php`

**What:** `$product->decrement('stock_quantity', $qty)` runs unconditionally. If stock is insufficient, it goes negative.

**Code change:**

Step 1: Create the exception class.

```php
<?php
// app/Exceptions/InsufficientStockException.php
namespace App\Exceptions;

class InsufficientStockException extends \Exception
{
    public function __construct(
        public readonly string $productName,
        public readonly float $available,
        public readonly float $required,
    ) {
        parent::__construct(
            "Insufficient stock for {$productName}. Available: {$available}, Required: {$required}"
        );
    }
}
```

Step 2: Update `InvoiceService::processStockDeductions()`.

```php
public function processStockDeductions(WorkOrder $workOrder): void
{
    // Idempotency check (existing)
    $alreadyDeducted = \App\Models\StockMovement::where('reference_type', WorkOrder::class)
        ->where('reference_id', $workOrder->id)
        ->where('type', 'sale')
        ->lockForUpdate()   // <-- ADDED (fix race condition B.6 too)
        ->exists();

    if ($alreadyDeducted) {
        return;
    }

    $partsUsed = $workOrder->parts_used ?? [];

    // VALIDATE BEFORE MUTATING (added)
    foreach ($partsUsed as $part) {
        if (empty($part['product_id']) || empty($part['qty'])) {
            continue;
        }

        $product = \App\Models\Product::lockForUpdate()->find($part['product_id']);
        if (! $product) {
            continue;
        }

        $qty = (float) $part['qty'];
        if ($product->stock_quantity < $qty) {
            throw new \App\Exceptions\InsufficientStockException(
                $product->name,
                (float) $product->stock_quantity,
                $qty
            );
        }
    }

    // APPLY DEDUCTIONS (existing loop — now safe)
    foreach ($partsUsed as $part) {
        // ... existing decrement + StockMovement::create logic
    }
}
```

Step 3: Update `WorkOrderController::handleCompletion()` to catch the exception and surface a user-friendly error:

```php
protected function handleCompletion(WorkOrder $workOrder)
{
    try {
        $this->workOrderService->completeWorkOrder($workOrder);
        // ... rest
    } catch (\App\Exceptions\InsufficientStockException $e) {
        return back()->with('error', $e->getMessage());
    } catch (\Exception $e) {
        return back()->with('error', $e->getMessage());
    }
}
```

**New test:** Add to `tests/Unit/Services/InvoiceServiceTest.php` (already exists):

```php
/** @test */
public function it_rejects_stock_deduction_when_insufficient_inventory(): void
{
    $product = Product::factory()->create(['stock_quantity' => 5]);
    $workOrder = WorkOrder::factory()->create([
        'parts_used' => [[
            'product_id' => $product->id,
            'name' => $product->name,
            'qty' => 10,  // more than available
            'price' => 20,
        ]],
    ]);

    $this->expectException(\App\Exceptions\InsufficientStockException::class);
    $this->invoiceService->processStockDeductions($workOrder);

    // Verify stock was NOT decremented
    $product->refresh();
    $this->assertEquals(5, $product->stock_quantity);
}
```

**Verify:**
1. New test passes
2. Existing `it_processes_stock_deductions_for_parts` still passes
3. `php artisan test` — all green
4. Manual: try completing a work order with a part where qty > stock — should see error and stock unchanged

**Commit message:** `fix(stock): prevent negative inventory + add row-level lock (CRITICAL)`

---

### STEP A.5 — Close payment idempotency gap

**File:** `app/Http/Controllers/PaymentController.php`

**What:** When neither `idempotency_key` nor `transaction_reference` is supplied, duplicates aren't blocked.

**Code change:**

```php
// BEFORE (around line 38):
$idempotencyKey = $validated['idempotency_key'] ?? $validated['transaction_reference'] ?? null;

if ($idempotencyKey) {
    $existingPayment = Payment::where('idempotency_key', $idempotencyKey)->first();
    if ($existingPayment) {
        return redirect()->route('...')->with('info', 'Payment already processed.');
    }
}

// AFTER:
// Always generate a deterministic key when client doesn't supply one.
// This uses invoice_id + amount + date + user to prevent accidental double-submit.
$idempotencyKey = $validated['idempotency_key']
    ?? $validated['transaction_reference']
    ?? hash('sha256', implode('|', [
        $invoice->id,
        (string) $validated['amount'],
        $validated['payment_date'] ?? now()->toDateString(),
        (string) auth()->id(),
    ]));

$existingPayment = Payment::where('idempotency_key', $idempotencyKey)->first();
if ($existingPayment) {
    return redirect()
        ->route('finance.index', ['tab' => 'paid'])
        ->with('info', 'Payment already recorded.');
}
```

**New test:** Add to `tests/Feature/PaymentFlowTest.php`:

```php
/** @test */
public function duplicate_payment_without_idempotency_key_is_blocked(): void
{
    $invoice = Invoice::factory()->issued()->create(['total' => 100]);

    // First submission
    $this->actingAs($this->user)->post(route('payments.store'), [
        'invoice_id' => $invoice->id,
        'amount' => 100,
        'method' => 'cash',
        'payment_date' => now()->toDateString(),
    ]);

    // Second submission (simulating browser back + resubmit)
    $this->actingAs($this->user)->post(route('payments.store'), [
        'invoice_id' => $invoice->id,
        'amount' => 100,
        'method' => 'cash',
        'payment_date' => now()->toDateString(),
    ]);

    // Only one payment should exist
    $this->assertEquals(1, Payment::where('invoice_id', $invoice->id)->count());
}
```

**Verify:**
1. New test passes
2. Existing payment tests still pass
3. `php artisan test` — all green
4. Manual: submit a payment, press browser back, resubmit — only one payment recorded

**Commit message:** `fix(payments): generate fallback idempotency key to prevent duplicates (CRITICAL)`

---

### Sprint A sign-off checklist

- [ ] A.1 — FinanceService tenant isolation fix + test
- [ ] A.2 — Invite token hash_equals + test
- [ ] A.3 — Google SSO tenant scoping + test
- [ ] A.4 — Stock deduction pre-check + lockForUpdate + test
- [ ] A.5 — Payment idempotency fallback key + test
- [ ] All 244+ tests pass
- [ ] Commit each fix separately or one "Sprint A" commit

---

## SPRINT B — HIGH PRIORITY (Fix this week)

**Goal:** Close remaining security gaps and add defense-in-depth.
**Duration estimate:** 2–3 days
**Dependencies:** Sprint A complete

---

### STEP B.1 — Remove TenantContext fallback

**File:** `app/Support/TenantContext.php`

**Code change:**
```php
// BEFORE:
public function id(): ?int
{
    return $this->current()?->id ?? auth()->user()?->tenant_id;
}

// AFTER:
public function id(): ?int
{
    return $this->current()?->id;
}
```

**Risk:** Anything that relies on the fallback will break. Need to verify TenantMiddleware runs on all tenant-scoped routes.

**Test before change:**
```bash
php artisan test
# All should pass — no code should depend on the fallback
```

**Test after change:**
```bash
php artisan test
# If anything breaks, it indicates a middleware ordering bug to fix
```

**Verify:** All tests pass. Manual: login, navigate all main pages, confirm tenant context always resolves.

---

### STEP B.2 — Sanitize backup export

**File:** `app/Http/Controllers/ManagementController.php:301-322`

**Code change:**

```php
private const BACKUP_SAFE_FIELDS = [
    \App\Models\Customer::class => ['id', 'name', 'email', 'phone', 'address', 'created_at'],
    \App\Models\Vehicle::class => ['id', 'customer_id', 'license_plate', 'make', 'model', 'year', 'created_at'],
    \App\Models\Checkin::class => ['id', 'customer_id', 'vehicle_id', 'service_type', 'status', 'checkin_time', 'checkout_time'],
    \App\Models\WorkOrder::class => ['id', 'checkin_id', 'customer_id', 'vehicle_id', 'status', 'created_at', 'completed_at'],
    \App\Models\Invoice::class => ['id', 'invoice_number', 'customer_id', 'total', 'status', 'issue_date', 'paid_amount'],
    \App\Models\Payment::class => ['id', 'invoice_id', 'amount', 'method', 'payment_date'],
    \App\Models\Product::class => ['id', 'name', 'sku', 'price', 'stock_quantity'],
    \App\Models\Service::class => ['id', 'name', 'code', 'price', 'is_active'],
    // Explicitly EXCLUDE: User, TenantApiToken, AuditLog (contain sensitive data)
];

public function downloadBackup()
{
    Gate::authorize('perform-admin-actions');

    $filename = 'ihrauto-backup-' . now()->format('Y-m-d-His') . '.json';

    return response()->streamDownload(function () {
        echo '{';
        $first = true;

        foreach (self::BACKUP_SAFE_FIELDS as $modelClass => $fields) {
            if (! $first) echo ',';
            $first = false;

            $key = class_basename($modelClass);
            echo '"' . $key . '":[';

            $firstRecord = true;
            foreach ($modelClass::cursor() as $record) {
                if (! $firstRecord) echo ',';
                $firstRecord = false;
                echo json_encode($record->only($fields));
            }
            echo ']';
        }
        echo '}';
    }, $filename, [
        'Content-Type' => 'application/json',
    ]);
}
```

**New test:** `tests/Feature/BackupExportTest.php`

```php
/** @test */
public function backup_does_not_include_password_hashes(): void
{
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)->get(route('management.backup.download'));

    $content = $response->streamedContent();
    $this->assertStringNotContainsString('password', strtolower($content));
    $this->assertStringNotContainsString('$2y$', $content);  // bcrypt prefix
}

/** @test */
public function backup_does_not_include_api_tokens(): void
{
    // similar check
}
```

**Verify:** Download a backup, grep for `password`, `token` — nothing should match.

---

### STEP B.3 — Harden AUTO_LOGIN_ENABLED gating

**File:** `app/Http/Middleware/TenantMiddleware.php`

**Code change:**

```php
// BEFORE (around line 65):
if (app()->environment('local') && config('app.auto_login_enabled', false) && ! Auth::check()) {
    // ...
}

// AFTER:
// Require BOTH: local env AND a file-based flag (harder to misconfigure in production)
$flagFile = storage_path('app/.auto_login_enabled');
if (
    app()->environment('local')
    && file_exists($flagFile)
    && ! Auth::check()
) {
    // ... existing logic
}
```

**Setup step:** For local dev, create the flag file:
```bash
touch storage/app/.auto_login_enabled
```

Add `.auto_login_enabled` to `.gitignore` so it's never committed.

**Test:** `php artisan test` — existing tests should still pass (they don't rely on auto-login in tests).

---

### STEP B.4 — Add authorize() to Customer/Vehicle/Mechanics update/destroy

**Files:**
- `app/Http/Controllers/CustomerController.php`
- `app/Http/Controllers/VehicleController.php` (if exists)
- `app/Http/Controllers/MechanicsController.php`

**Code change example:**

```php
public function update(UpdateCustomerRequest $request, Customer $customer)
{
    $this->authorize('update', $customer);  // <-- ADD
    $customer->update($request->validated());
    return back()->with('success', 'Customer updated.');
}

public function destroy(Customer $customer)
{
    $this->authorize('delete', $customer);  // <-- ADD
    // ... existing
}
```

Repeat for every mutation method that's missing `authorize()`.

**New test:** `tests/Feature/CustomerAuthorizationTest.php`

```php
/** @test */
public function technician_cannot_update_customer(): void
{
    $tech = User::factory()->technician()->create();
    $customer = Customer::factory()->for($tech->tenant)->create();

    $response = $this->actingAs($tech)->put(route('customers.update', $customer), [
        'name' => 'New Name',
    ]);

    $response->assertForbidden();
}
```

---

### STEP B.5 — Invoice immutability via DB trigger

**New file:** `database/migrations/2026_04_10_100000_add_invoice_immutability_trigger.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;  // PostgreSQL-only
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_issued_invoice_update()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Allow voiding (transition to void status)
                IF NEW.status = 'void' AND OLD.status != 'void' THEN
                    RETURN NEW;
                END IF;

                -- Allow payment tracking updates
                IF OLD.locked_at IS NOT NULL
                   AND (OLD.total != NEW.total
                        OR OLD.subtotal != NEW.subtotal
                        OR OLD.tax_total != NEW.tax_total
                        OR OLD.invoice_number != NEW.invoice_number
                        OR OLD.issue_date != NEW.issue_date) THEN
                    RAISE EXCEPTION 'Cannot modify financial fields on issued invoice %', OLD.id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS invoice_immutability_trigger ON invoices;
            CREATE TRIGGER invoice_immutability_trigger
                BEFORE UPDATE ON invoices
                FOR EACH ROW
                EXECUTE FUNCTION prevent_issued_invoice_update();
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS invoice_immutability_trigger ON invoices');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_issued_invoice_update');
    }
};
```

**Verify:** Try `Invoice::where('id', $issued->id)->update(['total' => 999])` — should throw PostgreSQL exception. On SQLite tests, trigger is skipped, so add an observer as a fallback for test env.

---

### STEP B.6 — Stock deduction row locking

Already bundled into A.4 (`lockForUpdate`). Skip here.

---

### STEP B.7 — WorkOrderPhoto tenant check + policy

**New file:** `app/Policies/WorkOrderPhotoPolicy.php`

```php
<?php
namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrderPhoto;

class WorkOrderPhotoPolicy
{
    public function view(User $user, WorkOrderPhoto $photo): bool
    {
        return $user->tenant_id === $photo->tenant_id;
    }

    public function delete(User $user, WorkOrderPhoto $photo): bool
    {
        return $user->tenant_id === $photo->tenant_id;
    }
}
```

Register in `AppServiceProvider::boot()`:
```php
Gate::policy(\App\Models\WorkOrderPhoto::class, \App\Policies\WorkOrderPhotoPolicy::class);
```

Update `WorkOrderPhotoController::destroy()`:
```php
public function destroy(WorkOrderPhoto $photo)
{
    $this->authorize('delete', $photo);
    // ... existing
}
```

**Test:** Add cross-tenant deletion test.

---

### STEP B.8 — AuditLog uses BelongsToTenant

**File:** `app/Models/AuditLog.php`

```php
// BEFORE:
class AuditLog extends Model
{
    protected $fillable = ['tenant_id', ...];

// AFTER:
use App\Traits\BelongsToTenant;

class AuditLog extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', ...];
```

**Risk:** Existing audit log queries in super-admin dashboard will now be tenant-scoped. Super-admin controllers that need to see all logs must use `AuditLog::withoutGlobalScopes()->...`.

**Test:** Verify super-admin still sees all logs, tenant user only sees their own.

---

### STEP B.9 — Fix undefined JavaScript in blade views

**Files:**
- `resources/views/work-orders/show.blade.php:39` — `submitCompletion()`
- `resources/views/checkin.blade.php:30` — `hideSuccessNotification()`

**Approach:** Read each file, find the onclick handler, either define the function above the button in a `<script>` block or replace with Alpine `x-on:click` + inline logic.

**Verify:** Open browser console on these pages, click the buttons, confirm no reference errors.

---

### STEP B.10 — Remove console.error from production

**File:** `resources/views/work-orders/create.blade.php:174`

**Code change:** Delete the `console.error(...)` line, OR wrap in `@if(app()->environment('local'))`.

---

### STEP B.11 — Tighten password reset rate limit

**File:** `routes/auth.php:27-39`

```php
Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
    ->name('password.email')
    ->middleware('throttle:3,5');  // 3 attempts per 5 minutes

Route::post('reset-password', [NewPasswordController::class, 'store'])
    ->name('password.store')
    ->middleware('throttle:3,5');
```

---

### STEP B.12 — Rate limit invite endpoint

**File:** `routes/web.php:120`

```php
Route::post('/mechanics/{mechanic}/invite', [MechanicsController::class, 'invite'])
    ->middleware('throttle:5,10')  // 5 invites per 10 minutes
    ->name('mechanics.invite');
```

---

### STEP B.13 — Create custom error pages

**New files:**
- `resources/views/errors/404.blade.php`
- `resources/views/errors/403.blade.php`
- `resources/views/errors/500.blade.php`
- `resources/views/errors/503.blade.php`

Each should extend the app layout and show a branded error page with:
- Error code + title
- Friendly description
- "Back to dashboard" or "Contact support" button
- IHRAUTO branding

**Verify:** Visit `/nonexistent-page` → custom 404. Try unauthorized action → custom 403.

---

### Sprint B sign-off checklist

- [ ] B.1 — TenantContext fallback removed
- [ ] B.2 — Backup export sanitized + test
- [ ] B.3 — AUTO_LOGIN_ENABLED file-based flag
- [ ] B.4 — authorize() on Customer/Vehicle/Mechanics + tests
- [ ] B.5 — Invoice immutability trigger migration
- [ ] B.7 — WorkOrderPhotoPolicy + authorize()
- [ ] B.8 — AuditLog BelongsToTenant trait
- [ ] B.9 — Fix undefined JS functions
- [ ] B.10 — Remove console.error
- [ ] B.11 — Tighten password reset rate limit
- [ ] B.12 — Rate limit invite endpoint
- [ ] B.13 — Custom error pages (404/403/500/503)
- [ ] All tests pass
- [ ] Migrations run cleanly on fresh DB

---

## SPRINT C — MEDIUM PRIORITY (Next sprint)

**Goal:** Data integrity + UX polish.
**Duration estimate:** 1 week

### Database (C.1–C.5)
- **C.1** Add FK on `appointments.tenant_id`
- **C.2** Add FK indexes on `appointments.customer_id/vehicle_id`, `quote_items.quote_id`
- **C.3** Add composite index `[tenant_id, start_time]` on appointments
- **C.4** Change `invoices.invoice_number` uniqueness to `(tenant_id, invoice_number)`
- **C.5** Add soft-delete cascade listeners on Customer, Vehicle, Invoice

### Business logic (C.6–C.13)
- **C.6** Round invoice balance to 2 decimals in accessor
- **C.7** Validate `started_at < completed_at` on work order completion
- **C.8** Store all datetimes in UTC; convert on display
- **C.9** Invalidate plan limit cache on model create events
- **C.10** Centralize license plate normalization helper
- **C.11** Add unique constraint `(tenant_id, storage_location, status='stored')` on tires
- **C.12** Add email-fallback dedup in `CheckinService`
- **C.13** Build customer merge tool

### Frontend / UX (C.14–C.22)
- **C.14** Add `aria-describedby` to form errors
- **C.15** Add `aria-label` to all icon-only buttons (audit all views)
- **C.16** Add text alternatives to color-only status indicators
- **C.17** Add loading state on all submit buttons (Alpine `:disabled` + spinner)
- **C.18** Replace `href="#"` with `<button>` or proper routes
- **C.19** Ensure minimum 44px touch targets on all interactive elements
- **C.20** Use Swiss CHF formatting (`number_format(..., 2, '.', "'")`)
- **C.21** Add mobile card view fallback for tables
- **C.22** Extract hardcoded strings to lang files

### Security (C.23–C.24)
- **C.23** Add HSTS header middleware in production
- **C.24** Remove `information_schema` query from health check

---

## SPRINT D — LOW PRIORITY (Backlog)

16 items — polish, consistency, minor validation. Details in `SECURITY-AUDIT-PLAN.md`.

Key items:
- Tighten vehicle year max
- Positive integer validation on invoice item quantities
- Invoice `paid_amount` denormalization → recompute via observer
- Remove soft deletes on payments (use immutable pattern instead)
- Consistent button styles across pages
- i18n month names + date formats
- Replace inline `style=""` with Tailwind

---

## EXECUTION PROTOCOL

### Before starting each step:
1. Read the current file(s)
2. Understand the current implementation
3. Verify the finding still applies (code may have changed)

### During each step:
1. Make the exact change described
2. Add the test case (unless read-only change)
3. Run `php artisan test --filter=<relevant>` first
4. Then run `php artisan test` (full suite)

### After each step:
1. If green → commit with descriptive message
2. If red → diagnose, fix, re-run
3. If blocked → document the blocker, move on

### Commit message format:
```
<type>(<scope>): <short description> (<SEVERITY>)

<body if needed>

Finding: <audit ref, e.g. A.1>
```

Examples:
- `fix(finance): scope analytics queries to tenant (CRITICAL)`
- `fix(auth): use hash_equals for invite tokens (CRITICAL)`
- `feat(errors): add custom 404/403/500 pages (HIGH)`

### At end of each sprint:
1. Run full test suite
2. Run manual QA on critical flows
3. Update `CHANGELOG.md`
4. Tag the commit (optional): `sprint-a-complete`, `sprint-b-complete`

---

## RISK REGISTER

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Sprint A fixes break existing tests | Medium | High | Run tests after each step, fix immediately |
| B.8 (AuditLog scoping) breaks admin dashboard | Medium | Medium | Audit all AuditLog queries, add `withoutGlobalScopes()` where needed |
| B.5 (Invoice trigger) breaks on SQLite tests | Low | Low | Migration already skips SQLite; tests unaffected |
| C.5 (Soft-delete cascade) orphans existing data | Medium | High | Run data audit before migration, backup DB |
| Time estimate overruns | High | Low | Plan is over-detailed intentionally; skip D if time runs out |

---

## DECISION POINTS (YOUR CALL)

Before I start implementing, please confirm:

1. **Scope:** Do all 4 sprints (A + B + C + D), or stop after Sprint B?
2. **Test discipline:** Run full suite after EVERY step (slower), or after every 3–5 steps (faster)?
3. **Commit granularity:** One commit per step (cleanest history) or one per sprint?
4. **Sprint A A.3 (SSO):** Do you want the simpler "resolve tenant from subdomain" fix, or a more complete "force user to select tenant after Google auth" flow?
5. **B.5 (invoice trigger):** OK to proceed with PostgreSQL-only trigger, or defer until full testing strategy?
6. **C.5 (soft-delete cascade):** This needs data audit first. OK to defer to a follow-up sprint?

---

## EXPECTED OUTCOME

At end of Sprint A:
- 5 critical vulnerabilities closed
- ~5 new regression tests added
- Safe to deploy to staging

At end of Sprint B:
- All high-severity findings closed
- Custom error pages live
- Backup export sanitized
- ~15 new tests added
- Safe to deploy to production

At end of Sprint C:
- Data integrity hardened
- Frontend polished
- i18n started
- ~30 new tests added
- Production hardened

At end of Sprint D:
- Code quality polished
- All 58 findings closed
- Full test count: ~290+

**Ready to execute on your confirmation.**
