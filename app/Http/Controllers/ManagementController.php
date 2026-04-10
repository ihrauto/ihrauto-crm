<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ReportingService;
use App\Support\TenantCache;
use App\Support\TenantUserAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManagementController extends Controller
{
    protected ReportingService $reportingService;

    public function __construct(
        ReportingService $reportingService,
        private readonly TenantUserAccess $tenantUserAccess
    ) {
        $this->reportingService = $reportingService;
    }

    public function index()
    {
        $kpis = $this->reportingService->getKPIs();
        $performance = $this->reportingService->getPerformanceMetrics();
        $customer_analytics = $this->reportingService->getCustomerAnalytics();
        $service_analytics = $this->reportingService->getServiceAnalytics();
        $tire_analytics = $this->reportingService->getTireAnalytics();
        $alerts = $this->reportingService->getSystemAlerts();
        $users = \App\Models\User::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('management', compact(
            'kpis',
            'performance',
            'customer_analytics',
            'service_analytics',
            'tire_analytics',
            'alerts',
            'users'
        ));
    }

    public function export()
    {
        \Illuminate\Support\Facades\Gate::authorize('perform-admin-actions');

        $customers = \App\Models\Customer::all();
        $filename = 'crm-customers-'.date('Y-m-d').'.csv';

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($customers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Email', 'Phone', 'Created At']);

            foreach ($customers as $customer) {
                fputcsv($file, [
                    $customer->id,
                    $customer->name,
                    $customer->email,
                    $customer->phone,
                    $customer->created_at,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function settings()
    {
        return view('management.settings');
    }

    public function updateSettings(Request $request)
    {
        // Require admin permissions
        \Illuminate\Support\Facades\Gate::authorize('perform-admin-actions');

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'city' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'uid_number' => 'nullable|string|max:50',
            'vat_registered' => 'boolean',
            'vat_number' => 'nullable|required_if:vat_registered,1|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:50',
            'account_holder' => 'nullable|string|max:100',
            'invoice_email' => 'nullable|email|max:255',
            'invoice_phone' => 'nullable|string|max:50',

            // Financial Settings
            'currency' => 'required|string|size:3',
            'tax_rate' => 'required|numeric|min:0|max:100',

            // Modules
            'module_tire_hotel' => 'nullable|string',
            'module_checkin' => 'nullable|string',
        ]);

        $tenant = auth()->user()->tenant;

        $tenant->update([
            'name' => $validated['company_name'],
            'address' => $validated['address'],
            'postal_code' => $validated['postal_code'],
            'city' => $validated['city'],
            'country' => $validated['country'],
            'uid_number' => $validated['uid_number'],
            'vat_registered' => $request->has('vat_registered') && $request->vat_registered == '1',
            'vat_number' => $validated['vat_number'],
            'bank_name' => $validated['bank_name'],
            'iban' => $validated['iban'],
            'account_holder' => $validated['account_holder'],
            'invoice_email' => $validated['invoice_email'],
            'invoice_phone' => $validated['invoice_phone'],
            'currency' => $validated['currency'],
        ]);

        // Update Features based on modules
        $features = $tenant->features ?? [];

        // Helper to update features array
        $updateFeature = function ($featureKey, $isEnabled) use (&$features) {
            if ($isEnabled && ! in_array($featureKey, $features)) {
                $features[] = $featureKey;
            } elseif (! $isEnabled && in_array($featureKey, $features)) {
                $features = array_diff($features, [$featureKey]);
            }
        };

        // We only toggle these if plan allows? For now we assume admins can toggle if they have the plan
        // Ideally we check if plan supports it first.

        $updateFeature('tire_hotel', $request->has('module_tire_hotel'));
        $updateFeature('vehicle_checkin', $request->has('module_checkin'));

        $tenant->features = array_values($features);

        // Settings JSON for other config
        $settings = $tenant->settings ?? [];
        $settings['tax_rate'] = $validated['tax_rate'];
        $tenant->settings = $settings;

        $tenant->save();
        TenantCache::forgetTenant($tenant);

        return redirect()->route('management.settings')->with('success', 'Company information and settings updated successfully.');
    }

    public function notifications()
    {
        abort(404);
    }

    public function pricing()
    {
        return redirect()->route('billing.pricing');
    }

    public function reports()
    {
        $kpis = $this->reportingService->getKPIs();
        $performance = $this->reportingService->getPerformanceMetrics();
        $customer_analytics = $this->reportingService->getCustomerAnalytics();
        $tire_analytics = $this->reportingService->getTireAnalytics();
        $alerts = $this->reportingService->getSystemAlerts();

        return view('management.reports', compact(
            'kpis',
            'performance',
            'customer_analytics',
            'tire_analytics',
            'alerts'
        ));
    }

    public function analytics()
    {
        abort(404);
    }

    public function createUser()
    {
        $roles = $this->tenantUserAccess->assignableRolesFor(auth()->user());

        return view('management.users.create', compact('roles'));
    }

    public function storeUser(Request $request)
    {
        $allowedRoles = $this->tenantUserAccess->assignableRolesFor($request->user());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => ['required', 'string', Rule::in($allowedRoles)],
        ], [
            'email.unique' => 'This email address is already in use on IHRAUTO CRM. User emails are unique across the whole platform.',
        ]);

        $user = new User;
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->password = \Illuminate\Support\Facades\Hash::make($validated['password']);
        $user->role = $validated['role'];

        // Handle tenant assignment (if applicable to current user context)
        if (auth()->check() && tenant_id()) {
            $user->tenant_id = tenant_id();
        }

        $user->save();
        $user->assignRole($validated['role']);

        return redirect()->route('management')->with('success', 'New user account created successfully.');
    }

    public function editUser(User $user)
    {
        $this->tenantUserAccess->ensureCanManageUser(auth()->user(), $user);

        $roles = $this->tenantUserAccess->assignableRolesFor(auth()->user());

        return view('management.users.edit', compact('user', 'roles'));
    }

    public function updateUser(Request $request, User $user)
    {
        $allowedRoles = $this->tenantUserAccess->assignableRolesFor($request->user());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'password' => 'nullable|string|min:8',
        ], [
            'email.unique' => 'This email address is already in use on IHRAUTO CRM. User emails are unique across the whole platform.',
        ]);

        $this->tenantUserAccess->ensureCanTransitionUserRole($request->user(), $user, $validated['role']);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];

        if (! empty($validated['password'])) {
            $user->password = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        return redirect()->route('management')->with('success', 'User account updated successfully.');
    }

    public function destroyUser(\App\Models\User $user)
    {
        \Illuminate\Support\Facades\Gate::authorize('delete-records');
        $this->tenantUserAccess->ensureCanDeleteUser(auth()->user(), $user);

        $user->delete();

        return back()->with('success', 'User account deleted successfully.');
    }

    /**
     * Whitelist of fields per model that are safe to include in a tenant-side backup.
     *
     * CRITICAL SECURITY NOTE: the previous implementation used $record->toJson(),
     * which serialised every fillable field. That included invite_token on User
     * (password is already hidden), the free-form `data` blob on audit_logs (which
     * may contain before/after snapshots of any column, including password hashes
     * on user update events), and any future sensitive field added to a fillable.
     *
     * This list is an explicit allow-list. To add a field, you must list it here.
     * Models not listed (User, TenantApiToken, AuditLog) are intentionally excluded
     * from the tenant-downloadable backup.
     */
    private const BACKUP_SAFE_FIELDS = [
        \App\Models\Customer::class => [
            'id', 'name', 'email', 'phone', 'address', 'city', 'postal_code',
            'notes', 'is_active', 'created_at', 'updated_at',
        ],
        \App\Models\Vehicle::class => [
            'id', 'customer_id', 'license_plate', 'make', 'model', 'year',
            'color', 'mileage', 'vin', 'created_at', 'updated_at',
        ],
        \App\Models\Checkin::class => [
            'id', 'customer_id', 'vehicle_id', 'service_type', 'service_description',
            'priority', 'status', 'checkin_time', 'checkout_time', 'estimated_cost',
            'actual_cost', 'technician_notes', 'created_at', 'updated_at',
        ],
        \App\Models\WorkOrder::class => [
            'id', 'checkin_id', 'customer_id', 'vehicle_id', 'technician_id',
            'status', 'service_tasks', 'customer_issues', 'technician_notes',
            'parts_used', 'started_at', 'completed_at', 'scheduled_at',
            'estimated_minutes', 'service_bay', 'created_at', 'updated_at',
        ],
        \App\Models\Invoice::class => [
            'id', 'invoice_number', 'work_order_id', 'customer_id', 'vehicle_id',
            'status', 'issue_date', 'due_date', 'subtotal', 'tax_total',
            'discount_total', 'total', 'paid_amount', 'notes',
            'created_at', 'updated_at',
        ],
        \App\Models\Payment::class => [
            'id', 'invoice_id', 'amount', 'method', 'payment_date',
            'transaction_reference', 'notes', 'created_at',
            // NOTE: idempotency_key is intentionally excluded — it is an internal
            // implementation detail and may encode user_id in derived form.
        ],
        \App\Models\Product::class => [
            'id', 'name', 'description', 'sku', 'price', 'cost', 'stock_quantity',
            'min_stock_quantity', 'unit', 'is_active', 'category',
            'created_at', 'updated_at',
        ],
        \App\Models\Service::class => [
            'id', 'name', 'description', 'code', 'price', 'duration_minutes',
            'category', 'is_active', 'created_at', 'updated_at',
        ],
        \App\Models\Tire::class => [
            'id', 'customer_id', 'vehicle_id', 'brand', 'model', 'size', 'season',
            'quantity', 'condition', 'storage_location', 'storage_date',
            'status', 'notes', 'created_at', 'updated_at',
        ],
        \App\Models\Appointment::class => [
            'id', 'customer_id', 'vehicle_id', 'title', 'start_time', 'end_time',
            'status', 'type', 'notes', 'created_at', 'updated_at',
        ],
    ];

    public function downloadBackup()
    {
        \Illuminate\Support\Facades\Gate::authorize('perform-admin-actions');

        $filename = 'crm-backup-'.now()->format('Y-m-d-His').'.json';
        $safeFields = self::BACKUP_SAFE_FIELDS;

        return response()->streamDownload(function () use ($safeFields) {
            echo '{"metadata":'.json_encode([
                'generated_at' => now()->toIso8601String(),
                'version' => '2.0',
                'app_name' => config('app.name'),
                'note' => 'This backup contains business data only. '
                    . 'User accounts, auth tokens, and audit logs are excluded by design.',
            ]);

            foreach ($safeFields as $modelClass => $fields) {
                $key = \Illuminate\Support\Str::snake(class_basename($modelClass)).'s';
                echo ',"'.$key.'":[';
                $first = true;
                foreach ($modelClass::cursor() as $record) {
                    if (! $first) {
                        echo ',';
                    }
                    // Only export whitelisted fields.
                    echo json_encode($record->only($fields));
                    $first = false;
                }
                echo ']';
            }

            echo '}';
        }, $filename, ['Content-Type' => 'application/json']);
    }
}
