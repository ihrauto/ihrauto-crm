<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SuperAdminController extends Controller
{
    /**
     * Display a listing of all tenants.
     */
    public function index(): View
    {
        $tenants = Tenant::withCount('users')
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('admin.tenants.index', compact('tenants'));
    }

    /**
     * Toggle tenant active status (suspend/activate).
     */
    public function toggleActive(Tenant $tenant): RedirectResponse
    {
        if ($tenant->is_active) {
            $tenant->suspend();
            $message = "Tenant '{$tenant->name}' has been suspended.";
        } else {
            $tenant->activate();
            $message = "Tenant '{$tenant->name}' has been activated.";
        }

        return redirect()->route('admin.tenants.index')
            ->with('success', $message);
    }

    /**
     * Show tenant details.
     */
    public function show(Tenant $tenant): View
    {
        $tenant->loadCount('users', 'customers', 'vehicles');

        return view('admin.tenants.show', compact('tenant'));
    }
}
