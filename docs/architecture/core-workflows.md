# Core Workflows

This document explains the major business flows step by step.

## 1. Tenant Registration And Provisioning

Entry points:

- Standard registration through `RegisteredUserController`
- Google OAuth plus company creation through `SocialAuthController`

Flow:

1. User submits registration or completes Google auth.
2. When registration starts from the public pricing page, the selected `plan` is preserved on `/register?plan=...` and posted with the signup form.
3. `RegisterTenantOwner` delegates provisioning work to `TenantProvisioningService`.
4. `TenantProvisioningService` creates the tenant inside a database transaction.
5. The service creates or attaches the owner user.
6. The service assigns the admin role.
7. The service seeds starter products and services.
8. A default tenant API token is issued.
9. Registration side effects such as `Registered` events fire after commit.

Outcome:

- One owner user belongs to one tenant.
- The tenant is trial-enabled and operational.
- Initial catalog and access model exist from first login.

## 2. Customer And Vehicle Management

Entry point:

- `CustomerController`

Flow:

1. Staff opens customer list or create form.
2. Validation runs through `StoreCustomerRequest` or `UpdateCustomerRequest`.
3. Customer email uniqueness is checked within the current tenant.
4. Vehicles and history are accessed through tenant-scoped relations.
5. Search and AJAX endpoints provide lightweight lookup behavior for other modules.

Outcome:

- Customer data becomes the base entity for check-ins, work orders, appointments, billing, and tire storage.

## 3. Check-In To Work Order

Entry point:

- `CheckinController`

Flow:

1. Staff starts a check-in for an existing vehicle or a new registration.
2. `StoreCheckinRequest` validates customer, vehicle, and service data.
3. `CheckinService` creates the check-in and, when needed, customer and vehicle records.
4. The controller derives initial service tasks and parts from selected services.
5. A work order is auto-created for the check-in.
6. Optional before-service photos are stored against the work order.
7. The user is redirected to the work order detail screen.

Outcome:

- The customer arrives once and the system immediately transitions into execution mode through a work order.

## 4. Scheduled Work Orders

Entry point:

- `WorkOrderController`

Flow:

1. Staff schedules work directly without a live check-in.
2. Validation uses tenant-scoped lookups for customer, vehicle, and technician IDs.
3. The work order is created with `scheduled` state.
4. The dashboard and work-order lists surface the job for the assigned team.
5. The technician or manager updates tasks, notes, parts, and status during execution.

Outcome:

- The same work-order system supports both walk-in and pre-booked jobs.

## 5. Work Order Completion, Invoice Generation, And Payment

Entry points:

- `WorkOrderController`
- `InvoiceController`
- `PaymentController`
- `InvoiceService`

Flow:

1. A work order moves to `completed`.
2. `WorkOrderController::completeWorkOrder()` runs inside a transaction.
3. Stock deductions are processed from work-order parts.
4. Related check-ins are completed when applicable.
5. `InvoiceService::createFromWorkOrder()` creates a draft invoice if one does not already exist.
6. Finance users can issue the invoice, which locks immutable fields.
7. Payments are recorded through `PaymentController`.
8. `PaymentObserver` and `InvoiceService::syncPaymentState()` keep paid totals and canonical invoice status aligned.

Canonical invoice states:

- `draft`
- `issued`
- `partial`
- `paid`
- `void`

Derived UI payment states such as `overdue` are presentation concerns, not stored canonical status.

## 6. Tire Hotel Flow

Entry point:

- `TireHotelController`
- `TireStorageService`

Flow:

1. Staff registers seasonal tire storage for an existing or new customer.
2. Tire records are linked to a customer and vehicle.
3. `TireStorageService` assigns or checks storage location.
4. The tire hotel view surfaces stored sets, readiness, and maintenance signals.
5. A tire storage record can generate a work order when pickup or service work is needed.

Outcome:

- Tire storage is treated as a first-class operational module rather than a note attached to customers.

## 7. Appointments

Entry point:

- `AppointmentController`

Flow:

1. Staff schedules an appointment for a customer and vehicle.
2. Tenant-aware validation ensures related entities belong to the current tenant.
3. Calendar event endpoints expose the appointment schedule for UI rendering.
4. Appointments can be rescheduled or deleted as the workshop calendar changes.

Outcome:

- The appointment system feeds expected workload into the workshop schedule before check-in happens.

## 8. Management, Roles, And Users

Entry points:

- `ManagementController`
- `RoleController`

Flow:

1. Tenant admins access management routes guarded by `module:access management`.
2. More sensitive actions use `permission:manage users`, `permission:manage settings`, and `permission:delete records`.
3. Settings updates write directly to the tenant record and tenant settings payload.
4. Role edits update permission assignments.
5. User creation and edits stay tenant-scoped while preserving global uniqueness of `users.email`.

Outcome:

- Admin features are separate from day-to-day workshop operations and should remain permission-bound.

## 9. Super-Admin Tenant Lifecycle

Entry points:

- `AdminDashboardController`
- `SuperAdminController`
- `TenantLifecycleService`
- Artisan commands

Flow:

1. Super-admins review tenants under `/admin`.
2. They can suspend, activate, note, or archive a tenant.
3. Archive behavior flows through `TenantLifecycleService`.
4. Soft-deletable tenant-owned models are archived, users are deactivated, and API tokens are revoked.
5. Irreversible purges are reserved for `tenant:purge`.

Outcome:

- Operationally dangerous platform actions are intentionally split between admin UI and CLI.
