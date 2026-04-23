# Code Map

This document answers two questions:

1. Where does a responsibility belong in this codebase?
2. Which classes are the main entrypoints for that responsibility?

## Repository Map

| Path | Responsibility |
| --- | --- |
| `routes/web.php` | Public, tenant, admin, and developer web routes |
| `routes/api.php` | Tenant API and legacy API routes |
| `bootstrap/app.php` | Route registration and middleware wiring |
| `app/Http/Controllers` | HTTP orchestration for tenant web, API, admin, auth, and dev flows |
| `app/Http/Middleware` | Tenant resolution, access boundaries, and request hardening |
| `app/Http/Requests` | Validation rules for write operations |
| `app/Services` | Multi-step business workflows and reporting logic |
| `app/Models` | Persistence, relationships, scopes, and local domain behavior |
| `app/Policies` | Record-level authorization |
| `app/Observers` | Model-triggered side effects |
| `app/Console/Commands` | Operational commands and maintenance runbooks |
| `database/migrations` | Schema and data evolution |
| `database/seeders` | Production bootstrap seeders plus explicit local/demo seed flows |
| `resources/views` | Blade UI for each module |
| `tests/Feature` | End-to-end behavior and HTTP contracts |
| `tests/Unit/Services` | Service-layer behavior and calculations |

## Route Groups

| Route Area | Main File | Purpose |
| --- | --- | --- |
| Public | `routes/web.php` | Pricing page, health check, auth redirects |
| Tenant workspace | `routes/web.php` | Workshop operations for authenticated tenant users |
| Super-admin | `routes/web.php` | Platform oversight and tenant administration |
| Developer-only | `routes/web.php` | Local tenant switching and local subscription helpers |
| API v1 | `routes/api.php` | Supported machine-to-machine tenant API |
| Legacy API | `routes/api.php` | Compatibility surface with deprecation headers |
| Console | `routes/console.php` and command classes | Scheduled tasks and privileged operations |

## Controllers

### Tenant Workspace

| Class | Responsibility |
| --- | --- |
| `DashboardController` | Tenant home dashboard and operational overview |
| `CustomerController` | Customer CRUD, search, history, AJAX lookups |
| `CheckinController` | Live check-in flow, auto-created work orders, before photos |
| `WorkOrderController` | Work-order scheduling, execution, completion, invoice generation |
| `TireHotelController` | Tire storage registration, storage lookup, tire-driven work orders |
| `AppointmentController` | Calendar scheduling and rescheduling |
| `InvoiceController` | Invoice viewing, editing, issuing, voiding, and deletion rules |
| `PaymentController` | Payment creation and invoice balance synchronization |
| `FinanceController` | Finance dashboard and invoice/payment listing |
| `ProductController` | Inventory product CRUD, stock operations, imports |
| `ServiceController` | Service catalog CRUD and activation toggles |
| `ProductServiceController` | Unified search/index for products and services |
| `ServiceBayController` | Service bay CRUD and initial seeding |
| `MechanicsController` | Technician account management and invitations under tenant user-access rules |
| `ManagementController` | Tenant reports, settings, users, exports, backup download, and tenant-safe staff management |
| `ProfileController` | User profile editing and account removal |
| `BillingController` | Tenant-facing manual billing and pricing recovery page |
| `SubscriptionController` | Onboarding, setup, and subscription support flows |

### Auth, Admin, And Utility

| Class | Responsibility |
| --- | --- |
| `RegisteredUserController` | Standard registration flow |
| `SocialAuthController` | Google auth and company creation flow |
| `AuthenticatedSessionController` | Login and logout |
| `InviteController` | Mechanic invite acceptance and password setup |
| `AdminDashboardController` | Super-admin KPIs and tenant health |
| `SuperAdminController` | Tenant activation, notes, suspension, archival |
| `HealthController` | Lightweight health check |
| `Dev/TenantSwitchController` | Local-only tenant switching |

### API

| Class | Responsibility |
| --- | --- |
| `Api/CustomerController` | Customer search, detail, and vehicle lookups |
| `Api/CheckinController` | Customer history, detail, active check-ins, and stats |

## Services

| Class | Responsibility |
| --- | --- |
| `TenantProvisioningService` | Transactional tenant and owner creation |
| `TenantLifecycleService` | Tenant archive and purge workflows |
| `InvoiceService` | Invoice number generation, creation, issuing, voiding, payment state, stock handling |
| `CheckinService` | Check-in creation for existing or new vehicles/customers, work order creation from checkin, photo upload |
| `WorkOrderService` | Status transitions, technician assignment, work order completion workflow |
| `TireStorageService` | Tire storage statistics, location assignment, tire intake, tire work order creation |
| `FinanceService` | Financial overview stats with caching |
| `DashboardService` | Dashboard aggregates (cached), schedules, alerts, and system state |
| `ReportingService` | KPI, performance, revenue, and analytics datasets |
| `EventTracker` | Simple event recording for operational metrics |

## Enums

