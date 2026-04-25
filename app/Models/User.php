<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasRoles {
        HasRoles::assignRole as baseAssignRole;
        HasRoles::syncRoles as baseSyncRoles;
        HasRoles::removeRole as baseRemoveRole;
        HasRoles::hasRole as baseHasRole;
        HasRoles::hasPermissionTo as baseHasPermissionTo;
    }

    use Notifiable, SoftDeletes;

    /**
     * C.9 — invalidate Tenant::canAddUser() cache on create/delete/restore
     * so user-count limits reflect reality immediately after changes.
     */
    protected static function booted(): void
    {
        $flush = function (User $user) {
            if ($user->tenant_id) {
                \Illuminate\Support\Facades\Cache::forget("tenant_{$user->tenant_id}_user_count");
            }
        };

        static::created($flush);
        static::deleted($flush);
        static::restored($flush);
    }

    /**
     * The attributes that are mass assignable.
     *
     * SECURITY: `tenant_id`, `role`, `is_active`, and `email_verified_at` are
     * deliberately excluded. They must be set via `forceFill()` or direct
     * property assignment from trusted code paths (provisioning, invite
     * acceptance, seeder). Never accept these from user-supplied request data.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'hourly_rate',
        'invite_token',
        'invite_expires_at',
        'password',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * SECURITY (M-6): `invite_token` must be in $hidden. It's a
     * single-use 64-char credential that grants account setup. If a
     * controller or debug dump ever serializes a User with a pending
     * invite, the token is enough to claim the account. Treat it like
     * `password`: never exposed.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'invite_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'dashboard_widgets' => 'array',
            // Audit follow-up to DATA-03: staff phone is PII under the
            // same threat model as customer phone (DB dump / backup leak).
            'phone' => 'encrypted',
        ];
    }

    /**
     * C1 (sprint 2026-04-24): with Spatie teams=true, role assignments
     * carry a `team_id` on the pivot that must match the current
     * `PermissionRegistrar::getPermissionsTeamId()` at lookup time.
     * Whenever this user has a tenant, push that tenant id into the
     * registrar before delegating to Spatie so the pivot row is
     * written scoped from the start. Super-admin users (tenant_id =
     * null) keep the global semantics (pivot team_id = null).
     */
    public function assignRole(...$roles)
    {
        $this->pushTenantTeamContext();

        return $this->baseAssignRole(...$roles);
    }

    public function syncRoles(...$roles)
    {
        $this->pushTenantTeamContext();

        return $this->baseSyncRoles(...$roles);
    }

    public function removeRole($role)
    {
        $this->pushTenantTeamContext();

        return $this->baseRemoveRole($role);
    }

    /**
     * Push this user's tenant context before deferring to Spatie so
     * direct calls (e.g. $user->hasRole('admin') in a job or test)
     * resolve against the user's own tenant — not whichever tenant the
     * last request / last assignRole happened to set.
     */
    public function hasRole($roles, ?string $guard = null): bool
    {
        $this->pushTenantTeamContext();

        return $this->baseHasRole($roles, $guard);
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $this->pushTenantTeamContext();

        return $this->baseHasPermissionTo($permission, $guardName);
    }

    private function pushTenantTeamContext(): void
    {
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        if (! $registrar->teams) {
            return;
        }

        // Always push — including null for tenant-less users (super-admin) —
        // so this user's role assignment never inherits a stale team id
        // from a prior caller. `setPermissionsTeamId(null)` is the correct
        // "global / platform-level" scope.
        $registrar->setPermissionsTeamId(! empty($this->tenant_id) ? $this->tenant_id : null);
    }

    /**
     * Check if user has admin role
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user has manager role
     */
    public function isManager(): bool
    {
        return $this->hasAnyRole(['admin', 'manager']);
    }

    /**
     * Check if user can perform action based on tenant limits
     */
    public function canPerformAction(string $action): bool
    {
        if (! $this->tenant) {
            return false;
        }

        return match ($action) {
            'add_user' => $this->tenant->canAddUser(),
            'add_customer' => $this->tenant->canAddCustomer(),
            'add_vehicle' => $this->tenant->canAddVehicle(),
            default => true,
        };
    }

    /**
     * Get work orders assigned to user
     */
    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class, 'technician_id');
    }
}
