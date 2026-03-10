# Function Index

This is a navigation-focused inventory of classes and methods under `app/`. It is meant to help engineers find entrypoints quickly and understand the current code surface.

Snapshot date: 2026-03-10

## Actions And Commands

| File | Class | Methods |
| --- | --- | --- |
| `app/Actions/Auth/RegisterTenantOwner.php` | `RegisterTenantOwner` | `__construct`, `handle` |
| `app/Console/Commands/BootstrapSuperAdminCommand.php` | `BootstrapSuperAdminCommand` | `handle` |
| `app/Console/Commands/CleanDemoDataCommand.php` | `CleanDemoDataCommand` | `handle`, `processTenant` |
| `app/Console/Commands/PurgeTenantCommand.php` | `PurgeTenantCommand` | `handle` |
| `app/Console/Commands/PurgeUsersCommand.php` | `PurgeUsersCommand` | `handle`, `processTenant`, `getProtectedUserIds`, `reassignDependencies` |
| `app/Console/Commands/ResetCRMData.php` | `ResetCRMData` | `handle` |
| `app/Console/Commands/RotateTenantApiTokenCommand.php` | `RotateTenantApiTokenCommand` | `handle` |
| `app/Console/Commands/SeedDemoCatalogCommand.php` | `SeedDemoCatalogCommand` | `handle`, `processTenant` |
| `app/Console/Commands/TestEmailCommand.php` | `TestEmailCommand` | `handle` |

## Controllers

