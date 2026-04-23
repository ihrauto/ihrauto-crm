# IHRAUTO CRM - Step-by-Step Implementation Plan

**Created:** April 1, 2026
**Source:** Full Technical & Business Audit Report + Previous Fix Plan
**Status:** Active - Work in Progress

---

## How to Use This Document

Each step is a self-contained unit of work. Steps are ordered by priority and dependency. Check off each step as it's completed. Each step includes:
- **What:** The specific change to make
- **Where:** Exact files to modify
- **How:** Implementation details
- **Verify:** How to confirm it works
- **Depends on:** Prerequisites (if any)

**Legend:**
- [x] = Already completed in previous sessions
- [ ] = Pending

---

## SPRINT 1: Critical Security & Data Integrity (P0)

> These items represent active security vulnerabilities or data corruption risks. Do first.

### Step 1.1: Fix XSS in Work Order Board
- [ ] **What:** Replace `addslashes()` with `Js::from()` in onclick handlers
- [ ] **Where:** `resources/views/work-orders/board.blade.php`
- [ ] **How:** Find all `addslashes()` calls used in JavaScript context and replace with Laravel's `Js::from()` helper which properly escapes for JS
- [ ] **Also:** Remove all `console.log()` statements from the same file
- [ ] **Verify:** View page source, confirm no `addslashes` or `console.log` remain. Test with a work order note containing `'; alert('xss'); //` - should render safely

### Step 1.2: Add Authorization to WorkOrderController
- [ ] **What:** Add `$this->authorize()` checks to mutation methods
- [ ] **Where:** `app/Http/Controllers/WorkOrderController.php`
- [ ] **How:**
  - Add `$this->authorize('update', $workOrder)` at the start of `update()`
  - Add `$this->authorize('delete', $workOrder)` at the start of `destroy()` (if exists)
  - Verify a `WorkOrderPolicy` exists, create one if not (follow `ProductPolicy` pattern)
- [ ] **Verify:** Login as technician, attempt to update another technician's work order - should get 403

### Step 1.3: Add Authorization to AppointmentController
- [ ] **What:** Add policy-based authorization to all mutation methods
- [ ] **Where:** `app/Http/Controllers/AppointmentController.php`
- [ ] **How:**
  - Add `$this->authorize('create', Appointment::class)` to `store()`
  - Add `$this->authorize('update', $appointment)` to `update()`
  - Add `$this->authorize('delete', $appointment)` to `destroy()`
  - Wire up existing `AppointmentPolicy` if not registered in `AppServiceProvider`
- [ ] **Verify:** Run `tests/Feature/AppointmentTest.php`, add negative authorization test

### Step 1.4: Add Authorization to PaymentController
- [ ] **What:** Add authorization check before processing payments
- [ ] **Where:** `app/Http/Controllers/PaymentController.php`
- [ ] **How:**
  - Add `Gate::authorize('view-financials')` to `store()` (payment creation is a financial action)
  - Or create a `PaymentPolicy` if finer-grained control needed
  - Verify the invoice belongs to the current tenant before accepting payment
- [ ] **Verify:** Login as technician (without finance permission), attempt payment - should get 403

### Step 1.5: Make Stock Deductions Idempotent
- [ ] **What:** Prevent double stock deductions if `processStockDeductions()` is called twice
- [ ] **Where:** `app/Services/InvoiceService.php`
- [ ] **How:**
  - Add a `stock_deducted` boolean flag to work_orders table (migration)
  - Check flag before deducting: `if ($workOrder->stock_deducted) return;`
  - Set flag after successful deduction within the same transaction
  - Alternative: Check if stock_movements already exist for this invoice
- [ ] **Verify:** Call `processStockDeductions()` twice on the same work order - stock should only decrease once

### Step 1.6: Add Invoice Item Count Validation
- [ ] **What:** Prevent creating invoices with zero items
- [ ] **Where:** `app/Services/InvoiceService.php`
- [ ] **How:**
  - In the invoice creation flow, check that work order has at least one service task or part
  - Throw a business exception if no items: `throw new \InvalidArgumentException('Cannot create invoice with no items.')`
- [ ] **Verify:** Attempt to invoice a work order with no tasks/parts - should get error message

