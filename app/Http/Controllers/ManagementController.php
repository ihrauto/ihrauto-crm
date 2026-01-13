<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ReportingService;
use Spatie\Permission\Models\Role;

class ManagementController extends Controller
{
    protected ReportingService $reportingService;

    public function __construct(ReportingService $reportingService)
    {
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
        $users = \App\Models\User::all();

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
        $filename = 'crm-customers-' . date('Y-m-d') . '.csv';

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

    public function audit()
    {
        $logs = \App\Models\AuditLog::with('user')->latest()->paginate(20);

        return view('management.audit', compact('logs'));
    }

    public function settings()
    {
        return view('management.settings');
    }

    public function updateSettings(\Illuminate\Http\Request $request)
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
            if ($isEnabled && !in_array($featureKey, $features)) {
                $features[] = $featureKey;
            } elseif (!$isEnabled && in_array($featureKey, $features)) {
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

        return redirect()->route('management.settings')->with('success', 'Company information and settings updated successfully.');
    }

    public function notifications()
    {
        return back()->with('info', 'Notification system is currently being configured.');
    }

    public function pricing()
    {
        return back()->with('info', 'Pricing management module is coming soon.');
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
        return back()->with('info', 'Performance analytics dashboard is being updated.');
    }

    public function createUser()
    {
        $roles = Role::all();

        return view('management.users.create', compact('roles'));
    }

    public function storeUser(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = new User;
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->password = \Illuminate\Support\Facades\Hash::make($validated['password']);
        $user->role = $validated['role'];

        // Handle tenant assignment (if applicable to current user context)
        if (auth()->check() && auth()->user()->tenant_id) {
            $user->tenant_id = auth()->user()->tenant_id;
        }

        $user->save();
        $user->assignRole($validated['role']);

        return redirect()->route('management')->with('success', 'New user account created successfully.');
    }

    public function editUser(User $user)
    {
        $roles = Role::all();

        return view('management.users.edit', compact('user', 'roles'));
    }

    public function updateUser(\Illuminate\Http\Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|string|exists:roles,name',
            'password' => 'nullable|string|min:8',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];

        if (!empty($validated['password'])) {
            $user->password = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        return redirect()->route('management')->with('success', 'User account updated successfully.');
    }

    public function destroyUser(\App\Models\User $user)
    {
        \Illuminate\Support\Facades\Gate::authorize('delete-records');

        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return back()->with('success', 'User account deleted successfully.');
    }

    public function downloadBackup()
    {
        \Illuminate\Support\Facades\Gate::authorize('perform-admin-actions');

        $data = [
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'version' => '1.0',
                'app_name' => config('app.name'),
            ],
            'users' => \App\Models\User::all(),
            'customers' => \App\Models\Customer::all(),
            'checkins' => \App\Models\Checkin::all(),
            'tires' => \App\Models\Tire::all(),
            'audit_logs' => \App\Models\AuditLog::all(),
        ];

        // Safely try to add other models if they exist
        if (class_exists('App\Models\WorkOrder')) {
            $data['work_orders'] = \App\Models\WorkOrder::all();
        }
        if (class_exists('App\Models\Invoice')) {
            $data['invoices'] = \App\Models\Invoice::all();
        }
        if (class_exists('App\Models\Payment')) {
            $data['payments'] = \App\Models\Payment::all();
        }
        if (class_exists('App\Models\Product')) {
            $data['products'] = \App\Models\Product::all();
        }
        if (class_exists('App\Models\Service')) {
            $data['services'] = \App\Models\Service::all();
        }

        $filename = 'crm-backup-' . now()->format('Y-m-d-His') . '.json';

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT);
        }, $filename, ['Content-Type' => 'application/json']);
    }
}