| File | Class | Methods |
| --- | --- | --- |
| `app/Http/Controllers/Admin/AdminDashboardController.php` | `AdminDashboardController` | `index`, `buildMetrics`, `getHealthMetrics`, `getGrowthMetrics`, `calculateVerifiedPercentage`, `getUsageMetrics`, `getRiskMetrics` |
| `app/Http/Controllers/Admin/SuperAdminController.php` | `SuperAdminController` | `index`, `show`, `toggleActive`, `addBonusDays`, `suspend`, `activate`, `addNote`, `updateNote`, `deleteNote`, `destroy`, `logAction` |
| `app/Http/Controllers/Api/CheckinController.php` | `Api\\CheckinController` | `getCustomerHistory`, `getCustomerDetails`, `getActiveCheckins`, `getStatistics` |
| `app/Http/Controllers/Api/CustomerController.php` | `Api\\CustomerController` | `search`, `show`, `vehicles` |
| `app/Http/Controllers/Api/TireController.php` | `Api\\TireController` | `none` |
| `app/Http/Controllers/AppointmentController.php` | `AppointmentController` | `index`, `events`, `reschedule`, `store`, `update`, `destroy` |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | `AuthenticatedSessionController` | `create`, `store`, `destroy` |
| `app/Http/Controllers/Auth/ConfirmablePasswordController.php` | `ConfirmablePasswordController` | `show`, `store` |
| `app/Http/Controllers/Auth/EmailVerificationNotificationController.php` | `EmailVerificationNotificationController` | `store` |
| `app/Http/Controllers/Auth/EmailVerificationPromptController.php` | `EmailVerificationPromptController` | `__invoke` |
| `app/Http/Controllers/Auth/InviteController.php` | `InviteController` | `showSetupForm`, `setup` |
| `app/Http/Controllers/Auth/NewPasswordController.php` | `NewPasswordController` | `create`, `store` |
| `app/Http/Controllers/Auth/PasswordController.php` | `PasswordController` | `update` |
| `app/Http/Controllers/Auth/PasswordResetLinkController.php` | `PasswordResetLinkController` | `create`, `store` |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | `RegisteredUserController` | `create`, `store` |
| `app/Http/Controllers/Auth/SocialAuthController.php` | `SocialAuthController` | `redirectToGoogle`, `handleGoogleCallback`, `showCreateCompany`, `storeCompany` |
| `app/Http/Controllers/Auth/VerifyEmailController.php` | `VerifyEmailController` | `__invoke`, `redirectAfterVerification` |
| `app/Http/Controllers/CheckinController.php` | `CheckinController` | `index`, `__construct`, `store`, `show`, `update`, `calculateAverageServiceTime`, `getServiceBayStatus`, `seedDefaultBays`, `getCustomerHistory`, `getCustomerDetails` |
| `app/Http/Controllers/Controller.php` | `Controller` | `none` |
| `app/Http/Controllers/CustomerController.php` | `CustomerController` | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `search`, `apiShow`, `history` |
| `app/Http/Controllers/DashboardController.php` | `DashboardController` | `__construct`, `index` |
| `app/Http/Controllers/Dev/TenantSwitchController.php` | `TenantSwitchController` | `index`, `switch`, `clear`, `info` |
| `app/Http/Controllers/FinanceController.php` | `FinanceController` | `index` |
| `app/Http/Controllers/HealthController.php` | `HealthController` | `check` |
| `app/Http/Controllers/InvoiceController.php` | `InvoiceController` | `__construct`, `show`, `edit`, `update`, `issue`, `void`, `destroy` |
| `app/Http/Controllers/ManagementController.php` | `ManagementController` | `__construct`, `index`, `export`, `settings`, `updateSettings`, `notifications`, `pricing`, `reports`, `analytics`, `createUser`, `storeUser`, `editUser`, `updateUser`, `destroyUser`, `downloadBackup` |
| `app/Http/Controllers/MechanicsController.php` | `MechanicsController` | `index`, `create`, `store`, `destroy`, `show`, `edit`, `update`, `invite` |
| `app/Http/Controllers/PaymentController.php` | `PaymentController` | `__construct`, `store` |
| `app/Http/Controllers/ProductController.php` | `ProductController` | `store`, `update`, `destroy`, `stockOperation`, `import`, `downloadTemplate` |
| `app/Http/Controllers/ProductServiceController.php` | `ProductServiceController` | `index`, `search` |
| `app/Http/Controllers/ProfileController.php` | `ProfileController` | `edit`, `update`, `destroy` |
| `app/Http/Controllers/RoleController.php` | `RoleController` | `index`, `update` |
| `app/Http/Controllers/ServiceBayController.php` | `ServiceBayController` | `index`, `store`, `update`, `destroy`, `bulkUpdate`, `seedDefaultBays` |
| `app/Http/Controllers/ServiceController.php` | `ServiceController` | `store`, `update`, `destroy`, `toggle` |
| `app/Http/Controllers/SubscriptionController.php` | `SubscriptionController` | `checkout`, `process`, `onboarding`, `storeSetup`, `markTourComplete` |
| `app/Http/Controllers/TenantController.php` | `TenantController` | `none` |
| `app/Http/Controllers/TireHotelController.php` | `TireHotelController` | `__construct`, `index`, `store`, `storeNewCustomerForwarder`, `storeNewCustomer`, `storeSeasonChange`, `legacyStoreSeasonChange`, `searchByRegistration`, `legacySearchByRegistration`, `show`, `apiShow`, `checkAvailability`, `update`, `destroy`, `generateWorkOrder`, `createTireWorkOrder`, `getUpcomingPickups`, `getMaintenanceAlerts` |
| `app/Http/Controllers/WorkOrderController.php` | `WorkOrderController` | `__construct`, `generateInvoice`, `index`, `board`, `employeeStats`, `showEmployeeStats`, `create`, `store`, `generate`, `edit`, `show`, `update`, `completeWorkOrder`, `jobDetails` |
| `app/Http/Controllers/WorkOrderPhotoController.php` | `WorkOrderPhotoController` | `store`, `destroy` |

## Middleware, Requests, And Resources

