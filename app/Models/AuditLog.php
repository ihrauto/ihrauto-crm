<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-scoped audit trail.
 *
 * The BelongsToTenant trait applies the TenantScope global scope so queries
 * from a tenant user context automatically filter to their own audit logs.
 * Super-admin dashboards that need to see all logs across tenants must use
 * `AuditLog::withoutGlobalScopes()` explicitly — this is intentional.
 *
 * Some audit log rows have `tenant_id = null` for system-level events
 * (e.g. tenant provisioning). Those are hidden from tenant queries by design
 * and only visible via withoutGlobalScopes().
 */
class AuditLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'model_type',
        'model_id',
        'changes',
        'ip_address',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
