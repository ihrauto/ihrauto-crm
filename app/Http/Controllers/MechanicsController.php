<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class MechanicsController extends Controller
{
    /**
     * Display a listing of mechanics (users with technician role).
     */
    public function index()
    {
        $mechanics = User::query()
            ->where('tenant_id', auth()->user()->tenant_id)
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

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make('temppassword123'), // Temporary password
            'tenant_id' => auth()->user()->tenant_id,
            'is_active' => true,
        ]);

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
        // Verify the mechanic belongs to the same tenant
        if ($mechanic->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unauthorized action.');
        }

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
        // Verify the mechanic belongs to the same tenant
        if ($mechanic->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unauthorized action.');
        }

        return view('mechanics.show', compact('mechanic'));
    }

    /**
     * Show the form for editing the specified mechanic.
     */
    public function edit(User $mechanic)
    {
        // Verify the mechanic belongs to the same tenant
        if ($mechanic->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unauthorized action.');
        }

        return view('mechanics.edit', compact('mechanic'));
    }

    /**
     * Update the specified mechanic in storage.
     */
    public function update(Request $request, User $mechanic)
    {
        // Verify the mechanic belongs to the same tenant
        if ($mechanic->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $mechanic->id,
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
        // Verify the mechanic belongs to the same tenant
        if ($mechanic->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unauthorized action.');
        }

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