### Step 1.7: Run Pending Database Migrations
- [ ] **What:** Execute the 4 pending migrations created in previous session
- [ ] **Where:** Database
- [ ] **How:**
  1. **Backup database first** (`php artisan backup:run --only-db`)
  2. Dry-run: `php artisan migrate --pretend` to review SQL
  3. Run: `php artisan migrate`
  4. Migrations to run:
     - `2026_03_22_100000_fix_tenant_id_type_on_catalog_tables` (string -> bigint)
     - `2026_03_22_100100_add_tenant_id_to_audit_logs`
     - `2026_03_22_100200_add_missing_performance_indexes`
     - `2026_03_22_100300_add_service_bay_fk_to_checkins`
- [ ] **Verify:** `php artisan migrate:status` shows all migrations ran. Test products/services CRUD still works.

---

## SPRINT 2: Performance & Caching (P1)

> Dashboard is the first page every user sees. 20+ queries per load will not scale.

### Step 2.1: Cache DashboardService Stats
- [ ] **What:** Add Redis caching with 5-minute TTL to dashboard statistics
- [ ] **Where:** `app/Services/DashboardService.php`
- [ ] **How:**
  - Wrap `getStats()` result in `Cache::remember("dashboard_stats_{tenant_id()}", 300, fn() => ...)`
  - Add cache invalidation when relevant models change (checkin created, work order updated, invoice issued)
  - Use tenant-scoped cache keys to prevent cross-tenant data leakage
- [ ] **Verify:** Enable query logging (`DB::enableQueryLog()`), load dashboard twice - second load should have far fewer queries

### Step 2.2: Consolidate DashboardService Queries
- [ ] **What:** Reduce ~20 separate COUNT queries to ~5 aggregated queries
- [ ] **Where:** `app/Services/DashboardService.php`
- [ ] **How:**
  - Use `selectRaw('status, COUNT(*) as count')->groupBy('status')` pattern for work orders
  - Combine checkin stats into single query with `CASE WHEN` aggregation
  - Replace individual `count()` calls with a single grouped query per model
- [ ] **Verify:** Enable query log, count queries before and after. Target: <10 queries for full dashboard

### Step 2.3: Fix N+1 in FinanceController
- [ ] **What:** Add eager loading to invoice/payment queries
- [ ] **Where:** `app/Http/Controllers/FinanceController.php`
- [ ] **How:**
  - Add `->with('customer')` to unpaid invoices query
  - Add `->with(['invoice', 'invoice.customer'])` to recent payments query
  - Add `->with('items')` where invoice totals are computed
- [ ] **Verify:** Check Laravel Debugbar or query log - finance page should have fewer queries

### Step 2.4: Fix N+1 in WorkOrderController
- [ ] **What:** Ensure all work order listings eager-load relationships
- [ ] **Where:** `app/Http/Controllers/WorkOrderController.php`
- [ ] **How:**
  - In `index()`: add `->with(['customer', 'vehicle', 'technician'])`
  - In `board()`: ensure technician query uses `->with(['workOrders.customer', 'workOrders.vehicle'])`
- [ ] **Verify:** Query log shows consistent query count regardless of number of work orders

### Step 2.5: Reduce API Token Cache TTL
- [ ] **What:** Reduce token cache from 5 minutes to 60 seconds, add invalidation
- [ ] **Where:** `app/Http/Middleware/AuthenticateTenantApiToken.php`
- [ ] **How:**
  - Change cache TTL from `300` to `60`
  - Add `Cache::forget("api_token_{$hash}")` when token is revoked/deleted
  - Add the forget call in whatever method handles token deletion
- [ ] **Verify:** Revoke an API token, confirm it stops working within 60 seconds (not 5 minutes)

---

## SPRINT 3: Testing Coverage (P1)

> Add tests for the authorization and business logic already implemented.