| File | Class | Methods |
| --- | --- | --- |
| `app/Http/Middleware/AddLegacyApiDeprecationHeaders.php` | `AddLegacyApiDeprecationHeaders` | `handle` |
| `app/Http/Middleware/AuthenticateTenantApiToken.php` | `AuthenticateTenantApiToken` | `__construct`, `handle`, `touchToken`, `unauthorized` |
| `app/Http/Middleware/CheckModuleAccess.php` | `CheckModuleAccess` | `handle` |
| `app/Http/Middleware/EnsureTenantTrialActive.php` | `EnsureTenantTrialActive` | `handle` |
| `app/Http/Middleware/RequireTireHotelAccess.php` | `RequireTireHotelAccess` | `handle` |
| `app/Http/Middleware/TenantMiddleware.php` | `TenantMiddleware` | `__construct`, `handle`, `resolveTenant`, `getTenantFromAuth`, `getTenantFromSubdomain`, `getTenantFromRoute`, `getTenantFromDomain`, `getTenantFromSession`, `handleMissingTenant`, `handleInactiveTenant`, `handleExpiredTenant`, `isDevelopmentRoute` |
| `app/Http/Middleware/TrustProxies.php` | `TrustProxies` | `none` |
| `app/Http/Middleware/UpdateTenantLastSeen.php` | `UpdateTenantLastSeen` | `handle` |
| `app/Http/Requests/Auth/LoginRequest.php` | `LoginRequest` | `authorize`, `rules`, `authenticate`, `ensureIsNotRateLimited`, `throttleKey` |
| `app/Http/Requests/ProfileUpdateRequest.php` | `ProfileUpdateRequest` | `rules` |
| `app/Http/Requests/StoreCheckinRequest.php` | `StoreCheckinRequest` | `authorize`, `rules`, `messages`, `attributes` |
| `app/Http/Requests/StoreCustomerRequest.php` | `StoreCustomerRequest` | `authorize`, `rules`, `messages`, `attributes` |
| `app/Http/Requests/StoreNewTireCustomerRequest.php` | `StoreNewTireCustomerRequest` | `authorize`, `rules` |
| `app/Http/Requests/StoreWorkOrderRequest.php` | `StoreWorkOrderRequest` | `authorize`, `rules`, `messages`, `attributes` |
| `app/Http/Requests/UpdateCheckinRequest.php` | `UpdateCheckinRequest` | `authorize`, `rules` |
| `app/Http/Requests/UpdateCustomerRequest.php` | `UpdateCustomerRequest` | `authorize`, `rules`, `messages`, `attributes` |
| `app/Http/Requests/UpdateTireRequest.php` | `UpdateTireRequest` | `authorize`, `rules`, `messages`, `safeFields` |
| `app/Http/Requests/UpdateWorkOrderRequest.php` | `UpdateWorkOrderRequest` | `authorize`, `rules`, `messages`, `prepareForValidation` |
| `app/Http/Resources/CheckinResource.php` | `CheckinResource` | `toArray` |
| `app/Http/Resources/CustomerResource.php` | `CustomerResource` | `toArray` |
| `app/Http/Resources/VehicleResource.php` | `VehicleResource` | `toArray` |

## Models