| Enum | Values |
| --- | --- |
| `WorkOrderStatus` | Created, Pending, Scheduled, InProgress, WaitingParts, Completed, Invoiced, Cancelled |
| `CheckinStatus` | Pending, InProgress, Completed, Cancelled |
| `PaymentMethod` | Cash, Card, BankTransfer, Other |
| `InvoiceStatus` | Draft, Issued, Partial, Paid, Void |

## Middleware And Support

| Class | Responsibility |
| --- | --- |
| `TenantMiddleware` | Web tenant resolution and tenant state gating |
| `AuthenticateTenantApiToken` | API bearer-token auth and tenant context loading |
| `UpdateTenantLastSeen` | Throttled tenant activity updates |
| `CheckModuleAccess` | Route-level module permission guard |
| `EnsureTenantTrialActive` | Trial and subscription access enforcement |
| `RequireTireHotelAccess` | Tire hotel feature access gate |
| `AddLegacyApiDeprecationHeaders` | Deprecation signaling for legacy API routes |
| `TenantContext` | Shared tenant context container for runtime access |
| `TenantCache` | Shared tenant and bearer-token cache key generation and invalidation |
| `TenantUserAccess` | Tenant-safe staff-role assignment and target-authorization rules |
| `TenantValidation` | Tenant-aware validation helper rules |
| `TenantScope` | Query-time tenant isolation |
| `BelongsToTenant` | Model-level tenant ownership behavior |

## Models

| Model | Responsibility |
| --- | --- |
| `Tenant` | Tenant account, plan, features, lifecycle state, limits, and access-gate helpers |
| `TenantApiToken` | Hashed bearer token storage, cache invalidation target, and rotation target |
| `User` | Staff identity, tenant membership, roles, invites, and activation state |
| `Customer` | Workshop customer record |
| `Vehicle` | Customer vehicle record |
| `Checkin` | Vehicle arrival and service intake |
| `WorkOrder` | Execution record for workshop work |
| `Invoice` | Canonical billing document and status owner |
| `Payment` | Payment events linked to invoices and customers, including idempotency keys |
| `Product` | Inventory item |
| `Service` | Billable or operational service definition |
| `Appointment` | Scheduled future workshop work |
| `Tire` | Tire storage domain record |
| `ServiceBay` | Physical or logical work bay |
| `AuditLog` | Operator and lifecycle audit history |
| `Event` | Operational event stream for tracking/reporting |

## Commands

| Command | Purpose |
| --- | --- |
| `ops:bootstrap-super-admin` | Seed the configured super-admin and platform roles for production boot |
| `tenant:rotate-api-token` | Rotate tenant bearer token credentials |
| `tenant:purge` | Irreversibly purge a tenant and associated data |
| `crm:clean-demo-data` | Remove demo operational data while keeping config |
| `crm:purge-users` | Soft-delete users safely while protecting owners/superadmins |
| `crm:seed-demo-catalog` | Seed demo products and services |
| `crm:reset-data` | Reset CRM operational data while preserving admin users |
| `mail:test` | Verify email configuration |

## Tests

| Area | Purpose |
| --- | --- |
| `tests/Feature/PublicSurfaceHardeningTest.php` | Locks down public route exposure |
| `tests/Feature/Api/TenantApiAuthTest.php` | Verifies token-based tenant API access |
| `tests/Feature/AuthenticationTest.php` | Verifies tenant lifecycle, inactive login blocking, and invite activation |
| `tests/Feature/ManagementAdminTest.php` | Verifies tenant user-management matrix, last-admin protection, and module gating |
| `tests/Feature/PaymentFlowTest.php` | Verifies payment recording and idempotency behavior |
| `tests/Feature/CheckinTest.php` | Verifies check-in creation, technician gating, and photo-upload rollback |
| `tests/Feature/WorkOrderTest.php` | Verifies work-order isolation and supported status transitions |
| `tests/Feature/TenantIsolationTest.php` | Verifies tenant data boundaries |
| `tests/Feature/PolicyTest.php` | Policy authorization for all 6 policies + 3 gates + cross-tenant |
| `tests/Feature/MiddlewareTest.php` | Tenant lifecycle, module access, plan gating middleware |
| `tests/Feature/NegativeCasesTest.php` | Cross-tenant denial, immutability, overpayment, idempotency |
| `tests/Feature/ConcurrencyTest.php` | Duplicate invoice, unique numbering, stock/payment race conditions |
| `tests/Feature/AuthorizationTest.php` | Role-based access control for finance, bays, products, services |
| `tests/Feature/*` | HTTP behavior and feature workflows |
| `tests/Unit/Services/CheckinServiceTest.php` | Customer deduplication, vehicle reuse, registration flow |
| `tests/Unit/Services/*` | Service-layer calculations and domain transitions |

## Launch Notes

- Tenant role-permission editing is intentionally not part of the production route surface during launch hardening.
- `DatabaseSeeder` is production-safe; use `LocalDemoSeeder` explicitly when local/demo tenant data is needed.

For a class-by-class method inventory, see [function-index.md](function-index.md).
