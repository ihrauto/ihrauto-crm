<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\TenantUserAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class MechanicsController extends Controller
{
    public function __construct(
        private readonly TenantUserAccess $tenantUserAccess
    ) {}

    /**
     * Display a listing of mechanics (users with technician role).
     */
    public function index()
    {
        $mechanics = User::query()
            ->where('tenant_id', tenant_id())
            ->whereHas('roles', function ($query) {
                $query->where('name', 'technician');
            })
            ->orderBy('name')
            ->paginate(15);

        return view('mechanics.index', compact('mechanics'));
    }

    /**
     * Show the form for creating a new mechanic.
     */
    public function create()
    {
        return view('mechanics.create');
    }

    /**
     * Store a newly created mechanic in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:50',
        ]);

        $this->tenantUserAccess->ensureCanAssignRole($request->user(), 'technician');

        // B-01: enforce plan user limit.
        \App\Support\PlanQuota::assertCanAddUser();

        $inviteToken = bin2hex(random_bytes(32));

        // Create user — tenant_id / role / is_active are protected fields
        // set via forceFill rather than mass assignment.
        $user = new User;
        $user->fill([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make(Str::random(64)),
            'invite_token' => $inviteToken,
            'invite_expires_at' => now()->addHours(48),
        ]);
        $user->forceFill([
            'tenant_id' => tenant_id(),
            'is_active' => false,
            'role' => 'technician',
        ])->save();

        // Ensure technician role exists and assign it
        $technicianRole = Role::firstOrCreate(
            ['name' => 'technician', 'guard_name' => 'web']
        );
        $user->assignRole($technicianRole);

        return redirect()->route('mechanics.index')
            ->with('success', "Mechanic '{$user->name}' has been added successfully!");
    }

    /**
     * Remove the specified mechanic from storage.
     */
    public function destroy(User $mechanic)
    {
        $this->tenantUserAccess->ensureMechanicTarget(auth()->user(), $mechanic);

        $name = $mechanic->name;

        // Remove role assignments
        $mechanic->roles()->detach();

        // Delete the user
        $mechanic->delete();

        return redirect()->route('mechanics.index')
            ->with('success', "Mechanic '{$name}' has been removed.");
    }

    /**
     * Display the specified mechanic.
     */
    public function show(User $mechanic)
    {
        $this->tenantUserAccess->ensureMechanicTarget(auth()->user(), $mechanic);

        return view('mechanics.show', compact('mechanic'));
    }

    /**
     * Show the form for editing the specified mechanic.
     */
    public function edit(User $mechanic)
    {
        $this->tenantUserAccess->ensureMechanicTarget(auth()->user(), $mechanic);

        return view('mechanics.edit', compact('mechanic'));
    }

    /**
     * Update the specified mechanic in storage.
     */
    public function update(Request $request, User $mechanic)
    {
        $this->tenantUserAccess->ensureMechanicTarget(auth()->user(), $mechanic);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$mechanic->id,
            'phone' => 'nullable|string|max:50',
            'hourly_rate' => 'nullable|numeric|min:0',
        ]);

        $mechanic->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'hourly_rate' => $request->hourly_rate,
        ]);

        return redirect()->route('mechanics.show', $mechanic)
            ->with('success', "Mechanic '{$mechanic->name}' has been updated.");
    }

    /**
     * Send invite email to mechanic.
     */
    public function invite(User $mechanic)
    {
        $this->tenantUserAccess->ensureMechanicTarget(auth()->user(), $mechanic);

        // Generate a unique token
        $token = bin2hex(random_bytes(32));

        // Save token and expiration
        $mechanic->update([
            'invite_token' => $token,
            'invite_expires_at' => now()->addHours(48),
        ]);

        // Get tenant name for email
        $tenant = auth()->user()->tenant;
        $tenantName = $tenant ? $tenant->name : 'IHR Auto';

        // Generate invite URL
        $inviteUrl = route('invite.setup', ['token' => $token]);

        // Send notification
        $mechanic->notify(new \App\Notifications\MechanicInviteNotification($inviteUrl, $tenantName));

        return redirect()->route('mechanics.show', $mechanic)
            ->with('success', "Invitation email sent to {$mechanic->email}!");
    }
}
