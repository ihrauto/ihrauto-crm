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

    /**
     * Audit-log rows whose action matches this list are administrative
     * metadata (super-admin notes on a tenant) and MAY be edited or
     * deleted through the admin UI. Everything else is a compliance
     * audit event and MUST stay immutable — Swiss OR Art. 958f requires
     * a 10-year retention for financial/operational audit trails.
     */
    public const MUTABLE_ACTIONS = ['note'];

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

    /**
     * DATA-04 (sprint 2026-04-24): application-level immutability guard.
     *
     * Compliance audit events cannot be deleted through Eloquent. A bug
     * that calls `AuditLog::find($id)->delete()` on a non-note row will
     * now throw a LogicException instead of silently dropping history.
     *
     * The ArchiveAuditLogsCommand path is unaffected: it uses the query
     * builder (`DB::table('audit_logs')->whereIn(...)->delete()`), which
     * bypasses Eloquent events. That command is the ONLY sanctioned
     * path for moving cold rows into audit_logs_archive; callers must
     * not use the Eloquent delete API.
     */
    protected static function booted(): void
    {
        $blockMutation = function (AuditLog $log, string $verb) {
            if (! in_array($log->action, self::MUTABLE_ACTIONS, true)) {
                throw new \LogicException(
                    "AuditLog #{$log->id} (action={$log->action}) is compliance data and cannot be {$verb}. "
                    .'Administrative notes (action=note) are the only rows that may be mutated through Eloquent; '
                    .'the sanctioned archival path is the audit-logs:archive artisan command.'
                );
            }
        };

        static::deleting(fn (AuditLog $log) => $blockMutation($log, 'deleted'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