| File | Class | Methods |
| --- | --- | --- |
| `app/Models/Appointment.php` | `Appointment` | `customer`, `vehicle`, `getDurationAttribute`, `getStatusBadgeColorAttribute`, `getTypeLabelAttribute` |
| `app/Models/AuditLog.php` | `AuditLog` | `user` |
| `app/Models/Checkin.php` | `Checkin` | `customer`, `vehicle`, `workOrder`, `getStatusBadgeColorAttribute`, `getPriorityBadgeColorAttribute`, `getDurationAttribute`, `formatDuration`, `getTimeAgoAttribute`, `scopeActive`, `scopePending`, `scopeInProgress`, `scopeCompleted`, `scopeToday` |
| `app/Models/Customer.php` | `Customer` | `vehicles`, `checkins`, `tires`, `invoices`, `quotes`, `payments`, `getFullNameAttribute`, `getActiveVehiclesAttribute`, `getActiveCheckinsAttribute`, `getStoredTiresAttribute` |
| `app/Models/Event.php` | `Event` | `tenant`, `user` |
| `app/Models/Invoice.php` | `Invoice` | `boot`, `isEditable`, `isDraft`, `isIssued`, `isVoid`, `isPaid`, `canBeVoided`, `customer`, `vehicle`, `workOrder`, `quote`, `items`, `payments`, `issuedByUser`, `voidedByUser`, `getBalanceAttribute`, `getPaymentStatusAttribute`, `getStatusBadgeColorAttribute`, `recalculate` |
| `app/Models/InvoiceItem.php` | `InvoiceItem` | `invoice` |
| `app/Models/InvoiceSequence.php` | `InvoiceSequence` | `none` |
| `app/Models/Payment.php` | `Payment` | `invoice`, `customer` |
| `app/Models/Product.php` | `Product` | `movements`, `services` |
| `app/Models/Quote.php` | `Quote` | `customer`, `vehicle`, `workOrder`, `items`, `invoice` |
| `app/Models/QuoteItem.php` | `QuoteItem` | `quote` |
| `app/Models/Service.php` | `Service` | `products` |
| `app/Models/ServiceBay.php` | `ServiceBay` | `scopeActive`, `scopeOrdered` |
| `app/Models/StockMovement.php` | `StockMovement` | `product`, `user`, `reference` |
| `app/Models/StorageSection.php` | `StorageSection` | `warehouse` |
| `app/Models/Tenant.php` | `Tenant` | `boot`, `users`, `customers`, `vehicles`, `checkins`, `tires`, `workOrders`, `apiTokens`, `scopeActive`, `scopeTrial`, `scopeSubscribed`, `scopeExpired`, `scopeByPlan`, `getFullUrlAttribute`, `getIsExpiredAttribute`, `getDaysRemainingAttribute`, `canAddUser`, `canAddCustomer`, `canAddVehicle`, `hasFeature`, `enableFeature`, `disableFeature`, `updateLastActivity`, `suspend`, `activate`, `convertToSubscription`, `getPlanLimits`, `canCreateWorkOrder`, `getRemainingWorkOrdersAttribute`, `hasTireHotel`, `hasApiAccess`, `isOnTrial`, `isTrialExpired`, `getStatistics` |
| `app/Models/TenantApiToken.php` | `TenantApiToken` | `tenant`, `issue`, `findActiveByPlainTextToken`, `revoke` |
| `app/Models/Tire.php` | `Tire` | `customer`, `vehicle`, `getFullDescriptionAttribute`, `getSeasonBadgeColorAttribute`, `getConditionBadgeColorAttribute`, `getStatusBadgeColorAttribute`, `getStorageDurationAttribute`, `getStorageDaysAttribute`, `needsInspection`, `isOverdue`, `scopeStored`, `scopeReadyForPickup`, `scopeWinterTires`, `scopeSummerTires`, `scopeAllSeasonTires`, `scopeByLocation` |
| `app/Models/User.php` | `User` | `casts`, `isAdmin`, `isManager`, `canPerformAction`, `workOrders` |
| `app/Models/Vehicle.php` | `Vehicle` | `customer`, `checkins`, `tires`, `getFullNameAttribute`, `getDisplayNameAttribute`, `getActiveCheckinsAttribute`, `getStoredTiresAttribute` |
| `app/Models/Warehouse.php` | `Warehouse` | `sections` |
| `app/Models/WorkOrder.php` | `WorkOrder` | `checkin`, `customer`, `vehicle`, `technician`, `invoice`, `quote`, `photos`, `getStatusBadgeColorAttribute`, `getStatusLabelAttribute` |
| `app/Models/WorkOrderPhoto.php` | `WorkOrderPhoto` | `workOrder`, `uploader`, `getUrlAttribute`, `isBeforePhoto`, `isAfterPhoto` |

## Policies, Observers, Notifications, And Support