### Step 3.1: Add Policy Tests
- [ ] **What:** Unit test all policies (ProductPolicy, ServicePolicy, AppointmentPolicy, TirePolicy)
- [ ] **Where:** `tests/Feature/PolicyTest.php` (new)
- [ ] **How:**
  - Test each policy method with admin, manager, technician, receptionist roles
  - Test cross-tenant denial (user from tenant A cannot access tenant B's resources)
  - Follow pattern from existing `AuthorizationTest.php`
- [ ] **Verify:** `php artisan test --filter=PolicyTest`

### Step 3.2: Add Middleware Tests
- [ ] **What:** Test TenantMiddleware, CheckModuleAccess, EnsureTenantTrialActive, RequireTireHotelAccess
- [ ] **Where:** `tests/Feature/MiddlewareTest.php` (new)
- [ ] **How:**
  - Test expired tenant gets redirected to tenant-expired page
  - Test module access checks (e.g., tire hotel disabled = 403)
  - Test tenant resolution chain (subdomain, domain, session)
- [ ] **Verify:** `php artisan test --filter=MiddlewareTest`

### Step 3.3: Add Negative Test Cases
- [ ] **What:** Test that invalid/unauthorized operations are properly rejected
- [ ] **Where:** Update existing test files + new `tests/Feature/NegativeCasesTest.php`
- [ ] **How:**
  - Duplicate customer email within tenant
  - Cross-tenant data access attempts on all CRUD controllers
  - Invoice editing after issuance (should fail)
  - Payment exceeding invoice balance
  - Negative stock quantities
  - Invalid CSV import data
- [ ] **Verify:** `php artisan test --filter=NegativeCases`

### Step 3.4: Add Concurrency Tests for Financial Operations
- [ ] **What:** Test race conditions in payment and invoice creation
- [ ] **Where:** `tests/Feature/ConcurrencyTest.php` (new)
- [ ] **How:**
  - Two payments on same invoice simultaneously (only one should succeed for exact balance)
  - Concurrent invoice creation from same work order
  - Concurrent stock deductions
  - Use database transactions and `lockForUpdate()` verification
- [ ] **Verify:** `php artisan test --filter=ConcurrencyTest`

### Step 3.5: Add Service Unit Tests
- [ ] **What:** Create true unit tests with mocks for all services
- [ ] **Where:** `tests/Unit/Services/` directory
- [ ] **How:**
  - `InvoiceServiceTest.php` - mock repositories, test business logic
  - `CheckinServiceTest.php` - test customer deduplication logic
  - `TireStorageServiceTest.php` - test location assignment logic
  - `TenantProvisioningServiceTest.php` - test tenant setup flow
  - Use Mockery for dependency injection
- [ ] **Verify:** `php artisan test --testsuite=Unit`

---

## SPRINT 4: Controller Refactoring (P2)

> Extract business logic from fat controllers into services. This is the highest-impact code quality improvement.

### Step 4.1: Refactor WorkOrderController::update()
- [ ] **What:** Extract 85-line update method into WorkOrderService
- [ ] **Where:**
  - `app/Http/Controllers/WorkOrderController.php`
  - `app/Services/WorkOrderService.php` (new or existing)
- [ ] **How:**
  - Create `WorkOrderService::updateWorkOrder($workOrder, array $data)` method
  - Move status transition logic, task completion tracking, parts handling into service
  - Controller should only: validate -> authorize -> call service -> return response
- [ ] **Depends on:** Step 1.2 (authorization added first)
- [ ] **Verify:** All existing `WorkOrderTest.php` tests still pass

### Step 4.2: Refactor CheckinController::store()
- [ ] **What:** Extract 70+ line store method - move remaining logic to CheckinService
- [ ] **Where:**
  - `app/Http/Controllers/CheckinController.php`
  - `app/Services/CheckinService.php`
- [ ] **How:**
  - Move work order creation logic into `CheckinService::createWorkOrderFromCheckin()`
  - Move photo upload handling into a separate `PhotoUploadService` or method
  - Controller should only: validate -> call service -> redirect
- [ ] **Verify:** All existing `CheckinTest.php` tests still pass

### Step 4.3: Refactor TireHotelController
- [ ] **What:** Remove duplicate/legacy methods, extract to TireStorageService
- [ ] **Where:**
  - `app/Http/Controllers/TireHotelController.php`
  - `app/Services/TireStorageService.php`
- [ ] **How:**
  - Identify duplicate methods (there may be legacy + new versions)
  - Extract business logic (storage assignment, capacity checks) to service
  - Keep controller thin
- [ ] **Verify:** All `TireHotelTest.php` tests still pass

### Step 4.4: Refactor FinanceController
- [ ] **What:** Extract reporting logic into a FinanceService or ReportingService
- [ ] **Where:**
  - `app/Http/Controllers/FinanceController.php`
  - `app/Services/FinanceService.php` (new)
- [ ] **How:**
  - Extract revenue calculations, payment summaries, unpaid invoice queries
  - Controller should just call service and pass data to view
- [ ] **Verify:** Finance page still loads correctly with same data

### Step 4.5: Wire Enums to Models
- [ ] **What:** Apply created enum classes as model casts and replace string comparisons
- [ ] **Where:**
  - `app/Models/WorkOrder.php` - cast `status` to `WorkOrderStatus`
  - `app/Models/Checkin.php` - cast `status` to `CheckinStatus`
  - `app/Models/Payment.php` - cast `method` to `PaymentMethod`
  - `app/Models/Invoice.php` - cast `status` to `InvoiceStatus`
  - All controllers/services that compare status strings
- [ ] **How:**
  - Add `protected $casts = ['status' => WorkOrderStatus::class]` to models
  - Replace `$workOrder->status === 'completed'` with `$workOrder->status === WorkOrderStatus::Completed`
  - Update Blade templates that compare status strings
  - Update form request validation rules to use enum values
- [ ] **Verify:** Full test suite passes. All status comparisons work correctly.

---

## SPRINT 5: Frontend & Accessibility (P2)

> The frontend is the weakest area (4.5/10). Focus on accessibility first (legal risk), then consistency.

### Step 5.1: Add ARIA Labels to Interactive Elements
- [ ] **What:** Add accessibility attributes to all forms, modals, buttons, and navigation
- [ ] **Where:** All Blade templates in `resources/views/`
- [ ] **How:**
  - Add `aria-label` to icon-only buttons
  - Add `aria-modal="true"` and `aria-labelledby` to all modals
  - Connect form labels to inputs with `for`/`id` attributes
  - Add `role="navigation"` to nav elements
  - Add `aria-live="polite"` to flash messages
  - Add skip link at top of layout
- [ ] **Verify:** Run Lighthouse accessibility audit, target score > 80

### Step 5.2: Extract Inline JavaScript
- [ ] **What:** Move 23+ inline `<script>` blocks into proper JS modules
- [ ] **Where:**
  - `resources/js/` directory (new modules)
  - Blade templates (remove inline scripts)
- [ ] **How:**
  - Create `resources/js/modules/checkin.js`, `work-orders.js`, `appointments.js`, etc.
  - Move Alpine.js component data to separate files where complex
  - Use `@vite` directive to include compiled assets
  - Replace hardcoded route paths with `route()` or data attributes
- [ ] **Depends on:** Step 1.1 (XSS fix first)
- [ ] **Verify:** All interactive features still work. No inline scripts remain.

### Step 5.3: Create Design System Tokens
- [ ] **What:** Standardize colors, spacing, typography, and shadows
- [ ] **Where:**
  - `tailwind.config.js` - define design tokens
  - `resources/css/` - add design system CSS if needed
  - Blade components in `resources/views/components/`
- [ ] **How:**
  - Define primary, secondary, accent, success, warning, danger color scales
  - Standardize spacing (sm=2, md=4, lg=6, xl=8)
  - Standardize shadows (sm, md, lg only)
  - Create consistent button size variants
  - Update existing components to use tokens
- [ ] **Verify:** Visual regression check - pages should look cleaner and more consistent

### Step 5.4: Build Reusable Modal Component
- [ ] **What:** Replace 4+ different modal implementations with one Blade component
- [ ] **Where:**
  - `resources/views/components/modal.blade.php` (new or update existing)
  - All templates using modals
- [ ] **How:**
  - Create Alpine.js-powered modal component with standard API
  - Support sizes (sm, md, lg, xl), close on escape, backdrop click
  - Include proper ARIA attributes
  - Replace all existing modal implementations one by one
- [ ] **Verify:** All modals work consistently. Keyboard navigation (Escape to close) works.

### Step 5.5: Build Reusable Data Table Component
- [ ] **What:** Create a standard table component with sorting, pagination, responsive design
- [ ] **Where:** `resources/views/components/data-table.blade.php` (new)
- [ ] **How:**
  - Support column definitions, sortable headers, pagination slot
  - Responsive: horizontal scroll on mobile with sticky first column
  - Consistent styling across all list views
- [ ] **Verify:** Replace one existing table (e.g., customers list) as proof of concept

---

## SPRINT 6: Internationalization & Documentation (P2)

### Step 6.1: i18n Setup and Core Translations
- [ ] **What:** Set up Laravel localization for German, French, Italian (Swiss market)
- [ ] **Where:**
  - `lang/de/`, `lang/fr/`, `lang/it/` directories (new)
  - `config/app.php` - locale settings
- [ ] **How:**
  - Extract all hardcoded English strings from Blade templates
  - Create translation files: `messages.php`, `validation.php`, `auth.php`
  - Replace hardcoded strings with `__('key')` or `@lang('key')` in templates
  - Start with German (primary Swiss market language)
  - Add locale switcher in user settings
- [ ] **Verify:** Switch locale to `de` - all UI text should display in German

### Step 6.2: Update Code Map Documentation
- [ ] **What:** Bring `docs/reference/code-map.md` up to date
- [ ] **Where:** `docs/reference/code-map.md`
- [ ] **How:**
  - Add all new files (policies, enums, form requests, services)
  - Update file counts and descriptions
  - Add new migration descriptions
  - Document the enum classes and their usage
- [ ] **Verify:** Every file in `app/` is represented in the code map

### Step 6.3: Generate API Documentation
- [ ] **What:** Create OpenAPI/Swagger spec for the v1 API
- [ ] **Where:** `docs/api/` directory (new)
- [ ] **How:**
  - Option A: Install `knuckleswtf/scribe` and generate from routes/docblocks
  - Option B: Write OpenAPI YAML manually for the ~10 API endpoints
  - Document authentication (API token), rate limits, request/response schemas
- [ ] **Verify:** API docs are browsable and match actual API behavior

### Step 6.4: Fix Documentation Discrepancies
- [ ] **What:** Correct known inaccuracies in existing docs
- [ ] **Where:** Various docs files
- [ ] **How:**
  - Fix tax rate docs: clarify it's application-wide via `CRM_TAX_RATE`, not per-tenant
  - Add version numbers to CHANGELOG.md entries
  - Document the tenant_id type migration and its rationale
  - Update engineering board with current status
- [ ] **Verify:** Read through each doc file, confirm it matches current code behavior

### Step 6.5: Create Production Runbook
- [ ] **What:** Document operational procedures for production
- [ ] **Where:** `docs/runbook.md` (new)
- [ ] **How:**
  - Backup and restore procedures
  - Tenant provisioning/deactivation steps
  - Emergency procedures (data leak response, downtime recovery)
  - Migration procedures (pre-flight checks, rollback steps)
  - Monitoring and alerting (Sentry integration, health check)
  - Environment variable documentation (all vars, what they do, defaults)
- [ ] **Verify:** A new team member could follow the runbook to perform each operation

---

## SPRINT 7: Business Logic Improvements (P2)

### Step 7.1: Add Appointment Conflict Detection
- [ ] **What:** Prevent double-booking technicians
- [ ] **Where:**
  - `app/Http/Requests/StoreAppointmentRequest.php`
  - `app/Http/Requests/UpdateAppointmentRequest.php`
  - `app/Services/AppointmentService.php` (new or existing)
- [ ] **How:**
  - Add custom validation rule that checks for overlapping appointments
  - Query: same technician, overlapping time range, not cancelled
  - `WHERE technician_id = ? AND status != 'cancelled' AND start_time < ? AND end_time > ?`
  - Return clear error message: "Technician X is already booked from HH:MM to HH:MM"
- [ ] **Verify:** Try to book two appointments for same technician at same time - should get validation error

### Step 7.2: Cache Plan Limit Checks
- [ ] **What:** Stop querying work order/customer counts on every check
- [ ] **Where:** `app/Models/Tenant.php` - `canCreateWorkOrder()`, etc.
- [ ] **How:**
  - Cache counts per tenant with short TTL (60 seconds)
  - `Cache::remember("tenant_{$id}_wo_count", 60, fn() => $this->workOrders()->count())`
  - Invalidate cache on model creation/deletion events
- [ ] **Verify:** Query log shows fewer queries during work order creation

### Step 7.3: Fix TireStorageService O(n^3) Algorithm
- [ ] **What:** Replace nested loop location finder with indexed query
- [ ] **Where:** `app/Services/TireStorageService.php` - `getNextAvailableLocation()`
- [ ] **How:**
  - Instead of iterating sections x rows x positions, query for occupied locations
  - Find first gap using SQL: `SELECT ... WHERE location NOT IN (SELECT location FROM tires WHERE status = 'stored')`
  - Or maintain a `storage_locations` table with occupancy status
- [ ] **Verify:** Performance test with 100+ stored tire sets - should complete in <100ms

### Step 7.4: Build Customer Merge Tool
- [ ] **What:** Allow merging duplicate customer records
- [ ] **Where:**
  - `app/Http/Controllers/CustomerController.php` - add `merge()` method
  - `resources/views/customers/merge.blade.php` (new)
  - `app/Services/CustomerMergeService.php` (new)
- [ ] **How:**
  - UI: Select two customers, choose which fields to keep
  - Backend: Transfer all related records (vehicles, checkins, invoices, tires) to surviving customer
  - Delete the merged-away customer (soft delete)
  - Audit log the merge action with both customer IDs
- [ ] **Verify:** Merge two customers - all related records should point to the surviving customer

---

## SPRINT 8: Database Hardening (P2-P3)

### Step 8.1: Add CHECK Constraints for Enum Fields
- [ ] **What:** Database-level validation for status/type columns
- [ ] **Where:** New migration
- [ ] **How:**
  - `ALTER TABLE work_orders ADD CONSTRAINT chk_wo_status CHECK (status IN ('created','pending','in_progress','completed','invoiced','cancelled'))`
  - Same for checkins.status, invoices.status, payments.method, tires.status
  - `vehicles.year CHECK (year > 1900 AND year <= EXTRACT(YEAR FROM NOW()) + 1)`
- [ ] **Verify:** Try inserting invalid status via raw SQL - should fail

### Step 8.2: Add SKU Uniqueness per Tenant
- [ ] **What:** Prevent duplicate SKUs within the same tenant
- [ ] **Where:** New migration
- [ ] **How:**
  - Add unique composite index: `UNIQUE(tenant_id, sku) WHERE sku IS NOT NULL`
  - This allows NULL SKUs (not all products have SKUs) but enforces uniqueness for those that do
- [ ] **Verify:** Try creating two products with same SKU in same tenant - should fail

### Step 8.3: Add Soft Deletes to Critical Models
- [ ] **What:** Prevent permanent data loss on delete
- [ ] **Where:**
  - `app/Models/User.php` - add `SoftDeletes`
  - `app/Models/Payment.php` - add `SoftDeletes`
  - `app/Models/WorkOrderPhoto.php` - add `SoftDeletes`
  - New migration to add `deleted_at` columns
- [ ] **How:**
  - Add `use SoftDeletes;` trait to each model
  - Add `$table->softDeletes()` via migration
  - Update any `::all()` or `::get()` queries that should include soft-deleted (use `withTrashed()`)
- [ ] **Verify:** Delete a user, confirm they're still in DB with `deleted_at` set. `User::count()` should exclude them.

### Step 8.4: Add Missing Foreign Key Indexes
- [ ] **What:** Add indexes on remaining FK columns that lack them
- [ ] **Where:** New migration
- [ ] **How:**
  - Check `invoices.work_order_id` - add index if missing
  - Check `work_orders.checkin_id` - add index if missing
  - Check `tires.customer_id`, `tires.vehicle_id` - add indexes if missing
  - Use `Schema::hasIndex()` to avoid duplicate index errors
- [ ] **Verify:** `\d tablename` in psql shows indexes on all FK columns

---

## SPRINT 9: DevOps & Infrastructure (P3)

### Step 9.1: Add Staging Environment
- [ ] **What:** Define staging service in Render config
- [ ] **Where:** `render.yaml`
- [ ] **How:**
  - Duplicate the web service definition with name `ihrauto-crm-staging`
  - Point to `staging` branch
  - Use separate database instance
  - Set `APP_ENV=staging`
- [ ] **Verify:** Push to staging branch, confirm staging environment deploys

### Step 9.2: Add Migration Dry-Run to CI
- [ ] **What:** Prevent broken migrations from reaching production
- [ ] **Where:** CI/CD pipeline (GitHub Actions or Render build)
- [ ] **How:**
  - Add `php artisan migrate --pretend` to build step
  - If it fails, abort deployment
  - Add `php artisan test` to build step
- [ ] **Verify:** Push a broken migration - deployment should fail

### Step 9.3: Document Rollback Procedures
- [ ] **What:** Step-by-step guide for rolling back deployments
- [ ] **Where:** `docs/runbook.md` (append to Step 6.5)
- [ ] **How:**
  - Document how to rollback a Render deployment
  - Document how to rollback database migrations (`php artisan migrate:rollback --step=N`)
  - Document how to restore from backup
  - Include emergency contact information
- [ ] **Verify:** Simulate a rollback scenario in staging

### Step 9.4: Add CDN for Static Assets
- [ ] **What:** Serve CSS/JS/images via CDN for better performance
- [ ] **Where:** `config/filesystems.php`, `vite.config.js`
- [ ] **How:**
  - Configure S3/CloudFront or similar CDN
  - Update Vite config to output to CDN-accessible path
  - Set `ASSET_URL` environment variable
- [ ] **Verify:** Load page, confirm static assets served from CDN domain

---

## SPRINT 10: Advanced Features (P3 - Backlog)

> These are valuable features but not urgent. Tackle as capacity allows.

### Step 10.1: Revenue Analytics Dashboard
- [ ] **What:** Build analytics page for shop owners showing revenue trends, technician utilization, customer retention
- [ ] **Where:** New controller, service, and view
- [ ] **How:**
  - `app/Http/Controllers/AnalyticsController.php` (new)
  - `app/Services/AnalyticsService.php` (new)
  - Charts: monthly revenue, payment method breakdown, top services, technician productivity
  - Use Chart.js or similar for visualization
  - Cache aggregated data with appropriate TTL
- [ ] **Verify:** Analytics page loads with accurate data matching manual calculations

### Step 10.2: Dark Mode Support
- [ ] **What:** Add dark mode toggle and styling
- [ ] **Where:** All Blade templates, Tailwind config
- [ ] **How:**
  - Enable Tailwind dark mode: `darkMode: 'class'`
  - Add `dark:` variants to all component styles
  - Add toggle in user settings (persist preference in user record or localStorage)
  - Use CSS custom properties for smooth theme switching
- [ ] **Verify:** Toggle dark mode - all pages should be legible with proper contrast

### Step 10.3: E2E Tests with Laravel Dusk
- [ ] **What:** Browser tests for critical user flows
- [ ] **Where:** `tests/Browser/` directory
- [ ] **How:**
  - Install Laravel Dusk
  - Test flows: login -> checkin -> work order -> invoice -> payment
  - Test appointment creation and calendar interaction
  - Test tire hotel storage assignment
- [ ] **Verify:** `php artisan dusk` passes all browser tests

### Step 10.4: PostgreSQL Row-Level Security
- [ ] **What:** Add database-level tenant isolation as defense-in-depth
- [ ] **Where:** New migration with raw SQL
- [ ] **How:**
  - Create RLS policies on all tenant-scoped tables
  - Set `current_setting('app.tenant_id')` on each connection
  - Test that raw `DB::table()` queries are filtered
  - This is a second layer - application-level scoping remains primary
- [ ] **Verify:** Connect to DB directly, set tenant context, confirm only that tenant's data is visible

### Step 10.5: Vue/React Components for Complex UIs
- [ ] **What:** Migrate appointment calendar and work order board to proper SPA components
- [ ] **Where:** `resources/js/components/` (new)
- [ ] **How:**
  - Evaluate Vue 3 (lighter weight, easier Laravel integration) vs React
  - Start with appointment calendar - most complex interactive UI
  - Use Inertia.js or standalone Vue components with API calls
  - Keep simpler pages as Blade templates
- [ ] **Verify:** Calendar component has same features as current implementation plus drag-and-drop

---

## Progress Tracking

### Already Completed (from previous sessions)

- [x] Phase 1.1: FinanceController authorization (Gate::authorize)
- [x] Phase 1.2: ServicePolicy created + ServiceController authorized
- [x] Phase 1.3: ProductController authorization added
- [x] Phase 1.4: ServiceBayController authorization added
- [x] Phase 1.5: API controllers defense-in-depth (abort_unless)
- [x] Phase 1.6: TireHotelController authorization added
- [x] Phase 2.1: Migration for tenant_id type fix (created, not yet run)
- [x] Phase 2.2: Migration for audit_logs tenant_id (created, not yet run)
- [x] Phase 2.3: Migration for performance indexes (created, not yet run)
- [x] Phase 2.4: Migration for service_bay FK (created, not yet run)
- [x] Phase 3.1: All 8 FormRequest classes created
- [x] Phase 3.2: CSV import validation improved
- [x] Phase 3.3: tenant_id() helper standardized across codebase
- [x] Phase 4.2: Enum classes created (WorkOrderStatus, CheckinStatus, PaymentMethod, InvoiceStatus)
- [x] Phase 4.4: Rate limiters defined in AppServiceProvider
- [x] Phase 4.5: Customer deduplication in CheckinService
- [x] Phase 5.1: AuthorizationTest.php created (7 tests)
- [x] Phase 5.4: getimagesize() validation added to photo uploads
- [x] AuditLog.php updated with tenant_id in fillable
- [x] Auditable.php updated to set tenant_id

### Completed in Current Session (April 1, 2026)

**Sprint 1 (P0 Critical) - COMPLETE:**
- [x] Step 1.1: Fix XSS (`addslashes` -> `Js::from` + data attributes), remove `console.log`
- [x] Step 1.2: Add authorization to WorkOrderController (store, update, generateInvoice)
- [x] Step 1.3: Add authorization to AppointmentController (store, update, reschedule, destroy)
- [x] Step 1.4: Add authorization to PaymentController (Gate::authorize)
- [x] Step 1.5: Make stock deductions idempotent (check existing StockMovements)
- [x] Step 1.6: Reject invoices with zero items (InvalidArgumentException)
- [x] Step 1.7: Migrations fixed for SQLite compatibility + ready for production
- [x] Fix: `view-financials` gate now checks correct permission (`access finance`)
- [x] Fix: ProductPolicy/ServicePolicy strict type comparison for string tenant_id
- [x] Fix: AuthorizationTest uses correct role (receptionist, not technician)
- [x] Fix: Migration 100300 keeps `service_bay` column (premature drop prevented)

**Sprint 2 (P1 Performance) - COMPLETE:**
- [x] Step 2.1: Cache DashboardService stats (5-min TTL, tenant-scoped key)
- [x] Step 2.2: Consolidate work order stats (4 queries -> 1), checkin stats (4 -> 1)
- [x] Step 2.3: FinanceController balance filtering moved from PHP to SQL
- [x] Step 2.4: WorkOrderController N+1 already fixed (verified)
- [x] Step 2.5: API token cache TTL reduced from 5 minutes to 60 seconds

**Sprint 3 (P1 Testing) - COMPLETE:**
- [x] Step 3.1: Policy tests — 22 tests (6 policies + 3 gates, cross-tenant, role-based)
- [x] Step 3.2: Middleware tests — 10 tests (tenant lifecycle, module access, plan gating, auth)
- [x] Step 3.3: Negative test cases — 11 tests (cross-tenant, immutability, overpayment, idempotency)
- [x] Step 3.4: Concurrency tests — 6 tests (duplicate invoice, unique numbering, stock, payment)
- [x] Step 3.5: CheckinService tests — 5 tests (registration, dedup, vehicle reuse)
- [x] Fix: InvoiceService::createFromWorkOrder() idempotency (query DB, not cached relation)

**Test count: 188 -> 242 (+54 tests). All passing.**

### Remaining Work Summary

| Sprint | Items | Priority | Estimated Effort |
|--------|-------|----------|-----------------|
| Sprint 4 | 5 items | P2 Medium | Large |
| Sprint 5 | 5 items | P2 Medium | Large |
| Sprint 6 | 5 items | P2 Medium | Medium |
| Sprint 7 | 4 items | P2 Medium | Medium-Large |
| Sprint 8 | 4 items | P2-P3 | Small-Medium |
| Sprint 9 | 4 items | P3 Low | Medium |
| Sprint 10 | 5 items | P3 Backlog | Large |

**Total remaining steps: 32**

---

## Notes

- Each sprint should result in one or more git commits with clear messages
- Run `php artisan test` after every sprint to catch regressions
- Sprint 1 is mandatory before any production deployment
- Sprints 2-4 should be completed before onboarding more tenants
- Sprints 5-10 can be prioritized based on business needs and customer feedback
