<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            self::logAudit('created', $model);
        });

        static::updated(function (Model $model) {
            self::logAudit('updated', $model);
        });

        static::deleted(function (Model $model) {
            self::logAudit('deleted', $model);
        });
    }

    protected static function logAudit($action, Model $model)
    {
        // Don't log if running in console (unless explicitly wanted, but usually noisy)
        // keeping it simple for now, logging everything.

        $changes = null;

        if ($action === 'updated') {
            $changes = [
                'before' => $model->getOriginal(),
                'after' => $model->getChanges(),
            ];
            // Filter out timestamps to reduce noise
            unset($changes['before']['updated_at'], $changes['after']['updated_at']);

            // If no meaningful changes, don't log
            if (empty($changes['after'])) {
                return;
            }
        } elseif ($action === 'created') {
            $changes = [
                'attributes' => $model->getAttributes(),
            ];
        }

        // Get user_id safely - verify user exists to prevent FK violations
        $userId = Auth::id();
        if ($userId) {
            // Verify user still exists (may have been deleted)
            $userExists = \App\Models\User::withoutGlobalScopes()->where('id', $userId)->exists();
            if (!$userExists) {
                $userId = null;
            }
        }

        try {
            AuditLog::create([
                'user_id' => $userId,
                'action' => $action,
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'changes' => $changes,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            // Don't let audit logging break the main operation
            \Log::warning('Audit log failed: ' . $e->getMessage());
        }
    }
}