| File | Class | Methods |
| --- | --- | --- |
| `app/Notifications/MechanicInviteNotification.php` | `MechanicInviteNotification` | `__construct`, `via`, `toMail`, `toArray` |
| `app/Observers/CustomerObserver.php` | `CustomerObserver` | `deleted`, `restored` |
| `app/Observers/PaymentObserver.php` | `PaymentObserver` | `created`, `updated`, `deleted`, `restored`, `updateInvoiceBalance` |
| `app/Policies/AppointmentPolicy.php` | `AppointmentPolicy` | `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete` |
| `app/Policies/CheckinPolicy.php` | `CheckinPolicy` | `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`, `complete` |
| `app/Policies/CustomerPolicy.php` | `CustomerPolicy` | `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete` |
| `app/Policies/InvoicePolicy.php` | `InvoicePolicy` | `viewAny`, `view`, `create`, `update`, `delete`, `issue`, `void`, `markPaid`, `restore`, `forceDelete` |
| `app/Policies/ProductPolicy.php` | `ProductPolicy` | `viewAny`, `view`, `create`, `update`, `delete`, `adjustStock`, `restore`, `forceDelete` |
| `app/Policies/TirePolicy.php` | `TirePolicy` | `viewAny`, `view`, `create`, `update`, `delete`, `markReadyForPickup`, `restore`, `forceDelete` |
| `app/Policies/VehiclePolicy.php` | `VehiclePolicy` | `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete` |
| `app/Policies/WorkOrderPolicy.php` | `WorkOrderPolicy` | `viewAny`, `view`, `create`, `update`, `delete`, `complete`, `generateInvoice`, `restore`, `forceDelete` |
| `app/Providers/AppServiceProvider.php` | `AppServiceProvider` | `register`, `boot` |
| `app/Scopes/TenantScope.php` | `TenantScope` | `apply`, `extend` |
| `app/Support/TenantContext.php` | `TenantContext` | `current`, `id`, `apiToken`, `set`, `clear`, `configureTenantDatabase` |
| `app/Support/TenantValidation.php` | `TenantValidation` | `exists`, `unique` |
| `app/Traits/Auditable.php` | `Auditable` | `bootAuditable`, `logAudit` |
| `app/Traits/BelongsToTenant.php` | `BelongsToTenant` | `bootBelongsToTenant`, `tenant`, `scopeForTenant`, `scopeWithoutTenantScope`, `isOwnedByCurrentTenant`, `isOwnedByTenant` |
| `app/Traits/ChecksTechnicianAvailability.php` | `ChecksTechnicianAvailability` | `isTechnicianBusy`, `getAvailableTechnician`, `getBusyTechnicianIds` |
| `app/View/Components/AppLayout.php` | `AppLayout` | `render` |
| `app/View/Components/GuestLayout.php` | `GuestLayout` | `render` |

## Services

| File | Class | Methods |
| --- | --- | --- |
| `app/Services/CheckinService.php` | `CheckinService` | `createForExistingVehicle`, `createWithNewRegistration`, `createCustomer`, `findVehicle`, `createVehicle` |
| `app/Services/DashboardService.php` | `DashboardService` | `getStats`, `getRecentActivities`, `getCalendarWorkOrders`, `getTodaysSchedule`, `getTechnicianStatus`, `getAlerts`, `getRecentCheckins`, `getTireOperations`, `getServiceBayStatus`, `getSystemStatus` |
| `app/Services/EventTracker.php` | `EventTracker` | `track`, `trackSimple` |
| `app/Services/InvoiceService.php` | `InvoiceService` | `generateInvoiceNumber`, `createFromWorkOrder`, `issueInvoice`, `voidInvoice`, `markAsPaid`, `syncPaymentState`, `updateDraftInvoice`, `buildInvoiceItems`, `processStockDeductions`, `reverseStockDeductions` |
| `app/Services/ReportingService.php` | `ReportingService` | `getKPIs`, `getMonthlyRevenue`, `getServiceCompletionRate`, `getStorageUtilization`, `getPerformanceMetrics`, `getCustomerAnalytics`, `getServiceAnalytics`, `getTireAnalytics`, `getSystemAlerts` |
| `app/Services/TenantLifecycleService.php` | `TenantLifecycleService` | `archive`, `purge`, `log` |
| `app/Services/TenantProvisioningService.php` | `TenantProvisioningService` | `provisionOwner`, `provisionTenantForExistingUser`, `createTenant`, `assignAdminRole`, `uniqueSlug`, `planLimits`, `seedStarterCatalog` |
| `app/Services/TireStorageService.php` | `TireStorageService` | `getStatistics`, `calculateStorageUtilization`, `getStorageMap`, `isLocationAvailable`, `getNextAvailableLocation`, `assignStorageLocation`, `storeNewCustomerTires`, `getSectionColor`, `findOrCreateCustomer`, `createVehicle` |
