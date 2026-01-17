<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'hourly_rate',
        'invite_token',
        'invite_expires_at',
        'password',
        'role',
        'is_active',
        'email_verified_at',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
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
        if (!$this->tenant) {
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
